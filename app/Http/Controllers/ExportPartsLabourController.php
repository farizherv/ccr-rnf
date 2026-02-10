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

        $templatePath = storage_path('app/templates/parts_labour_template.xlsx');
        if (!is_file($templatePath)) {
            abort(404, 'Template Excel tidak ditemukan: ' . $templatePath);
        }

        $spreadsheet = IOFactory::load($templatePath);

        $ws = $spreadsheet->getSheetByName('PARTS & LABOUR WORKSHEET');
        $dt = $spreadsheet->getSheetByName('DETAIL');

        if (!$ws) abort(500, 'Sheet "PARTS & LABOUR WORKSHEET" tidak ada di template.');
        if (!$dt) abort(500, 'Sheet "DETAIL" tidak ada di template.');

        $rows = $report->parts_payload['rows'] ?? [];
        if (!is_array($rows)) $rows = [];

        // ambil row yang ada isi
        $rows = array_values(array_filter($rows, function ($r) {
            if (!is_array($r)) return false;
            return !empty($r['qty']) || !empty($r['part_number']) || !empty($r['part_description'])
                || !empty($r['purchase_price']) || !empty($r['sales_price']);
        }));

        $startRow = 4;
        $templateTotalRow = 100;
        $capacity = $templateTotalRow - $startRow; // 96
        $count = count($rows);

        // sesuaikan jumlah row agar sama dengan data
        if ($count > $capacity) {
            $need = $count - $capacity;
            $insertAt = $templateTotalRow;
            $ws->insertNewRowBefore($insertAt, $need);

            $styleSrc = $ws->getStyle("A{$startRow}:J{$startRow}");
            for ($i = 0; $i < $need; $i++) {
                $r = $insertAt + $i;
                $ws->duplicateStyle($styleSrc, "A{$r}:J{$r}");
                $ws->getRowDimension($r)->setRowHeight($ws->getRowDimension($startRow)->getRowHeight());
            }
        } elseif ($count < $capacity) {
            $removeFrom = $startRow + $count;
            $removeCount = $capacity - $count;
            if ($removeCount > 0) $ws->removeRow($removeFrom, $removeCount);
        }

        $totalRow = $startRow + $count;
        $lastDataRow = $totalRow - 1;

        for ($i = 0; $i < $count; $i++) {
            $r = $startRow + $i;
            $row = $rows[$i];

            $qty = (float) preg_replace('/[^\d.]/', '', (string)($row['qty'] ?? 0));
            $purchase = (float) preg_replace('/[^\d.]/', '', (string)($row['purchase_price'] ?? 0));
            $sales = (float) preg_replace('/[^\d.]/', '', (string)($row['sales_price'] ?? 0));

            $ws->setCellValue("A{$r}", $i + 1);
            $ws->setCellValue("B{$r}", $qty);
            $ws->setCellValue("C{$r}", (string)($row['uom'] ?? ''));
            $ws->setCellValue("D{$r}", (string)($row['part_number'] ?? ''));
            $ws->setCellValue("E{$r}", (string)($row['part_description'] ?? ''));
            $ws->setCellValue("F{$r}", (string)($row['part_section'] ?? ''));
            $ws->setCellValue("G{$r}", $purchase);
            $ws->setCellValue("I{$r}", $sales);

            $ws->setCellValue("H{$r}", "=B{$r}*G{$r}");
            $ws->setCellValue("J{$r}", "=B{$r}*I{$r}");
        }

        if ($count > 0) {
            $ws->setCellValue("H{$totalRow}", "=SUM(H{$startRow}:H{$lastDataRow})");
            $ws->setCellValue("J{$totalRow}", "=SUM(J{$startRow}:J{$lastDataRow})");
        } else {
            $ws->setCellValue("H{$totalRow}", 0);
            $ws->setCellValue("J{$totalRow}", 0);
        }

        // link DETAIL L51 -> total sales
        $dt->setCellValue("L51", "='PARTS & LABOUR WORKSHEET'!J{$totalRow}");

        $filename = 'Parts_Labour_Engine_' . $report->id . '_' . Str::random(6) . '.xlsx';
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0775, true);
        $tmpPath = $tmpDir . '/' . $filename;

        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }
}
