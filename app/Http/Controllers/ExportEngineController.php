<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class ExportEngineController extends Controller
{
    private function safeFolder($text): string
    {
        $text = trim((string) $text);
        $text = preg_replace('/[^\pL\pN\s\-_\.]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return $text !== '' ? $text : 'UNKNOWN';
    }

    private function normalizeGroupFolder($group): string
    {
        $group = $this->safeFolder($group);
        if (strtolower($group) === 'operator seat') return 'Seat Operator';
        return $group;
    }

    private function publicDiskAbsPath($maybePath): ?string
    {
        $raw = ltrim((string) $maybePath, '/');

        // normalisasi prefix yang sering kejadian
        $raw = preg_replace('#^(storage/|public/)#', '', $raw);
        $raw = preg_replace('#^storage/public/#', '', $raw);

        // kalau path relatif disk public
        if ($raw !== '' && Storage::disk('public')->exists($raw)) {
            return Storage::disk('public')->path($raw);
        }

        // kalau ternyata DB nyimpan absolute path
        if (is_file($maybePath)) {
            return $maybePath;
        }

        return null;
    }

    /**
     * ==========================================
     * 🔥 GENERATE WORD ENGINE
     * ==========================================
     */
    public function generateEngine($id): string
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

        $template = new TemplateProcessor(
            public_path('templates/TEMPLATE CCR ENGINE.docx')
        );

        // HEADER
        $template->setValue('COMPONENT', (string) $report->component);
        $template->setValue('MAKE', (string) $report->make);
        $template->setValue('MODEL', (string) $report->model);
        $template->setValue('SN', (string) $report->sn);
        $template->setValue('SMU', (string) $report->smu);
        $template->setValue('CUSTOMER', (string) $report->customer);
        $template->setValue(
            'INSPECTION_DATE',
            optional($report->inspection_date)->format('Y-m-d')
        );

        // FOTO FIT
        $FIXED_WIDTH = 350;
        $MAX_HEIGHT  = 455;

        $itemsData = [];

        foreach ($report->items as $item) {
            $photoRows = [];

            foreach ($item->photos as $photo) {
                $path = $this->publicDiskAbsPath($photo->path);
                if (!$path) continue;

                try {
                    [$w, $h] = getimagesize($path);
                } catch (\Throwable $e) {
                    continue;
                }

                if ($h == 0) continue;
                $ratio = $w / $h;

                $fitWidth  = $FIXED_WIDTH;
                $fitHeight = $FIXED_WIDTH / $ratio;

                if ($fitHeight > $MAX_HEIGHT) {
                    $fitHeight = $MAX_HEIGHT;
                    $fitWidth  = $MAX_HEIGHT * $ratio;
                }

                $photoRows[] = [
                    'photo' => [
                        'path'   => $path,
                        'width'  => $fitWidth,
                        'height' => $fitHeight,
                        'ratio'  => true,
                    ]
                ];
            }

            if (empty($photoRows)) {
                $photoRows[] = ['photo' => null];
            }

            $itemsData[] = [
                'description' => $item->description ?: '-',
                'photos'      => $photoRows
            ];
        }

        // CLONE ITEM TABLE
        $template->cloneBlock('ITEM_TABLE', max(count($itemsData), 1), true, true);

        foreach ($itemsData as $i => $itemData) {
            $n = $i + 1;

            $template->setValue("description#{$n}", $itemData['description']);

            $template->cloneBlock(
                "PHOTO_BLOCK#{$n}",
                count($itemData['photos']),
                true,
                true
            );

            $blank = public_path('blank.png');

            foreach ($itemData['photos'] as $k => $photo) {
                $m = $k + 1;

                if ($photo['photo'] === null) {
                    $template->setImageValue("photo#{$n}#{$m}", [
                        'path'   => $blank,
                        'width'  => 1,
                        'height' => 1,
                        'ratio'  => true,
                    ]);
                    $template->setValue("photo_text#{$n}#{$m}", "NO PHOTO");
                    continue;
                }

                $template->setImageValue("photo#{$n}#{$m}", [
                    'path'   => $photo['photo']['path'],
                    'width'  => $photo['photo']['width'],
                    'height' => $photo['photo']['height'],
                    'ratio'  => true,
                ]);
                $template->setValue("photo_text#{$n}#{$m}", "");
            }
        }

        // SAVE WORD FILE (rapi + update docx_path)
        $groupFolder = $this->normalizeGroupFolder($report->group_folder);
        $customer    = $this->safeFolder($report->customer);
        $component   = $this->safeFolder($report->component);
        $model       = $this->safeFolder($report->model);
        $reportId    = $report->id;

        $fileName = "CCR_ENGINE.docx";
        $relativePath = "ccr_files/{$groupFolder}/{$customer}/{$component}/{$model}/{$reportId}/Export/{$fileName}";

        Storage::disk('public')->makeDirectory(dirname($relativePath));

        // kalau ada file lama, hapus dulu biar gak “ketuker”
        if ($report->docx_path && Storage::disk('public')->exists($report->docx_path)) {
            Storage::disk('public')->delete($report->docx_path);
        }

        $savePath = storage_path('app/public/' . $relativePath);
        $template->saveAs($savePath);

        $report->docx_path = $relativePath;
        $report->docx_generated_at = now();
        $report->save();

        return $savePath;
    }

    /**
     * ================================
     * 🔥 PREVIEW ENGINE
     * ================================
     */
    public function previewPdf($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);
        return view('engine.preview', compact('report'));
    }

    /**
     * ================================
     * 🔥 DOWNLOAD WORD ENGINE (AUTO-REGENERATE)
     * ================================
     */
    public function downloadEngine($id)
    {
        $report = CcrReport::findOrFail($id);

        // Kalau sudah ada file dan masih ada di storage, langsung pakai
        if ($report->docx_path && Storage::disk('public')->exists($report->docx_path)) {
            $abs = Storage::disk('public')->path($report->docx_path);
        } else {
            // Kalau belum ada, generate dulu (method kamu return absolute path)
            $abs = $this->generateEngine($id);
        }

        if (!is_file($abs)) {
            abort(404, 'File Word tidak ditemukan.');
        }

        // ✅ JANGAN pakai ->header() di BinaryFileResponse
        return response()->download(
            $abs,
            basename($abs),
            [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
                'Expires'       => '0',
            ]
        );
    }

}
