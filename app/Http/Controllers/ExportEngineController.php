<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\CcrReport;
use Illuminate\Support\Facades\Storage;


class ExportEngineController extends Controller
{
    
    private function safeFolder($text)
    {
    $text = trim((string) $text);
    $text = preg_replace('/[^\pL\pN\s\-_\.]/u', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return $text !== '' ? $text : 'UNKNOWN';
    }

    private function normalizeGroupFolder($group)
    {
    $group = $this->safeFolder($group);
    if (strtolower($group) === 'operator seat') return 'Seat Operator';
    return $group;
    }

    /**
     * ==========================================
     * 🔥 GENERATE WORD ENGINE
     * ==========================================
     */
    public function generateEngine($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

        // LOAD TEMPLATE
        $template = new TemplateProcessor(
            public_path('templates/TEMPLATE CCR ENGINE.docx')
        );

        // HEADER
        $template->setValue('COMPONENT', $report->component);
        $template->setValue('MAKE', $report->make);
        $template->setValue('MODEL', $report->model);
        $template->setValue('SN', $report->sn);
        $template->setValue('SMU', $report->smu);
        $template->setValue('CUSTOMER', $report->customer);
        $template->setValue(
            'INSPECTION_DATE',
            optional($report->inspection_date)->format('Y-m-d')
        );

        /**
         * FOTO FIT FINAL
         */
        $FIXED_WIDTH = 350;
        $MAX_HEIGHT  = 455;
        $SPACING     = 25;

        $itemsData = [];

        foreach ($report->items as $item) {

            $photoRows = [];

            foreach ($item->photos as $index => $photo) {

                $path = Storage::disk('public')->path($photo->path);
                if (!file_exists($path)) continue;

                try {
                    [$w, $h] = getimagesize($path);
                } catch (\Throwable $e) {
                    continue;
                }

                $ratio = $w / $h;

                // Width-fit default
                $fitWidth  = $FIXED_WIDTH;
                $fitHeight = $FIXED_WIDTH / $ratio;

                // Height-fit jika terlalu tinggi
                if ($fitHeight > $MAX_HEIGHT) {
                    $fitHeight = $MAX_HEIGHT;
                    $fitWidth  = $MAX_HEIGHT * $ratio;
                }

                $photoRows[] = [
                    'photo' => [
                        'path'      => $path,
                        'width'     => $fitWidth,
                        'height'    => $fitHeight,
                        'ratio'     => true,
                        'alignment' => 'center'
                    ]
                ];
            }

            // Jika tidak ada foto
            if (empty($photoRows)) {
                $photoRows[] = ['photo' => null];
            }


            $itemsData[] = [
                'description' => $item->description ?: '-',
                'photos'      => $photoRows
            ];
        }

        // CLONE ITEM TABLE
        $template->cloneBlock('ITEM_TABLE', count($itemsData), true, true);

        // LOOP ITEM
        foreach ($itemsData as $i => $itemData) {

            $n = $i + 1;

            // DESCRIPTION
            $template->setValue("description#{$n}", $itemData['description']);

            // CLONE PHOTO BLOCK
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
                        'path' => $blank,
                        'width' => 1,
                        'height' => 1,
                        'ratio' => true,
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

        // relative path untuk disk public
        $relativePath = "ccr_files/{$groupFolder}/{$customer}/{$component}/{$model}/{$reportId}/Export/{$fileName}";

        // pastikan foldernya ada
        Storage::disk('public')->makeDirectory(dirname($relativePath));

        // absolute path untuk saveAs
        $savePath = storage_path('app/public/' . $relativePath);

        $template->saveAs($savePath);

        // simpan path ke DB
        $report->docx_path = $relativePath;
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

        // Kalau kamu memang mau preview halaman (bukan PDF), gunakan view preview
        return view('engine.preview', compact('report'));

        // Kalau view kamu bukan engine.preview, ganti sesuai file view yang ada:
        // return view('engine.show', compact('report'));
    }

    /**
     * ================================
     * 🔥 DOWNLOAD WORD ENGINE
     * ================================
     */
    public function downloadEngine($id)
    {
        $report = CcrReport::findOrFail($id);

        // kalau sudah pernah dibuat dan filenya masih ada, langsung download
        if ($report->docx_path && Storage::disk('public')->exists($report->docx_path)) {
            $abs = storage_path('app/public/' . $report->docx_path);
            return response()->download($abs, basename($abs));
        }

        // kalau belum ada, baru generate
        $filePath = $this->generateEngine($id);

        if (!file_exists($filePath)) {
            abort(404, 'File Word tidak ditemukan.');
        }

        return response()->download($filePath, basename($filePath));
    }

}
