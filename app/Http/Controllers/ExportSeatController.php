<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\CcrReport;

class ExportSeatController extends Controller
{
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

                $path = public_path("storage/" . $photo->path);
                if (!file_exists($path)) continue;

                list($w, $h) = getimagesize($path);
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
                $photoRows[] = [
                    'photo' => [
                        'path'   => public_path('no-image.png'),
                        'width'  => 200,
                        'height' => 200,
                        'ratio'  => true
                    ]
                ];
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

            // INSERT FOTO
            foreach ($itemData['photos'] as $k => $photo) {

                $m = $k + 1;

                $template->setImageValue("photo#{$n}#{$m}", [
                    'path'   => $photo['photo']['path'],
                    'width'  => $photo['photo']['width'],
                    'height' => $photo['photo']['height'],
                    'ratio'  => true
                ]);
            }
        }

        // SAVE FILE
        $fileName = "CCR_SEAT_" .
            preg_replace('/[^A-Za-z0-9\-]/', '_', $report->unit) .
            "_" . time() . ".docx";

        $savePath = storage_path("app/public/" . $fileName);

        $template->saveAs($savePath);

        return $savePath;
    }

    /**
     * ================================
     * 🔥 PREVIEW HTML
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
        $filePath = $this->generateSeat($id);

        if (!file_exists($filePath)) {
            abort(404, 'File Word tidak ditemukan.');
        }

        return response()->download($filePath, basename($filePath));
    }
}
