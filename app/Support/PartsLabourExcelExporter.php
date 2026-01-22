<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

// ADD (dropdown Excel)
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PartsLabourExcelExporter
{
    public static function export(array $payload, string $templatePath, string $outPath): void
    {
        $rows   = $payload['rows']   ?? [];
        $styles = $payload['styles'] ?? [];
        $meta   = $payload['meta']   ?? [];

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        if (!empty($meta['no_unit'])) {
            $sheet->setCellValue('A1', 'No. Unit: ' . $meta['no_unit']);
        }

        // template: header di row 2, data mulai row 3
        $startRow = 3;

        // mapping col index (0..9) -> Excel column
        $cols = ['A','B','C','D','E','F','G','H','I','J'];

        // === FIX: Part Description lebih panjang (mirip Excel contoh kamu) ===
        // (Tidak ganggu yang lain)
        $sheet->getColumnDimension('E')->setWidth(45); // Part Description
        $sheet->getStyle('E:E')->getAlignment()->setWrapText(true);

        // Accounting "Rp" (lebih mirip Excel accounting)
        $rpAccounting = '_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)';

        // === OPTIONAL: Dropdown di Excel (UOM + Part Description) dari file list ===
        // (Kalau file list belum ada / kosong, otomatis skip)
        $uomList = self::loadListFile('uom_list.php');
        $descList = self::loadListFile('part_description_list.php');

        // apply dropdown untuk 300 baris (biar user bisa lanjut isi di Excel)
        $dvStart = $startRow;
        $dvEnd   = $startRow + 300 - 1;

        self::applyDropdownList($spreadsheet, $sheet, 'C', $dvStart, $dvEnd, $uomList, 'uom');       // UOM
        self::applyDropdownList($spreadsheet, $sheet, 'E', $dvStart, $dvEnd, $descList, 'partdesc'); // Part Description

        foreach ($rows as $i => $r) {
            $excelRow = $startRow + $i;

            $qty      = (int)($r['qty'] ?? 0);

            // NOTE: sekarang H (Total) & J (Extended) tidak formula, isi dari payload (manual input)
            $purchase = self::toIntOrNull($r['purchase_price'] ?? null);
            $total    = self::toIntOrNull($r['total'] ?? null);
            $sales    = self::toIntOrNull($r['sales_price'] ?? null);

            // FIX: payload pakai extended_price (fallback ke extended kalau ada legacy)
            $extended = self::toIntOrNull($r['extended_price'] ?? ($r['extended'] ?? null));

            $sheet->setCellValue("A{$excelRow}", $i + 1);
            $sheet->setCellValue("B{$excelRow}", $qty);
            $sheet->setCellValue("C{$excelRow}", $r['uom'] ?? '');
            $sheet->setCellValue("D{$excelRow}", $r['part_number'] ?? '');
            $sheet->setCellValue("E{$excelRow}", $r['part_description'] ?? '');
            $sheet->setCellValue("F{$excelRow}", $r['part_section'] ?? '');

            $sheet->setCellValue("G{$excelRow}", $purchase);
            $sheet->setCellValue("H{$excelRow}", $total);
            $sheet->setCellValue("I{$excelRow}", $sales);
            $sheet->setCellValue("J{$excelRow}", $extended);

            // apply accounting format for money cols (G,H,I,J)
            foreach (['G','H','I','J'] as $mc) {
                $sheet->getStyle("{$mc}{$excelRow}")
                      ->getNumberFormat()
                      ->setFormatCode($rpAccounting);
            }

            // apply per-cell styles dari payload
            foreach ($cols as $ci => $colLetter) {
                $key = $i . ':' . $ci;
                if (!isset($styles[$key]) || !is_array($styles[$key])) continue;

                $s = $styles[$key];
                $cell = "{$colLetter}{$excelRow}";

                $styleArr = [];

                // font
                $font = [];
                if (!empty($s['bold'])) $font['bold'] = true;
                if (!empty($s['italic'])) $font['italic'] = true;
                if (!empty($s['underline'])) $font['underline'] = Font::UNDERLINE_SINGLE;
                if (!empty($s['color'])) {
                    $font['color'] = ['argb' => self::hexToARGB($s['color'])];
                }
                if (!empty($font)) $styleArr['font'] = $font;

                // fill
                if (!empty($s['bg'])) {
                    $styleArr['fill'] = [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => self::hexToARGB($s['bg'])],
                    ];
                }

                // align
                if (!empty($s['align'])) {
                    $h = match ($s['align']) {
                        'center' => Alignment::HORIZONTAL_CENTER,
                        'right'  => Alignment::HORIZONTAL_RIGHT,
                        default  => Alignment::HORIZONTAL_LEFT,
                    };
                    $styleArr['alignment'] = ['horizontal' => $h];
                }

                if (!empty($styleArr)) {
                    $sheet->getStyle($cell)->applyFromArray($styleArr);
                }
            }
        }

        /**
         * OPTIONAL: footer manual (kalau template kamu memang butuh)
         * Aku tulis di baris setelah data terakhir: H & J.
         */
        $footerTotal    = self::toIntOrNull($meta['footer_total'] ?? null);
        $footerExtended = self::toIntOrNull($meta['footer_extended'] ?? null);

        if ($footerTotal !== null || $footerExtended !== null) {
            $footerRow = $startRow + max(count($rows), 1);

            if ($footerTotal !== null) {
                $sheet->setCellValue("H{$footerRow}", $footerTotal);
                $sheet->getStyle("H{$footerRow}")->getNumberFormat()->setFormatCode($rpAccounting);
            }
            if ($footerExtended !== null) {
                $sheet->setCellValue("J{$footerRow}", $footerExtended);
                $sheet->getStyle("J{$footerRow}")->getNumberFormat()->setFormatCode($rpAccounting);
            }
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outPath);
    }

    private static function toIntOrNull($v): ?int
    {
        if ($v === null) return null;
        $s = preg_replace('/[^\d]/', '', (string)$v);
        if ($s === '') return null;
        return (int)$s;
    }

    private static function hexToARGB(string $hex): string
    {
        $hex = trim($hex);
        if ($hex === '') return 'FF000000';
        if ($hex[0] === '#') $hex = substr($hex, 1);
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return 'FF' . strtoupper($hex);
    }

    // =============================
    // ADD: load list file (resources/data/*.php)
    // =============================
    private static function loadListFile(string $filename): array
    {
        $path = null;

        if (function_exists('base_path')) {
            $path = base_path('resources/data/' . $filename);
        } else {
            // fallback kalau helper Laravel tidak tersedia
            $path = dirname(__DIR__, 2) . '/resources/data/' . $filename;
        }

        if (!is_file($path)) return [];

        $data = include $path;
        if (!is_array($data)) return [];

        $out = [];
        foreach ($data as $v) {
            $v = trim((string)$v);
            if ($v === '') continue;
            $out[] = $v;
        }

        return array_values(array_unique($out));
    }

    // =============================
    // ADD: apply dropdown list to a column range
    // =============================
    private static function applyDropdownList(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $colLetter,
        int $rowStart,
        int $rowEnd,
        array $list,
        string $key
    ): void {
        if (empty($list)) return;

        // pilih mode:
        // - list pendek => langsung "a,b,c"
        // - list panjang => taruh di sheet hidden "_lists"
        $joined = implode(',', $list);
        $useDirect = (strlen($joined) <= 240);

        $formula = '';

        if ($useDirect) {
            // escape double quote
            $formula = '"' . str_replace('"', '""', $joined) . '"';
        } else {
            // buat/ambil sheet hidden
            $listSheet = $spreadsheet->getSheetByName('_lists');
            if (!$listSheet) {
                $listSheet = new Worksheet($spreadsheet, '_lists');
                $spreadsheet->addSheet($listSheet);
                $listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
            }

            // mapping kolom list
            $listCol = ($key === 'uom') ? 'A' : 'B';

            // isi list (overwrite kolom itu)
            $r = 1;
            foreach ($list as $val) {
                $listSheet->setCellValue("{$listCol}{$r}", $val);
                $r++;
            }

            $end = max(1, count($list));
            // range formula untuk data validation
            $formula = "'_lists'!\${$listCol}\$1:\${$listCol}\${$end}";
        }

        // buat template DataValidation
        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST);
        $dv->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $dv->setAllowBlank(true);
        $dv->setShowInputMessage(true);
        $dv->setShowErrorMessage(true);
        $dv->setShowDropDown(true);
        $dv->setFormula1($formula);

        // apply per cell
        for ($row = $rowStart; $row <= $rowEnd; $row++) {
            $cell = $sheet->getCell("{$colLetter}{$row}");
            $cell->setDataValidation(clone $dv);
        }
    }
}
