<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class ExportSeatController extends Controller
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

        $raw = preg_replace('#^(storage/|public/)#', '', $raw);
        $raw = preg_replace('#^storage/public/#', '', $raw);

        if ($raw !== '' && Storage::disk('public')->exists($raw)) {
            return Storage::disk('public')->path($raw);
        }

        if (is_file($maybePath)) {
            return $maybePath;
        }

        return null;
    }

    /**
     * ==========================================
     * 🔥 GENERATE WORD SEAT
     * ==========================================
     */
    public function generateSeat($id): string
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

        $template = new TemplateProcessor(
            public_path('templates/TEMPLATE CCR SEAT.docx')
        );

        // HEADER
        $template->setValue('COMPONENT', (string) $report->component);
        $template->setValue('MAKE', (string) $report->make);
        $template->setValue('UNIT', (string) $report->unit);
        $template->setValue('MODEL', (string) $report->model);
        $template->setValue('WO_PR', (string) $report->wo_pr);
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

        // SAVE WORD FILE
        $groupFolder = $this->normalizeGroupFolder($report->group_folder);
        $customer    = $this->safeFolder($report->customer);
        $component   = $this->safeFolder($report->component);
        $unit        = $this->safeFolder($report->unit);
        $reportId    = $report->id;

        $fileName = "CCR_SEAT.docx";
        $relativePath = "ccr_files/{$groupFolder}/{$customer}/{$component}/{$unit}/{$reportId}/Export/{$fileName}";

        Storage::disk('public')->makeDirectory(dirname($relativePath));

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
     * 🔥 PREVIEW SEAT
     * ================================
     */
    public function preview($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);
        return view('seat.preview', compact('report'));
    }

    /**
     * ================================
     * 🔥 DOWNLOAD WORD SEAT (AUTO-REGENERATE)
     * ================================
     */
    public function generateSeatDownload($id)
    {
        $report = CcrReport::findOrFail($id);

        $needRegenerate =
            empty($report->docx_path) ||
            !Storage::disk('public')->exists($report->docx_path) ||
            empty($report->docx_generated_at) ||
            ($report->updated_at && $report->docx_generated_at && $report->updated_at->gt($report->docx_generated_at));

        if ($needRegenerate) {
            $filePath = $this->generateSeat($id);
            if (!is_file($filePath)) abort(404, 'File Word tidak ditemukan.');
            $downloadName = "CCR_SEAT_{$report->id}_" . now()->format('Ymd_His') . ".docx";
            return response()->download($filePath, $downloadName)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $abs = storage_path('app/public/' . $report->docx_path);
        $downloadName = "CCR_SEAT_{$report->id}_" . now()->format('Ymd_His') . ".docx";
        return response()->download($abs, $downloadName)->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
