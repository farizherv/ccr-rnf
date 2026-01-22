<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportPartsLabourController extends Controller
{
    public function engine(int $id)
    {
        $report = CcrReport::findOrFail($id);

        $template = storage_path('app/templates/parts_labour_template.xlsx');
        $spreadsheet = IOFactory::load($template);

        $ws = $spreadsheet->getSheetByName('PARTS & LABOUR WORKSHEET');
        $dt = $spreadsheet->getSheetByName('DETAIL');

        $rows = $report->parts_payload['rows'] ?? [];
        $rows = array_values(array_filter($rows, function ($r) {
            return !empty($r['qty']) || !empty($r['part_number']) || !empty($r['part_description'])
                || !empty($r['purchase_price']) || !empty($r['sales_price']);
        }));

        $startRow = 4;
        $templateTotalRow = 100; // di template excel kamu total ada di row 100
        $capacity = $templateTotalRow - $startRow; // 96 row (4..99)

        $count = count($rows);

        // ====== 1) Bikin jumlah row data menyesuaikan (rapi) ======
        if ($count > $capacity) {
            $need = $count - $capacity;
            $insertAt = $templateTotalRow; // insert tepat sebelum total row
            $ws->insertNewRowBefore($insertAt, $need);

            // copy style dari row data pertama (row 4) untuk baris baru
            $styleSrc = $ws->getStyle("A{$startRow}:J{$startRow}");
            for ($i = 0; $i < $need; $i++) {
                $r = $insertAt + $i;
                $ws->duplicateStyle($styleSrc, "A{$r}:J{$r}");
                $ws->getRowDimension($r)->setRowHeight($ws->getRowDimension($startRow)->getRowHeight());
            }
        } elseif ($count < $capacity) {
            // hapus row kosong supaya total row naik (rapi)
            $removeFrom = $startRow + $count;
            $removeCount = $capacity - $count;
            if ($removeCount > 0) {
                $ws->removeRow($removeFrom, $removeCount);
            }
        }

        // Setelah insert/remove, total row sekarang posisinya:
        $totalRow = $startRow + $count;
        $lastDataRow = $totalRow - 1;

        // ====== 2) Isi data ======
        for ($i = 0; $i < $count; $i++) {
            $r = $startRow + $i;
            $row = $rows[$i];

            $qty = (float)($row['qty'] ?? 0);
            $purchase = (float)($row['purchase_price'] ?? 0);
            $sales = (float)($row['sales_price'] ?? 0);

            $ws->setCellValue("A{$r}", $i + 1);
            $ws->setCellValue("B{$r}", $qty);
            $ws->setCellValue("C{$r}", $row['uom'] ?? '');
            $ws->setCellValue("D{$r}", $row['part_number'] ?? '');
            $ws->setCellValue("E{$r}", $row['part_description'] ?? '');
            $ws->setCellValue("F{$r}", $row['part_section'] ?? '');
            $ws->setCellValue("G{$r}", $purchase);
            $ws->setCellValue("I{$r}", $sales);

            // formula extended
            $ws->setCellValue("H{$r}", "=B{$r}*G{$r}");
            $ws->setCellValue("J{$r}", "=B{$r}*I{$r}");
        }

        // ====== 3) Formula total row (yang sekarang sudah “naik”) ======
        if ($count > 0) {
            $ws->setCellValue("H{$totalRow}", "=SUM(H{$startRow}:H{$lastDataRow})");
            $ws->setCellValue("J{$totalRow}", "=SUM(J{$startRow}:J{$lastDataRow})");
        } else {
            $ws->setCellValue("H{$totalRow}", 0);
            $ws->setCellValue("J{$totalRow}", 0);
        }

        // ====== 4) Update DETAIL link ke total parts ======
        // Excel kamu: L51 = total parts
        $dt->setCellValue("L51", "='PARTS & LABOUR WORKSHEET'!J{$totalRow}");

        // ====== 5) Download ======
        $filename = 'Parts_Labour_Engine_' . $report->id . '_' . Str::random(6) . '.xlsx';
        $tmpPath = storage_path('app/tmp/' . $filename);
        if (!is_dir(dirname($tmpPath))) mkdir(dirname($tmpPath), 0775, true);

        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }
}
