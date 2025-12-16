<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\CcrReport;

class ExportEngineController extends Controller
{
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

                $path = public_path("storage/" . $photo->path);
                if (!file_exists($path)) continue;

                list($w, $h) = getimagesize($path);
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
                $photoRows[] = [
                    'photo' => [
                        'path'      => public_path('no-image.png'),
                        'width'     => 200,
                        'height'    => 200,
                        'ratio'     => true,
                        'alignment' => 'center'
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

        // SAVE WORD FILE
        $fileName = "CCR_ENGINE_" .
            preg_replace('/[^A-Za-z0-9\-]/', '_', $report->component) .
            "_" . time() . ".docx";

        $savePath = storage_path("app/public/" . $fileName);

        $template->saveAs($savePath);

        return $savePath;
    }

    /**
     * ================================
     * 🔥 DOWNLOAD WORD ENGINE
     * ================================
     */
    public function downloadEngine($id)
    {
        $filePath = $this->generateEngine($id);

        if (!file_exists($filePath)) {
            abort(404, 'File Word tidak ditemukan.');
        }

        return response()->download($filePath, basename($filePath));
    }
}
