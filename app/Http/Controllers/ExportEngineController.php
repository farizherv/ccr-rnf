<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use App\Models\CcrReport;

class ExportEngineController extends Controller
{
    public function generateEngine($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

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
        $template->setValue('INSPECTION_DATE', $report->inspection_date->format('Y-m-d'));

        /**
         * FOTO FIT FINAL (presisi dengan tabel)
         * - Lebar max: 350 px
         * - Tinggi max: 455 px  ← (dikurangi 5 px sesuai permintaan)
         * - Spacing foto > 1: 25 px
         */

        $FIXED_WIDTH   = 350;  
        $MAX_HEIGHT    = 455;   // dikurangi 5px
        $SPACING       = 25;    

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

                // Jika terlalu tinggi, gunakan height-fit
                if ($fitHeight > $MAX_HEIGHT) {
                    $fitHeight = $MAX_HEIGHT;
                    $fitWidth  = $MAX_HEIGHT * $ratio;
                }

                // Jarak antar foto
                $marginTop = ($index === 0) ? 0 : $SPACING;

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
                        'path'      => public_path("no-image.png"),
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

        // LOOP PER ITEM
        foreach ($itemsData as $i => $itemData) {

            $n = $i + 1;

            // DESCRIPTION
            $template->setValue("description#{$n}", $itemData['description']);

            // CLONE PHOTO BLOCK
            $template->cloneBlock("PHOTO_BLOCK#{$n}", count($itemData['photos']), true, true);

            // INSERT FOTO
            foreach ($itemData['photos'] as $k => $photo) {

                $m = $k + 1;

                $template->setImageValue("photo#{$n}#{$m}", [
                    'path'      => $photo['photo']['path'],
                    'width'     => $photo['photo']['width'],
                    'height'    => $photo['photo']['height'],
                    'ratio'     => true
                ]);
            }
        }

        // SAVE WORD FILE
        $fileName = "CCR_ENGINE_" . preg_replace('/[^A-Za-z0-9\-]/', '_', $report->component) . "_" . time() . ".docx";
        $savePath = storage_path("app/public/" . $fileName);

        $template->saveAs($savePath);
        return $savePath;
    }


    public function previewPdf($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);
        return view('engine.preview', compact('report'));
    }


    public function downloadPdf($id)
    {
        $report = CcrReport::with('items.photos')->findOrFail($id);

        $pdf = \PDF::loadView('engine.preview_pdf', compact('report'))
            ->setPaper('A4', 'portrait');

        $fileName = "CCR_ENGINE_" . $report->component . "_" . time() . ".pdf";

        return $pdf->download($fileName);
    }


    public function downloadEngine($id)
    {
        $filePath = $this->generateEngine($id);

        if (!file_exists($filePath)) {
            abort(404, "File Word tidak ditemukan.");
        }

        return response()->download($filePath, basename($filePath));
    }
}
