<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\CcrReport;
use Illuminate\Support\Facades\Storage;


class ExportSeatController extends Controller
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
    // samakan dengan folder NAS kamu
    if (strtolower($group) === 'operator seat') return 'Seat Operator';
    return $group;
    }

    
    private function publicDiskAbsPath($maybePath)
    {
        $raw = ltrim((string) $maybePath, '/');

        // normalisasi prefix yang sering kejadian
        $raw = preg_replace('#^(storage/|public/)#', '', $raw);
        $raw = preg_replace('#^storage/public/#', '', $raw);

        // kalau memang path relatif disk public
        if (Storage::disk('public')->exists($raw)) {
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
     * 🔥 GENERATE WORD SEAT (SAMA ENGINE)
     * ==========================================
     */
    public function generateSeat($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

        // LOAD TEMPLATE
        $template = new TemplateProcessor(
            public_path('templates/TEMPLATE CCR SEAT.docx')
        );

        // HEADER
        $template->setValue('COMPONENT', $report->component);
        $template->setValue('MAKE', $report->make);
        $template->setValue('UNIT', $report->unit);
        $template->setValue('MODEL', $report->model);
        $template->setValue('WO_PR', $report->wo_pr);
        $template->setValue('CUSTOMER', $report->customer);
        $template->setValue(
            'INSPECTION_DATE',
            optional($report->inspection_date)->format('Y-m-d')
        );

        /**
         * FOTO FIT FINAL (SAMA ENGINE)
         */
        $FIXED_WIDTH = 350;
        $MAX_HEIGHT  = 455;
        $SPACING     = 25;

        $itemsData = [];

        foreach ($report->items as $item) {

            $photoRows = [];

            foreach ($item->photos as $index => $photo) {

                $path = $this->publicDiskAbsPath($photo->path);
                if (!$path) continue;

                try {
                    [$w, $h] = getimagesize($path);
                } catch (\Throwable $e) {
                    continue;
                }

                $ratio = $w / $h;

                // Default width-fit
                $fitWidth  = $FIXED_WIDTH;
                $fitHeight = $FIXED_WIDTH / $ratio;

                // Jika terlalu tinggi → height-fit
                if ($fitHeight > $MAX_HEIGHT) {
                    $fitHeight = $MAX_HEIGHT;
                    $fitWidth  = $MAX_HEIGHT * $ratio;
                }

                $photoRows[] = [
                    'photo' => [
                        'path'   => $path,
                        'width'  => $fitWidth,
                        'height' => $fitHeight,
                        'ratio'  => true
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

                // ✅ kalau tidak ada foto
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

                // ✅ kalau ada foto
                $template->setImageValue("photo#{$n}#{$m}", [
                    'path'   => $photo['photo']['path'],
                    'width'  => $photo['photo']['width'],
                    'height' => $photo['photo']['height'],
                    'ratio'  => true,
                ]);
                $template->setValue("photo_text#{$n}#{$m}", "");
            }


        }

        // SAVE FILE
        // SAVE WORD FILE (rapi + update docx_path)
        $groupFolder = $this->normalizeGroupFolder($report->group_folder);
        $customer    = $this->safeFolder($report->customer);
        $component   = $this->safeFolder($report->component);
        $unit        = $this->safeFolder($report->unit);
        $reportId    = $report->id;

        $fileName = "CCR_SEAT.docx";

        // relative path untuk disk public
        $relativePath = "ccr_files/{$groupFolder}/{$customer}/{$component}/{$unit}/{$reportId}/Export/{$fileName}";

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
     * 🔥 DOWNLOAD WORD SEAT
     * ================================
     */
    public function generateSeatDownload($id)
    {
        $report = CcrReport::findOrFail($id);

        // kalau sudah pernah dibuat dan filenya masih ada, langsung download
        if ($report->docx_path && Storage::disk('public')->exists($report->docx_path)) {
            $abs = storage_path('app/public/' . $report->docx_path);
            return response()->download($abs, basename($abs));
        }

        // kalau belum ada, baru generate
        $filePath = $this->generateSeat($id);

        if (!file_exists($filePath)) {
            abort(404, 'File Word tidak ditemukan.');
        }

        return response()->download($filePath, basename($filePath));
    }

}

