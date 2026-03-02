<?php

namespace App\Http\Controllers;

use App\Models\CcrReport;
use App\Support\CcrHeavyJobBroker;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class ExportPartsLabourController extends Controller
{
    private const EXPORT_TIMEOUT_SECONDS = 600;
    private const STALE_EXPORT_MAX_AGE_SECONDS = 604800; // 7 hari
    private const MAX_CACHED_EXPORT_FILES = 10;
    private const EXPORT_SCHEMA_VERSION = 'parts-labour-v23-seat-detail-min-rows';
    private const TEMPLATE_START_ROW = 4;
    private const TEMPLATE_TOTAL_ROW = 100;
    private const DETAIL_MAIN_START_ROW = 10;
    private const DETAIL_MAIN_END_ROW = 50;
    private const DETAIL_MAIN_STYLE_ROW = 49;
    private const DETAIL_SUBTOTAL_ROW = 51;
    private const DETAIL_CONSUMABLE_ROW = 53;
    private const DETAIL_PAINT_START_ROW = 56;
    private const DETAIL_PAINT_END_ROW = 61;
    private const DETAIL_PAINT_STYLE_ROW = 57;
    private const DETAIL_EXTERNAL_START_ROW = 67;
    private const DETAIL_EXTERNAL_END_ROW = 74;
    private const DETAIL_EXTERNAL_STYLE_ROW = 68;
    private const DETAIL_TOTALS_START_ROW = 75;

    public function engine(int $id)
    {
        return $this->downloadByType($id, 'engine');
    }

    public function seat(int $id)
    {
        return $this->downloadByType($id, 'seat');
    }

    private function downloadByType(int $id, string $type)
    {
        $type = $this->normalizeExportType($type);
        $this->tuneRuntimeForLargeExport();
        $broker = $this->jobBroker();

        if ($broker->queueEnabled()) {
            $report = CcrReport::findOrFail($id);
            $this->assertReportType($report, $type);
            $templatePath = $this->templatePath($type);
            $cachedPath = $this->resolveCachedExportPath($report, $type, $templatePath);

            if (!is_file($cachedPath)) {
                // Keep queue warming for burst traffic, but never block direct user download.
                // If queue worker is down/stuck, request still proceeds via inline generation below.
                $broker->enqueue('parts', $type, $id);
            }
        }

        return $this->withReportLock($type, $id, function () use ($id, $type) {
            $report = CcrReport::findOrFail($id);
            $this->assertReportType($report, $type);
            $templatePath = $this->templatePath($type);
            $cachedPath = $this->resolveCachedExportPath($report, $type, $templatePath);

            if (!is_file($cachedPath)) {
                [$freshReport, $spreadsheet] = $this->buildWorkbook($id, $type, $templatePath);
                $cachedPath = $this->writeSpreadsheetAtomically(
                    $spreadsheet,
                    $this->resolveCachedExportPath($freshReport, $type, $templatePath)
                );
            }

            $this->cleanupStaleExports(dirname($cachedPath), $cachedPath);

            $componentName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim((string) $report->component));
            $downloadName = 'PartsLabourD_' . ucfirst($type) . ($componentName !== '' ? '-' . $componentName : '') . '.xlsx';
            return response()->download(
                $cachedPath,
                $downloadName,
                [
                    'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'private, max-age=0, must-revalidate',
                    'Pragma'        => 'public',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        });
    }

    public function warmCachedExport(int $id, string $type): string
    {
        $type = $this->normalizeExportType($type);
        $this->tuneRuntimeForLargeExport();

        return $this->withReportLock($type, $id, function () use ($id, $type) {
            $report = CcrReport::findOrFail($id);
            $this->assertReportType($report, $type);
            $templatePath = $this->templatePath($type);
            $cachedPath = $this->resolveCachedExportPath($report, $type, $templatePath);

            if (!is_file($cachedPath)) {
                [$freshReport, $spreadsheet] = $this->buildWorkbook($id, $type, $templatePath);
                $cachedPath = $this->writeSpreadsheetAtomically(
                    $spreadsheet,
                    $this->resolveCachedExportPath($freshReport, $type, $templatePath)
                );
            }

            $this->cleanupStaleExports(dirname($cachedPath), $cachedPath);
            return $cachedPath;
        });
    }

    private function buildWorkbook(int $id, string $expectedType, ?string $templatePath = null): array
    {
        $report = CcrReport::findOrFail($id);
        $this->assertReportType($report, $expectedType);

        $templatePath = $templatePath ?: $this->templatePath($expectedType);
        if (!is_file($templatePath)) {
            abort(404, 'Template Excel tidak ditemukan: ' . $templatePath);
        }

        $partsSheetName = 'PARTS & LABOUR WORKSHEET';
        $detailSheetName = 'DETAIL';

        $spreadsheet = IOFactory::load($templatePath);
        if ($expectedType === 'seat') {
            $this->replacePartsSheetFromSeatTemplate($spreadsheet, $partsSheetName);
        }
        $this->sanitizeWorkbookForExport($spreadsheet);

        $ws = $spreadsheet->getSheetByName($partsSheetName);
        $dt = $spreadsheet->getSheetByName($detailSheetName);

        if (!$ws) abort(500, 'Sheet "PARTS & LABOUR WORKSHEET" tidak ada di template.');
        if (!$dt) abort(500, 'Sheet "DETAIL" tidak ada di template.');

        $partsPayload = $this->normalizePayloadArray($report->parts_payload);
        $detailPayload = $this->normalizePayloadArray($report->detail_payload);
        $styles = isset($partsPayload['styles']) && is_array($partsPayload['styles']) ? $partsPayload['styles'] : [];
        $notes = isset($partsPayload['notes']) && is_array($partsPayload['notes']) ? $partsPayload['notes'] : [];
        $rows = $partsPayload['rows'] ?? [];
        if (!is_array($rows)) $rows = [];

        // Ambil row yang benar-benar punya isi agar data user tidak terbuang.
        $rows = array_values(array_filter($rows, function ($r) {
            if (!is_array($r)) {
                return false;
            }

            return $this->rowHasMeaningfulPartData($r);
        }));

        $partsLayout = $this->partsLayoutConfig($expectedType);
        $startRow = (int) $partsLayout['start_row'];
        $templateTotalRow = (int) $partsLayout['total_row'];
        $headerRow = (int) $partsLayout['header_row'];
        $clearStartCol = (string) $partsLayout['clear_start_col'];
        $clearEndCol = (string) $partsLayout['clear_end_col'];
        $filterEndCol = (string) $partsLayout['filter_end_col'];
        $capacity = $templateTotalRow - $startRow;
        $count = count($rows);

        $totalRow = $this->preparePartsSheetLayout(
            $ws,
            $count,
            $startRow,
            $templateTotalRow,
            (array) ($partsLayout['style_columns'] ?? [])
        );
        $dataEndRow = $count > 0 ? ($startRow + $count - 1) : $startRow;
        $this->resetPartsSheetVisibilityAndFilter($ws, $headerRow, $startRow, $dataEndRow, $totalRow, $filterEndCol);
        // Template lama punya conditional-format duplicate bawaan pada kolom Part Number.
        // Export memakai rule duplicate dari payload website agar hasilnya konsisten.
        $this->removeAllConditionalFormatting($ws);
        if ($count < $capacity) {
            $clearStart = $startRow + $count;
            $clearEnd = $templateTotalRow - 1;
            $this->clearRange($ws, $clearStartCol, $clearStart, $clearEndCol, $clearEnd);
        }

        $totalPurchaseExtended = 0;
        $totalSalesExtended = 0;

        $chunk = [];
        $chunkStartRow = $startRow;
        $chunkSize = 500;

        for ($i = 0; $i < $count; $i++) {
            $row = $rows[$i];

            $qty = $this->toInt($row['qty'] ?? null) ?? 0;
            $purchase = $this->toInt($row['purchase_price'] ?? null) ?? 0;
            $sales = $this->toInt($row['sales_price'] ?? null);
            if ($sales === null) {
                $sales = $this->rupiahFromRawPrice($row['sales_price_raw'] ?? null);
            }
            $sales = $sales ?? 0;

            $purchaseExtended = $this->toInt($row['total'] ?? null);
            if ($purchaseExtended === null) {
                $purchaseExtended = $qty * $purchase;
            }

            $salesExtended = $this->toInt($row['extended_price'] ?? null);
            if ($salesExtended === null) {
                $salesExtended = $this->rupiahFromRawPriceByQty($qty, $row['sales_price_raw'] ?? ($row['sales_price'] ?? null));
            }
            if ($salesExtended === null) {
                $salesExtended = $qty * $sales;
            }

            if ($expectedType === 'seat') {
                // Seat worksheet uses dedicated table shape:
                // A-F regular fields, G reserved note/stock column, H sales, I extended.
                $chunk[] = [
                    $i + 1,
                    $qty,
                    (string) ($row['uom'] ?? ''),
                    (string) ($row['part_number'] ?? ''),
                    (string) ($row['part_description'] ?? ''),
                    (string) ($row['part_section'] ?? ''),
                    null,
                    $sales,
                    $salesExtended,
                ];
            } else {
                $chunk[] = [
                    $i + 1,
                    $qty,
                    (string)($row['uom'] ?? ''),
                    (string)($row['part_number'] ?? ''),
                    (string)($row['part_description'] ?? ''),
                    (string)($row['part_section'] ?? ''),
                    $purchase,
                    $purchaseExtended,
                    $sales,
                    $salesExtended,
                ];
            }

            $totalPurchaseExtended += $purchaseExtended;
            $totalSalesExtended += $salesExtended;

            if (count($chunk) >= $chunkSize) {
                $ws->fromArray($chunk, null, "A{$chunkStartRow}", true);
                $chunkStartRow += count($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            $ws->fromArray($chunk, null, "A{$chunkStartRow}", true);
        }

        $partsMeta = isset($partsPayload['meta']) && is_array($partsPayload['meta']) ? $partsPayload['meta'] : [];
        $detailMeta = isset($detailPayload['meta']) && is_array($detailPayload['meta']) ? $detailPayload['meta'] : [];
        $footerTotal = $this->toInt($partsMeta['footer_total'] ?? null);
        $footerExtended = $this->toInt($partsMeta['footer_extended'] ?? null);

        if ($footerTotal !== null) {
            $totalPurchaseExtended = $footerTotal;
        }
        if ($footerExtended !== null) {
            $totalSalesExtended = $footerExtended;
        }

        $unitNo = $this->firstNonEmptyString([
            $partsMeta['no_unit'] ?? null,
            $detailMeta['equipt_no'] ?? null,
            $report->unit ?? null,
            $detailMeta['unit'] ?? null,
            $partsMeta['unit'] ?? null,
            $report->component ?? null,
        ]);

        $totalPurchaseCol = $partsLayout['total_purchase_column'] ?? null;
        $totalSalesCol = (string) ($partsLayout['total_sales_column'] ?? 'J');
        if (is_string($totalPurchaseCol) && $totalPurchaseCol !== '') {
            $ws->setCellValue("{$totalPurchaseCol}{$totalRow}", $totalPurchaseExtended);
        }
        $ws->setCellValue("{$totalSalesCol}{$totalRow}", $totalSalesExtended);

        $unitCell = $partsLayout['unit_cell'] ?? null;
        if (is_string($unitCell) && $unitCell !== '') {
            // Engine template menaruh No. Unit di D1.
            $ws->setCellValue($unitCell, $unitNo);
        }
        if ($expectedType === 'seat') {
            $ws->setCellValue("G{$startRow}", 'In Stock');
        }

        $partsColumns = $this->partsStyleColumnMap($expectedType);
        $this->applyPartsStyles($ws, $styles, $startRow, $count, $partsColumns);
        $this->applyPartsNotes($ws, $notes, $startRow, $count, $partsColumns);
        $this->applyDuplicatePartNumberHighlight($ws, $rows, $startRow);

        $this->fillDetailSheet($dt, $report, $detailPayload, $totalRow, $totalSalesCol, $partsPayload, $expectedType);

        $spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($ws));

        return [$report, $spreadsheet];
    }

    private function tuneRuntimeForLargeExport(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::EXPORT_TIMEOUT_SECONDS);
        }
        @ini_set('max_execution_time', (string) self::EXPORT_TIMEOUT_SECONDS);

        $memory = ini_get('memory_limit');
        if (is_string($memory) && $memory !== '' && $memory !== '-1') {
            @ini_set('memory_limit', '2048M');
        }
    }

    private function withReportLock(string $type, int $reportId, callable $callback)
    {
        $typeToken = $this->normalizeExportType($type);
        $lockDir = storage_path('app/tmp/export-locks');
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }

        $lockPath = $lockDir . '/' . $typeToken . '-parts-labour-' . $reportId . '.lock';
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            return $callback();
        }

        try {
            if (!@flock($handle, LOCK_EX)) {
                return $callback();
            }

            return $callback();
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    private function resolveCachedExportPath(CcrReport $report, string $type, ?string $templatePath = null): string
    {
        $typeToken = $this->normalizeExportType($type);
        $baseDir = storage_path('app/exports/' . $typeToken . '/' . $report->id);
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $templatePath = $templatePath ?: $this->templatePath($type);
        $templateMtime = is_file($templatePath) ? (string) (@filemtime($templatePath) ?: 0) : '0';
        if ($typeToken === 'seat') {
            $seatPartsTemplatePath = $this->seatPartsSheetTemplatePath(false);
            $seatPartsTemplateMtime = ($seatPartsTemplatePath && is_file($seatPartsTemplatePath))
                ? (string) (@filemtime($seatPartsTemplatePath) ?: 0)
                : '0';
            $templateMtime .= ':seatParts=' . $seatPartsTemplateMtime;
        }
        $updatedToken = optional($report->updated_at)->format('YmdHisu') ?: now()->format('YmdHisu');
        $partsRev = (string) ((int) ($report->parts_payload_rev ?? 0));
        $detailRev = (string) ((int) ($report->detail_payload_rev ?? 0));
        $partsPayloadHash = $this->payloadFingerprint($report->parts_payload);
        $detailPayloadHash = $this->payloadFingerprint($report->detail_payload);
        $fingerprint = substr(hash('sha256', implode('|', [
            self::EXPORT_SCHEMA_VERSION,
            $typeToken,
            $report->id,
            $updatedToken,
            $partsRev,
            $detailRev,
            $partsPayloadHash,
            $detailPayloadHash,
            $templateMtime,
        ])), 0, 12);

        return $baseDir . '/parts_labour_detail_' . $fingerprint . '.xlsx';
    }

    private function normalizeExportType(string $type): string
    {
        $normalized = strtolower(trim($type));
        if (!in_array($normalized, ['engine', 'seat'], true)) {
            abort(404);
        }

        return $normalized;
    }

    private function jobBroker(): CcrHeavyJobBroker
    {
        return app(CcrHeavyJobBroker::class);
    }

    private function assertReportType(CcrReport $report, string $expectedType): void
    {
        $expectedType = $this->normalizeExportType($expectedType);
        $actualType = strtolower(trim((string) ($report->type ?? '')));
        if ($actualType !== $expectedType) {
            abort(404);
        }
    }

    private function writeSpreadsheetAtomically(Spreadsheet $spreadsheet, string $targetPath): string
    {
        $tmpPath = $targetPath . '.tmp-' . Str::random(8);

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($tmpPath);

            if (is_file($targetPath)) {
                @unlink($targetPath);
            }

            if (!@rename($tmpPath, $targetPath)) {
                if (!@copy($tmpPath, $targetPath)) {
                    throw new \RuntimeException('Gagal memindahkan file export Excel.');
                }
                @unlink($tmpPath);
            }

            return $targetPath;
        } catch (Throwable $e) {
            @unlink($tmpPath);
            throw $e;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    private function cleanupStaleExports(string $dir, string $keepPath): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $now = time();
        $files = glob($dir . '/*.xlsx') ?: [];
        usort($files, static function (string $a, string $b): int {
            return (int) (@filemtime($b) <=> @filemtime($a));
        });

        foreach ($files as $index => $file) {
            if ($file === $keepPath) {
                continue;
            }

            $mtime = @filemtime($file);
            if ($mtime === false) {
                @unlink($file);
                continue;
            }

            if (($now - $mtime) >= self::STALE_EXPORT_MAX_AGE_SECONDS) {
                @unlink($file);
                continue;
            }

            if ($index >= self::MAX_CACHED_EXPORT_FILES) {
                @unlink($file);
            }
        }

        foreach (glob($dir . '/*.tmp-*') ?: [] as $tmpFile) {
            @unlink($tmpFile);
        }

    }

    private function templatePath(string $type): string
    {
        $type = $this->normalizeExportType($type);
        $candidates = [
            storage_path('app/templates/parts_labour_template.xlsx'),
            resource_path('templates/parts_labour_template.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        abort(404, "Template Excel {$type} tidak ditemukan. Cek file: " . implode(' | ', $candidates));
    }

    private function seatPartsSheetTemplatePath(bool $strict = true): ?string
    {
        $candidates = [
            storage_path('app/templates/parts_labour_seat_parts_template.xlsx'),
            resource_path('templates/parts_labour_seat_parts_template.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        if ($strict) {
            abort(404, 'Template PARTS khusus Seat tidak ditemukan. Cek file: ' . implode(' | ', $candidates));
        }

        return null;
    }

    private function replacePartsSheetFromSeatTemplate(Spreadsheet $spreadsheet, string $partsSheetName): void
    {
        $seatTemplatePath = $this->seatPartsSheetTemplatePath();
        $seatTemplateSpreadsheet = IOFactory::load($seatTemplatePath);

        try {
            $seatPartsSheet = $seatTemplateSpreadsheet->getSheetByName($partsSheetName);
            if (!$seatPartsSheet) {
                abort(500, 'Sheet "PARTS & LABOUR WORKSHEET" tidak ada di template Seat.');
            }

            $existingPartsSheet = $spreadsheet->getSheetByName($partsSheetName);
            if ($existingPartsSheet) {
                $index = $spreadsheet->getIndex($existingPartsSheet);
                $spreadsheet->removeSheetByIndex($index);
                $spreadsheet->addExternalSheet($seatPartsSheet, $index);
                $spreadsheet->getSheet($index)->setTitle($partsSheetName);
            } else {
                $spreadsheet->addExternalSheet($seatPartsSheet, 0);
                $spreadsheet->getSheet(0)->setTitle($partsSheetName);
            }
        } finally {
            $seatTemplateSpreadsheet->disconnectWorksheets();
            unset($seatTemplateSpreadsheet);
        }
    }

    private function partsLayoutConfig(string $type): array
    {
        $type = $this->normalizeExportType($type);

        if ($type === 'seat') {
            return [
                'start_row' => 5,
                'total_row' => 35,
                'header_row' => 3,
                'clear_start_col' => 'A',
                'clear_end_col' => 'I',
                'filter_end_col' => 'I',
                'style_columns' => range('A', 'I'),
                'total_purchase_column' => null,
                'total_sales_column' => 'I',
                'unit_cell' => 'D1',
            ];
        }

        return [
            'start_row' => self::TEMPLATE_START_ROW,
            'total_row' => self::TEMPLATE_TOTAL_ROW,
            'header_row' => 2,
            'clear_start_col' => 'A',
            'clear_end_col' => 'J',
            'filter_end_col' => 'J',
            'style_columns' => range('A', 'J'),
            'total_purchase_column' => 'H',
            'total_sales_column' => 'J',
            'unit_cell' => 'D1',
        ];
    }

    private function partsStyleColumnMap(string $type): array
    {
        $type = $this->normalizeExportType($type);

        if ($type === 'seat') {
            // Seat table has no purchase/total display columns.
            // Keep backward compatibility for old payload indexes:
            // 8/9 -> H/I (sales/extended), 6/7 also remapped to H/I.
            return ['A', 'B', 'C', 'D', 'E', 'F', 'H', 'I', 'H', 'I'];
        }

        return ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
    }

    private function normalizePayloadArray($payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return is_array($payload) ? $payload : [];
    }

    private function sanitizeWorkbookForExport(Spreadsheet $spreadsheet): void
    {
        $this->sanitizeDefinedNames($spreadsheet);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (strtoupper((string) $sheet->getTitle()) === 'DETAIL') {
                // Hardening: template can carry accidental far-right cells (e.g. XFD*)
                // which drastically increases memory use in row/merge operations.
                $this->pruneOutlierCells($sheet, 256);
            }

            foreach ($sheet->getDataValidationCollection() as $coordinate => $validation) {
                $formula1 = (string) $validation->getFormula1();
                $formula2 = (string) $validation->getFormula2();

                // Hapus validation rusak dari template lama agar file tidak memicu repair di Excel.
                if ($this->isBrokenReferenceFormula($formula1) || $this->isBrokenReferenceFormula($formula2)) {
                    $sheet->setDataValidation($coordinate, null);
                }
            }
        }
    }

    private function pruneOutlierCells(Worksheet $sheet, int $maxColumnIndex): void
    {
        $toDelete = [];
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            if (preg_match('/^([A-Z]+)\d+$/', $coordinate, $matches) !== 1) {
                continue;
            }

            $columnIndex = Coordinate::columnIndexFromString($matches[1]);
            if ($columnIndex > $maxColumnIndex) {
                $toDelete[] = $coordinate;
            }
        }

        if (empty($toDelete)) {
            return;
        }

        foreach ($toDelete as $coordinate) {
            $sheet->getCellCollection()->delete($coordinate);
        }
        $sheet->garbageCollect();
    }

    private function sanitizeDefinedNames(Spreadsheet $spreadsheet): void
    {
        foreach ($spreadsheet->getDefinedNames() as $definedName) {
            $nameUpper = strtoupper($definedName->getName());
            $value = (string) $definedName->getValue();
            $isFilterDatabase = $nameUpper === '_XLNM._FILTERDATABASE';
            $isSystemName = $nameUpper === '_XLNM.PRINT_AREA';
            $mustDrop = $this->isBrokenReferenceFormula($value);

            // _FilterDatabase sering tertinggal dari file lama saat range berubah dan dapat memicu
            // workbook repair di Excel. Selalu drop dan biarkan PhpSpreadsheet membuat ulang.
            if ($isFilterDatabase) {
                $scope = $definedName->getLocalOnly()
                    ? ($definedName->getScope() ?? $definedName->getWorksheet())
                    : null;
                $spreadsheet->removeDefinedName($definedName->getName(), $scope);
                continue;
            }

            // Keep only print-area names that are still valid. Others from legacy template are removed.
            if ($isSystemName && !$mustDrop) {
                continue;
            }

            $scope = $definedName->getLocalOnly()
                ? ($definedName->getScope() ?? $definedName->getWorksheet())
                : null;
            $spreadsheet->removeDefinedName($definedName->getName(), $scope);
        }
    }

    private function isBrokenReferenceFormula(?string $formula): bool
    {
        $formula = strtoupper(trim((string) $formula));
        if ($formula === '') {
            return false;
        }

        if (str_contains($formula, '#REF!')) {
            return true;
        }

        // Deteksi referensi workbook eksternal: '[Book]Sheet'!A1
        if (preg_match('/\[[^\]]+\][^!]*!/', $formula) === 1) {
            return true;
        }

        return false;
    }

    private function resetPartsSheetVisibilityAndFilter(
        Worksheet $ws,
        int $headerRow,
        int $startRow,
        int $dataEndRow,
        int $totalRow,
        string $filterEndCol = 'J'
    ): void
    {
        $safeDataEndRow = max($startRow, $dataEndRow);
        for ($row = $startRow; $row <= $safeDataEndRow; $row++) {
            $dimension = $ws->getRowDimension($row);
            $dimension->setVisible(true);
            $dimension->setZeroHeight(false);
        }
        // Selalu tampilkan row subtotal, tetapi biarkan tail kosong (antara data-end dan subtotal)
        // tetap tersembunyi agar sheet menyesuaikan row terakhir yang punya data.
        $subtotalDimension = $ws->getRowDimension($totalRow);
        $subtotalDimension->setVisible(true);
        $subtotalDimension->setZeroHeight(false);

        // Template lama pernah tersimpan dengan filter aktif, sehingga row ter-hide di hasil export.
        // Reset total supaya file selalu tampil penuh saat dibuka user.
        $ws->removeAutoFilter();
        // Batasi autofilter hanya sampai row data terakhir.
        // Jika subtotal row ikut range filter, Excel akan memaksa semua row di range jadi visible.
        $ws->setAutoFilter("A{$headerRow}:{$filterEndCol}{$safeDataEndRow}");
    }

    private function rowHasMeaningfulPartData(array $row): bool
    {
        foreach (['part_number', 'part_description', 'part_section'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return true;
            }
        }

        foreach (['qty', 'purchase_price', 'sales_price', 'total', 'extended_price'] as $key) {
            $parsed = $this->toInt($row[$key] ?? null);
            if ($parsed !== null && $parsed !== 0) {
                return true;
            }
        }

        foreach (['purchase_price_raw', 'sales_price_raw'] as $key) {
            $parsed = $this->rupiahFromRawPrice($row[$key] ?? null);
            if ($parsed !== null && $parsed !== 0) {
                return true;
            }
        }

        return false;
    }

    private function isEmptyDetailMainRow(array $row): bool
    {
        foreach ([
            'section',
            'code',
            'component_code',
            'component_desc',
            'component_description',
            'component',
            'work_desc',
            'work_description',
            'description',
            'work_order',
            'work_order_no',
            'work_order_number',
        ] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return false;
            }
        }

        foreach ([
            'hours',
            'hour',
            'labour_charge',
            'labour_charge_rp',
            'labour',
            'labour_total',
            'parts_charge',
            'parts_charge_rp',
            'parts',
            'parts_total',
        ] as $key) {
            $parsed = $this->toInt($row[$key] ?? null);
            if ($parsed !== null && $parsed !== 0) {
                return false;
            }
        }

        // `seg`/item number saja tidak dianggap data bermakna.
        return true;
    }

    private function isEmptyDetailPaintingRow(array $row): bool
    {
        return trim((string) ($row['item'] ?? '')) === ''
            && trim((string) ($row['qty'] ?? '')) === ''
            && trim((string) ($row['uom'] ?? '')) === ''
            && trim((string) ($row['unit_price'] ?? '')) === ''
            && trim((string) ($row['total'] ?? '')) === '';
    }

    private function isEmptyDetailExternalRow(array $row): bool
    {
        if (trim((string) ($row['service'] ?? '')) !== '') {
            return false;
        }
        if (trim((string) ($row['remark'] ?? '')) !== '') {
            return false;
        }

        $amount = $this->firstInt([
            $row['amount'] ?? null,
            $row['charge'] ?? null,
            $row['price'] ?? null,
        ], null);

        return $amount === null || $amount === 0;
    }

    private function fillDetailSheet(
        Worksheet $dt,
        CcrReport $report,
        array $detailPayload,
        int $partsTotalRow,
        string $partsTotalColumn,
        array $partsPayload = [],
        string $reportType = 'engine'
    ): void
    {
        $meta = isset($detailPayload['meta']) && is_array($detailPayload['meta']) ? $detailPayload['meta'] : [];
        $partsMeta = isset($partsPayload['meta']) && is_array($partsPayload['meta']) ? $partsPayload['meta'] : [];
        $misc = isset($detailPayload['misc']) && is_array($detailPayload['misc']) ? $detailPayload['misc'] : [];
        $totals = isset($detailPayload['totals']) && is_array($detailPayload['totals']) ? $detailPayload['totals'] : [];
        $mainRows = isset($detailPayload['main_rows']) && is_array($detailPayload['main_rows']) ? $detailPayload['main_rows'] : [];
        $paintingRows = isset($detailPayload['painting_rows']) && is_array($detailPayload['painting_rows']) ? $detailPayload['painting_rows'] : [];
        $externalRows = isset($detailPayload['external_rows']) && is_array($detailPayload['external_rows']) ? $detailPayload['external_rows'] : [];

        $customer = $this->firstNonEmptyString([
            $meta['customer'] ?? null,
            $report->customer ?? null,
        ]);
        $quoEst = $this->firstNonEmptyString([$meta['quo_est_number'] ?? null]);
        $woNumber = $this->firstNonEmptyString([
            $meta['wo_number'] ?? null,
        ]);
        $model = $this->firstNonEmptyString([
            $meta['model'] ?? null,
        ]);
        $sn = $this->firstNonEmptyString([
            $meta['sn'] ?? null,
        ]);
        $equipNo = $this->firstNonEmptyString([
            $meta['equipt_no'] ?? null,
        ]);
        $attention = $this->firstNonEmptyString([$meta['attention'] ?? null]);
        $inspectionDate = $this->normalizeDateString($this->firstNonEmptyString([$meta['date'] ?? null]));
        if ($inspectionDate === '' && $report->inspection_date) {
            $inspectionDate = $report->inspection_date->format('d/m/Y');
        }
        $smu = $this->firstNonEmptyString([
            $meta['smu'] ?? null,
        ]);
        $jobOutline = $this->firstNonEmptyString([
            $meta['job_outline'] ?? null,
        ]);

        $dt->setCellValue('A2', $customer);
        $dt->setCellValue('G2', $quoEst);
        $dt->setCellValue('I2', $woNumber);
        $dt->setCellValue('J2', $model);
        $dt->setCellValue('K2', $sn);
        $dt->setCellValue('L2', $equipNo);
        $dt->setCellValue('A4', $attention);
        $dt->setCellValue('J4', $inspectionDate);
        $dt->setCellValue('L4', $smu);
        $dt->setCellValue('B6', $jobOutline);

        $mainRows = $this->trimTrailingEmptyRows($mainRows, fn (array $row): bool => $this->isEmptyDetailMainRow($row));
        $paintingRows = $this->trimTrailingEmptyRows($paintingRows, fn (array $row): bool => $this->isEmptyDetailPaintingRow($row));
        $externalRows = $this->trimTrailingEmptyRows($externalRows, fn (array $row): bool => $this->isEmptyDetailExternalRow($row));

        $isSeatReport = strtolower(trim($reportType)) === 'seat';
        $minimumVisibleRows = [
            'main' => $isSeatReport ? 2 : 0,
            'painting' => 0,
            'external' => $isSeatReport ? 2 : 0,
        ];
        $layout = $this->prepareDetailSheetLayout(
            $dt,
            count($mainRows),
            count($paintingRows),
            count($externalRows),
            $minimumVisibleRows
        );
        $mainStart = (int) $layout['main_start'];
        $mainCapacity = (int) $layout['main_capacity'];
        $mainEnd = (int) $layout['main_end'];
        $paintingStart = (int) $layout['painting_start'];
        $paintingCapacity = (int) $layout['painting_capacity'];
        $paintingEnd = (int) $layout['painting_end'];
        $externalStart = (int) $layout['external_start'];
        $externalCapacity = (int) $layout['external_capacity'];
        $externalEnd = (int) $layout['external_end'];
        $subtotalRow = (int) $layout['subtotal_row'];
        $consumableRow = (int) $layout['consumable_row'];
        $paintingTotalRow = (int) $layout['painting_total_row'];
        $externalTotalRow = (int) $layout['external_total_row'];
        $totalLabourRow = (int) $layout['total_labour_row'];
        $totalPartsRow = $totalLabourRow + 1;
        $totalMiscRow = $totalLabourRow + 2;
        $totalBeforeDiscRow = $totalLabourRow + 3;
        $discountRow = $totalLabourRow + 4;
        $totalBeforeTaxRow = $totalLabourRow + 5;
        $salesTaxRow = $totalLabourRow + 6;
        $totalRepairChargeRow = $totalLabourRow + 7;
        $this->applyDetailRuntimeMerges($dt, $paintingStart, $paintingEnd, $externalStart, $externalEnd, $totalLabourRow);

        $mainSegCode = [];
        $mainDesc = [];
        $mainRight = [];
        foreach (array_slice($mainRows, 0, $mainCapacity) as $row) {
            if (!is_array($row)) {
                $mainSegCode[] = [null, null];
                $mainDesc[] = [null, null];
                $mainRight[] = [null, null, null, null];
                continue;
            }

            $mainSegCode[] = [
                $this->firstNonEmptyString([
                    $row['seg'] ?? null,
                    $row['section'] ?? null,
                ]),
                $this->firstNonEmptyString([
                    $row['code'] ?? null,
                    $row['component_code'] ?? null,
                ]),
            ];

            $mainDesc[] = [
                $this->firstNonEmptyString([
                    $row['component_desc'] ?? null,
                    $row['component_description'] ?? null,
                    $row['component'] ?? null,
                ]),
                $this->firstNonEmptyString([
                    $row['work_desc'] ?? null,
                    $row['work_description'] ?? null,
                    $row['description'] ?? null,
                ]),
            ];

            $mainRight[] = [
                $this->firstNonEmptyString([
                    $row['work_order'] ?? null,
                    $row['work_order_no'] ?? null,
                    $row['work_order_number'] ?? null,
                ]),
                $this->firstNonEmptyString([
                    $row['hours'] ?? null,
                    $row['hour'] ?? null,
                ]),
                $this->firstInt([
                    $row['labour_charge'] ?? null,
                    $row['labour_charge_rp'] ?? null,
                    $row['labour'] ?? null,
                    $row['labour_total'] ?? null,
                ], null),
                $this->firstInt([
                    $row['parts_charge'] ?? null,
                    $row['parts_charge_rp'] ?? null,
                    $row['parts'] ?? null,
                    $row['parts_total'] ?? null,
                ], null),
            ];
        }

        if (!empty($mainSegCode)) {
            $dt->fromArray($mainSegCode, null, "A{$mainStart}", true);
            $dt->fromArray($mainDesc, null, "E{$mainStart}", true);
            $dt->fromArray($mainRight, null, "I{$mainStart}", true);

            // Template DETAIL stores K font as white in data rows, which makes manual
            // labour/parts charges look empty on export. Force visible black text.
            $mainWritten = count($mainSegCode);
            $mainWrittenEnd = $mainStart + $mainWritten - 1;
            $dt->getStyle("K{$mainStart}:L{$mainWrittenEnd}")
                ->getFont()
                ->getColor()
                ->setARGB('FF000000');
        }

        $paintingLeft = [];
        $paintingRight = [];
        foreach (array_slice($paintingRows, 0, $paintingCapacity) as $row) {
            if (!is_array($row)) {
                $paintingLeft[] = [null];
                $paintingRight[] = [null, null, null, null];
                continue;
            }

            $qty = $this->toInt($row['qty'] ?? null);
            $unitPrice = $this->toInt($row['unit_price'] ?? null);
            $rowTotal = $this->toInt($row['total'] ?? null);
            if ($rowTotal === null && $qty !== null && $unitPrice !== null) {
                $rowTotal = $qty * $unitPrice;
            }

            $paintingLeft[] = [trim((string) ($row['item'] ?? ''))];
            $paintingRight[] = [
                $qty,
                trim((string) ($row['uom'] ?? '')),
                $unitPrice,
                $rowTotal,
            ];
        }

        if (!empty($paintingLeft)) {
            $dt->fromArray($paintingLeft, null, "D{$paintingStart}", true);
            $dt->fromArray($paintingRight, null, "F{$paintingStart}", true);
        }

        $externalLeft = [];
        $externalRight = [];
        foreach (array_slice($externalRows, 0, $externalCapacity) as $row) {
            if (!is_array($row)) {
                $externalLeft[] = [null];
                $externalRight[] = [null, null];
                continue;
            }

            $externalLeft[] = [trim((string) ($row['service'] ?? ''))];
            $externalRight[] = [
                $this->toInt($row['amount'] ?? null),
                trim((string) ($row['remark'] ?? '')),
            ];
        }

        if (!empty($externalLeft)) {
            $dt->fromArray($externalLeft, null, "F{$externalStart}", true);
            $dt->fromArray($externalRight, null, "I{$externalStart}", true);
        }

        $labourSubtotal = $this->firstInt([
            $totals['total_labour'] ?? null,
            $meta['sub_total_labour'] ?? null,
        ], 0);
        $hoursSubtotalText = $this->firstNonEmptyString([
            $meta['sub_total_hours'] ?? null,
        ]);
        if ($hoursSubtotalText === '') {
            $hoursSubtotal = 0.0;
            foreach (array_slice($mainRows, 0, $mainCapacity) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $hoursSubtotal += $this->toHoursFloat($row['hours'] ?? null) ?? 0.0;
            }
            $hoursSubtotalText = $this->formatHours($hoursSubtotal);
        }
        $partsSubtotal = $this->firstInt([
            $totals['total_parts'] ?? null,
            $meta['sub_total_parts'] ?? null,
            $partsMeta['footer_extended'] ?? null,
        ], null);

        $paintingTotal = $this->firstInt([
            $misc['painting_total'] ?? null,
        ], 0);
        if ($paintingTotal === 0) {
            $paintingTotal = 0;
            foreach (array_slice($paintingRows, 0, $paintingCapacity) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $paintingTotal += (int) ($this->toInt($row['total'] ?? null) ?? 0);
            }
        }

        $consumableFraction = $this->toPercentFraction($misc['consumable_percent'] ?? 0, 0.0);
        $discountFraction = $this->toPercentFraction($totals['discount_percent'] ?? 0, 0.0);
        $taxPercentSource = $totals['tax_percent'] ?? ($totals['sales_tax_percent'] ?? 11);
        $taxFraction = $this->toPercentFraction($taxPercentSource, 0.11);
        $consumableCharge = $this->firstInt([
            $misc['consumable_charge'] ?? null,
        ], null);
        $externalTotal = $this->firstInt([
            $misc['external_total'] ?? null,
        ], null);
        $totalMisc = $this->firstInt([
            $totals['total_misc'] ?? null,
        ], null);
        $totalBeforeDisc = $this->firstInt([
            $totals['total_before_disc'] ?? null,
        ], null);
        $discountAmount = $this->firstInt([
            $totals['discount_amount'] ?? null,
        ], null);
        $totalBeforeTax = $this->firstInt([
            $totals['total_before_tax'] ?? null,
        ], null);
        $salesTax = $this->firstInt([
            $totals['sales_tax'] ?? null,
        ], null);
        $totalRepairCharge = $this->firstInt([
            $totals['total_repair_charge'] ?? null,
        ], null);

        $dt->setCellValue("J{$subtotalRow}", $hoursSubtotalText);
        $dt->setCellValue("K{$subtotalRow}", $labourSubtotal);
        $dt->setCellValue(
            "L{$subtotalRow}",
            $partsSubtotal !== null
                ? $partsSubtotal
                : "='PARTS & LABOUR WORKSHEET'!{$partsTotalColumn}{$partsTotalRow}"
        );
        $dt->setCellValue("F{$consumableRow}", $consumableFraction);
        $dt->setCellValue("L{$consumableRow}", $consumableCharge ?? "=F{$consumableRow}*K{$subtotalRow}");
        $dt->setCellValue("L{$paintingTotalRow}", $paintingTotal);

        $externalSumEndRow = $externalStart;
        if (!empty($externalRows)) {
            $externalSumEndRow = $externalStart + min(count($externalRows), $externalCapacity) - 1;
        }
        $dt->setCellValue("L{$externalTotalRow}", $externalTotal ?? "=SUM(I{$externalStart}:I{$externalSumEndRow})");

        $miscSumStartRow = $consumableRow;
        $miscSumEndRow = max($miscSumStartRow, $totalLabourRow - 2);
        $dt->setCellValue("L{$totalMiscRow}", $totalMisc ?? "=SUM(L{$miscSumStartRow}:L{$miscSumEndRow})");
        $dt->setCellValue("L{$totalBeforeDiscRow}", $totalBeforeDisc ?? "=SUM(L{$totalLabourRow}:L{$totalMiscRow})");
        $dt->setCellValue("K{$discountRow}", $discountFraction);
        $dt->setCellValue("L{$discountRow}", $discountAmount ?? "=K{$discountRow}*L{$totalBeforeDiscRow}");
        $dt->setCellValue("L{$totalBeforeTaxRow}", $totalBeforeTax ?? "=L{$totalBeforeDiscRow}-L{$discountRow}");
        $dt->setCellValue("K{$salesTaxRow}", $taxFraction);
        $dt->setCellValue("L{$salesTaxRow}", $salesTax ?? "=L{$totalBeforeTaxRow}*K{$salesTaxRow}");
        $dt->setCellValue("L{$totalRepairChargeRow}", $totalRepairCharge ?? "=SUM(L{$totalBeforeTaxRow}:L{$salesTaxRow})");

        // Keep percentage cells consistent with website/UI percent semantics.
        $dt->getStyle("F{$consumableRow}")->getNumberFormat()->setFormatCode('0%');
        $dt->getStyle("K{$discountRow}")->getNumberFormat()->setFormatCode('0%');
        $dt->getStyle("K{$salesTaxRow}")->getNumberFormat()->setFormatCode('0%');
    }

    private function prepareDetailSheetLayout(
        Worksheet $dt,
        int $mainCount,
        int $paintingCount,
        int $externalCount,
        array $minimumVisibleRows = []
    ): array
    {
        $minMainVisible = max(0, (int) ($minimumVisibleRows['main'] ?? 0));
        $minPaintingVisible = max(0, (int) ($minimumVisibleRows['painting'] ?? 0));
        $minExternalVisible = max(0, (int) ($minimumVisibleRows['external'] ?? 0));

        $mainBaseCapacity = self::DETAIL_MAIN_END_ROW - self::DETAIL_MAIN_START_ROW + 1;
        $paintingBaseCapacity = self::DETAIL_PAINT_END_ROW - self::DETAIL_PAINT_START_ROW + 1;
        $externalBaseCapacity = self::DETAIL_EXTERNAL_END_ROW - self::DETAIL_EXTERNAL_START_ROW + 1;

        $mainOverflow = max(0, $mainCount - $mainBaseCapacity);
        if ($mainOverflow > 0) {
            // Insert sebelum row penutup main section agar border tebal tetap hanya di baris terakhir data.
            $this->insertDetailRows(
                $dt,
                self::DETAIL_MAIN_END_ROW,
                $mainOverflow,
                self::DETAIL_MAIN_STYLE_ROW,
                'A',
                'L'
            );
        }
        $deltaMain = $mainOverflow;

        $paintingOverflow = max(0, $paintingCount - $paintingBaseCapacity);
        if ($paintingOverflow > 0) {
            // Template punya row penutup di bawah painting rows.
            // Sisipkan sebelum row penutup supaya area painting tetap rapih.
            $paintingTemplateEnd = self::DETAIL_PAINT_END_ROW + $deltaMain;
            $paintingInsertAt = $paintingTemplateEnd + 1;
            $paintingStyleRow = self::DETAIL_PAINT_STYLE_ROW + $deltaMain;
            $this->insertDetailRows(
                $dt,
                $paintingInsertAt,
                $paintingOverflow,
                $paintingStyleRow,
                'A',
                'L'
            );
        }
        $deltaPainting = $paintingOverflow;

        $externalOverflow = max(0, $externalCount - $externalBaseCapacity);
        if ($externalOverflow > 0) {
            // Baris terakhir external di template memakai border bawah penutup.
            // Untuk overflow, insert sebelum baris itu dan pakai style row data reguler.
            $externalTemplateEnd = self::DETAIL_EXTERNAL_END_ROW + $deltaMain + $deltaPainting;
            $externalInsertAt = $externalTemplateEnd;
            $externalStyleRow = self::DETAIL_EXTERNAL_STYLE_ROW + $deltaMain + $deltaPainting;
            $this->insertDetailRows(
                $dt,
                $externalInsertAt,
                $externalOverflow,
                $externalStyleRow,
                'A',
                'L'
            );
        }
        $deltaExternal = $externalOverflow;

        $mainStart = self::DETAIL_MAIN_START_ROW;
        $mainCapacity = $mainBaseCapacity + $deltaMain;
        $mainEnd = $mainStart + $mainCapacity - 1;
        $mainVisibleRows = max(
            min($mainCount, $mainCapacity),
            min($minMainVisible, $mainCapacity)
        );
        $mainVisibleEnd = $mainVisibleRows > 0
            ? ($mainStart + $mainVisibleRows - 1)
            : ($mainStart - 1);
        $this->setDetailRowsVisibility($dt, $mainStart, $mainEnd, true);
        if ($mainVisibleEnd < $mainEnd) {
            $this->setDetailRowsVisibility($dt, $mainVisibleEnd + 1, $mainEnd, false);
        }

        $paintingStart = self::DETAIL_PAINT_START_ROW + $deltaMain;
        $paintingCapacity = $paintingBaseCapacity + $deltaPainting;
        $paintingEnd = $paintingStart + $paintingCapacity - 1;
        $paintingVisibleRows = max(
            min($paintingCount, $paintingCapacity),
            min($minPaintingVisible, $paintingCapacity)
        );
        $paintingVisibleEnd = $paintingVisibleRows > 0
            ? ($paintingStart + $paintingVisibleRows - 1)
            : ($paintingStart - 1);
        $this->setDetailRowsVisibility($dt, $paintingStart, $paintingEnd, true);
        if ($paintingVisibleEnd < $paintingEnd) {
            $this->setDetailRowsVisibility($dt, $paintingVisibleEnd + 1, $paintingEnd, false);
        }

        $externalStart = self::DETAIL_EXTERNAL_START_ROW + $deltaMain + $deltaPainting;
        $externalCapacity = $externalBaseCapacity + $deltaExternal;
        $externalEnd = $externalStart + $externalCapacity - 1;
        $externalVisibleRows = max(
            min($externalCount, $externalCapacity),
            min($minExternalVisible, $externalCapacity)
        );
        $externalVisibleEnd = $externalVisibleRows > 0
            ? ($externalStart + $externalVisibleRows - 1)
            : ($externalStart - 1);
        $this->setDetailRowsVisibility($dt, $externalStart, $externalEnd, true);
        if ($externalVisibleEnd < $externalEnd) {
            $this->setDetailRowsVisibility($dt, $externalVisibleEnd + 1, $externalEnd, false);
        }

        return [
            'main_start' => $mainStart,
            'main_capacity' => $mainCapacity,
            'main_end' => $mainEnd,
            'painting_start' => $paintingStart,
            'painting_capacity' => $paintingCapacity,
            'painting_end' => $paintingEnd,
            'external_start' => $externalStart,
            'external_capacity' => $externalCapacity,
            'external_end' => $externalEnd,
            'subtotal_row' => self::DETAIL_SUBTOTAL_ROW + $deltaMain,
            'consumable_row' => self::DETAIL_CONSUMABLE_ROW + $deltaMain,
            'painting_total_row' => self::DETAIL_PAINT_START_ROW + $deltaMain,
            'external_total_row' => self::DETAIL_EXTERNAL_START_ROW + $deltaMain + $deltaPainting,
            'total_labour_row' => self::DETAIL_TOTALS_START_ROW + $deltaMain + $deltaPainting + $deltaExternal,
        ];
    }

    private function insertDetailRows(
        Worksheet $sheet,
        int $insertAt,
        int $count,
        int $styleSourceRow,
        string $startCol,
        string $endCol
    ): void {
        if ($count <= 0) {
            return;
        }

        $sheet->insertNewRowBefore($insertAt, $count);
        $startIndex = Coordinate::columnIndexFromString($startCol);
        $endIndex = Coordinate::columnIndexFromString($endCol);
        $insertEnd = $insertAt + $count - 1;

        // Ensure inserted cells exist so style assignment sticks when values are written later.
        for ($row = $insertAt; $row <= $insertEnd; $row++) {
            for ($colIndex = $startIndex; $colIndex <= $endIndex; $colIndex++) {
                $col = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->getCell("{$col}{$row}");
            }
        }

        for ($colIndex = $startIndex; $colIndex <= $endIndex; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            // Copy per-column style so fills/number formats/borders remain identical to template row.
            $targetRange = "{$col}{$insertAt}:{$col}{$insertEnd}";
            if ($col === 'K') {
                // duplicateStyle on K can explode memory on large DETAIL overflows in this template.
                // applyFromArray keeps the visual style without triggering that path.
                $sheet->getStyle($targetRange)->applyFromArray(
                    $sheet->getStyle("{$col}{$styleSourceRow}")->exportArray()
                );
                continue;
            }

            $sheet->duplicateStyle(
                $sheet->getStyle("{$col}{$styleSourceRow}"),
                $targetRange
            );
        }

        $rowHeight = $sheet->getRowDimension($styleSourceRow)->getRowHeight();
        for ($row = $insertAt; $row < ($insertAt + $count); $row++) {
            if ($rowHeight > 0) {
                $sheet->getRowDimension($row)->setRowHeight($rowHeight);
            }
            $sheet->getRowDimension($row)->setVisible(true);
            $sheet->getRowDimension($row)->setZeroHeight(false);
        }
    }

    private function setDetailRowsVisibility(Worksheet $sheet, int $startRow, int $endRow, bool $visible): void
    {
        if ($endRow < $startRow) {
            return;
        }

        for ($row = $startRow; $row <= $endRow; $row++) {
            $dimension = $sheet->getRowDimension($row);
            $dimension->setVisible($visible);
            $dimension->setZeroHeight(!$visible);
        }
    }

    private function applyDetailRuntimeMerges(
        Worksheet $sheet,
        int $paintingStart,
        int $paintingEnd,
        int $externalStart,
        int $externalEnd,
        int $totalLabourRow
    ): void {
        for ($r = $paintingStart; $r <= $paintingEnd; $r++) {
            $this->safeMergeCells($sheet, "D{$r}:E{$r}");
            $this->safeMergeCells($sheet, "J{$r}:K{$r}");
        }

        for ($r = $externalStart; $r <= $externalEnd; $r++) {
            $this->safeMergeCells($sheet, "A{$r}:E{$r}");
            $this->safeMergeCells($sheet, "F{$r}:H{$r}");
            $this->safeMergeCells($sheet, "J{$r}:K{$r}");
        }

        $discountRow = $totalLabourRow + 4;
        $salesTaxRow = $totalLabourRow + 6;
        for ($r = $totalLabourRow; $r <= ($totalLabourRow + 7); $r++) {
            $this->safeMergeCells($sheet, "A{$r}:I{$r}");
            if ($r !== $discountRow && $r !== $salesTaxRow) {
                $this->safeMergeCells($sheet, "J{$r}:K{$r}");
            }
        }
    }

    private function safeMergeCells(Worksheet $sheet, string $range): void
    {
        try {
            // MERGE_CELL_CONTENT_HIDE avoids PhpSpreadsheet clearing-cell path that can
            // explode memory on dynamic DETAIL row insertion in this template.
            $sheet->mergeCells($range, Worksheet::MERGE_CELL_CONTENT_HIDE);
        } catch (Throwable $e) {
            // Ignore overlaps that already exist in the template.
        }
    }

    private function preparePartsSheetLayout(
        Worksheet $ws,
        int $count,
        int $startRow,
        int $templateTotalRow,
        array $columns
    ): int
    {
        $capacity = max(0, $templateTotalRow - $startRow);
        $lastTemplateDataRow = $templateTotalRow - 1;
        $dataTemplateStyleRow = $lastTemplateDataRow >= $startRow ? $lastTemplateDataRow : $startRow;
        if (empty($columns)) {
            $columns = range('A', 'J');
        }
        $dataStyleByColumn = [];
        $totalStyleByColumn = [];
        foreach ($columns as $col) {
            // Untuk row dinamis (> kapasitas template), pakai style row data terakhir template
            // agar border/number format sama seperti row item biasa (bukan row subtotal).
            $dataStyleByColumn[$col] = $ws->getStyle("{$col}{$dataTemplateStyleRow}")->exportArray();
            $totalStyleByColumn[$col] = $ws->getStyle("{$col}{$templateTotalRow}")->exportArray();
        }
        $dataRowHeight = $ws->getRowDimension($dataTemplateStyleRow)->getRowHeight();
        $totalRowHeight = $ws->getRowDimension($templateTotalRow)->getRowHeight();

        for ($r = $startRow; $r <= $lastTemplateDataRow; $r++) {
            $ws->getRowDimension($r)->setVisible(true);
        }

        if ($count <= $capacity) {
            // Sembunyikan sisa row template agar area item tetap ringkas tanpa operasi removeRow massal.
            $visibleRows = max($count, 1);
            $hideStart = $startRow + $visibleRows;
            for ($r = $hideStart; $r <= $lastTemplateDataRow; $r++) {
                $ws->getRowDimension($r)->setVisible(false);
                // Persist hidden row state in exported XLSX.
                $ws->getRowDimension($r)->setZeroHeight(true);
            }
            return $templateTotalRow;
        }

        $totalRow = $startRow + $count;
        if ($totalRow > $templateTotalRow) {
            $dataFillEnd = $totalRow - 1;
            foreach ($columns as $col) {
                // duplicateStyle menjaga border per-cell (all borders) tetap konsisten
                // pada seluruh baris tambahan. applyFromArray di range lebar bisa
                // menghilangkan border internal antar row.
                $ws->duplicateStyle(
                    $ws->getStyle("{$col}{$dataTemplateStyleRow}"),
                    "{$col}{$templateTotalRow}:{$col}{$dataFillEnd}"
                );
            }

            for ($r = $templateTotalRow; $r <= $dataFillEnd; $r++) {
                if ($dataRowHeight > 0) {
                    $ws->getRowDimension($r)->setRowHeight($dataRowHeight);
                }
                $ws->getRowDimension($r)->setVisible(true);
            }
        }

        foreach ($columns as $col) {
            $ws->getStyle("{$col}{$totalRow}")->applyFromArray($totalStyleByColumn[$col]);
        }
        if ($totalRowHeight > 0) {
            $ws->getRowDimension($totalRow)->setRowHeight($totalRowHeight);
        }
        $ws->getRowDimension($totalRow)->setVisible(true);

        return $totalRow;
    }

    private function payloadFingerprint($payload): string
    {
        $normalized = $this->normalizePayloadArray($payload);
        $normalized = $this->sortRecursive($normalized);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $encoded = '';
        }

        return substr(hash('sha256', $encoded), 0, 16);
    }

    private function sortRecursive($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isAssoc($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }

        return $value;
    }

    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function applyPartsStyles(
        Worksheet $ws,
        array $styles,
        int $startRow,
        int $rowCount,
        ?array $columns = null
    ): void
    {
        if ($rowCount <= 0 || empty($styles)) {
            return;
        }

        $columns = (is_array($columns) && !empty($columns))
            ? array_values($columns)
            : ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $maxColIndex = count($columns) - 1;
        foreach ($styles as $key => $cellStyle) {
            if (!is_array($cellStyle)) {
                continue;
            }

            if (!preg_match('/^\s*(\d+)\s*:\s*(\d+)\s*$/', (string) $key, $matches)) {
                continue;
            }

            $rowIndex = (int) $matches[1];
            $colIndex = (int) $matches[2];

            if ($rowIndex < 0 || $rowIndex >= $rowCount || $colIndex < 0 || $colIndex > $maxColIndex) {
                continue;
            }

            $styleArray = [];

            $font = [];
            if (!empty($cellStyle['bold'])) {
                $font['bold'] = true;
            }
            if (!empty($cellStyle['italic'])) {
                $font['italic'] = true;
            }
            if (!empty($cellStyle['underline'])) {
                $font['underline'] = Font::UNDERLINE_SINGLE;
            }
            $fontColor = $this->toArgbColor($cellStyle['color'] ?? null);
            if ($fontColor !== null) {
                $font['color'] = ['argb' => $fontColor];
            }
            if (!empty($font)) {
                $styleArray['font'] = $font;
            }

            $fillColor = $this->toArgbColor($cellStyle['bg'] ?? null);
            if ($fillColor !== null) {
                $styleArray['fill'] = [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => $fillColor],
                    'endColor' => ['argb' => $fillColor],
                ];
            }

            $alignRaw = strtolower(trim((string) ($cellStyle['align'] ?? '')));
            if ($alignRaw !== '') {
                $horizontal = null;
                if ($alignRaw === 'left') {
                    $horizontal = Alignment::HORIZONTAL_LEFT;
                } elseif ($alignRaw === 'center') {
                    $horizontal = Alignment::HORIZONTAL_CENTER;
                } elseif ($alignRaw === 'right') {
                    $horizontal = Alignment::HORIZONTAL_RIGHT;
                }

                if ($horizontal !== null) {
                    $styleArray['alignment'] = ['horizontal' => $horizontal];
                }
            }

            if (empty($styleArray)) {
                continue;
            }

            $excelRow = $startRow + $rowIndex;
            $excelCell = $columns[$colIndex] . $excelRow;
            $ws->getStyle($excelCell)->applyFromArray($styleArray);
        }
    }

    private function applyPartsNotes(
        Worksheet $ws,
        array $notes,
        int $startRow,
        int $rowCount,
        ?array $columns = null
    ): void
    {
        if ($rowCount <= 0 || empty($notes)) {
            return;
        }

        $columns = (is_array($columns) && !empty($columns))
            ? array_values($columns)
            : ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $maxColIndex = count($columns) - 1;
        foreach ($notes as $key => $noteText) {
            $text = trim((string) $noteText);
            if ($text === '') {
                continue;
            }

            if (!preg_match('/^\s*(\d+)\s*:\s*(\d+)\s*$/', (string) $key, $matches)) {
                continue;
            }

            $rowIndex = (int) $matches[1];
            $colIndex = (int) $matches[2];
            if ($rowIndex < 0 || $rowIndex >= $rowCount || $colIndex < 0 || $colIndex > $maxColIndex) {
                continue;
            }

            $excelCell = $columns[$colIndex] . ($startRow + $rowIndex);
            $ws->removeComment($excelCell);

            $comment = $ws->getComment($excelCell);
            $comment->setAuthor('CCR');

            $richText = new RichText();
            $richText->createText($text);
            $comment->setText($richText);
        }
    }

    private function clearRange(Worksheet $sheet, string $startCol, int $startRow, string $endCol, int $endRow): void
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($col = ord($startCol); $col <= ord($endCol); $col++) {
                $cell = chr($col) . $row;
                if (!$sheet->cellExists($cell)) {
                    continue;
                }
                $sheet->getCell($cell)->setValue(null);
            }
        }
    }

    private function trimTrailingEmptyRows(array $rows, callable $isEmpty): array
    {
        $rows = array_values($rows);
        while (!empty($rows)) {
            $last = end($rows);
            if (!is_array($last) || !$isEmpty($last)) {
                break;
            }
            array_pop($rows);
        }
        return $rows;
    }

    private function normalizeDateString(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            [$y, $m, $d] = explode('-', $value);
            return sprintf('%02d/%02d/%04d', (int) $d, (int) $m, (int) $y);
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }
        return date('d/m/Y', $ts);
    }

    private function removeAllConditionalFormatting(Worksheet $sheet): void
    {
        $conditionalMap = $sheet->getConditionalStylesCollection();
        if (empty($conditionalMap)) {
            return;
        }

        foreach (array_keys($conditionalMap) as $range) {
            $sheet->removeConditionalStyles((string) $range);
        }
    }

    private function applyDuplicatePartNumberHighlight(Worksheet $ws, array $rows, int $startRow): void
    {
        if (empty($rows)) {
            return;
        }

        $rowIndexesByPartNumber = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $partNumber = trim((string) ($row['part_number'] ?? ''));
            if ($partNumber === '') {
                continue;
            }

            // Samakan logika duplicate ke level "teks sama" yang ramah user:
            // abaikan case dan rapikan spasi internal.
            $normalized = mb_strtoupper(preg_replace('/\s+/', ' ', $partNumber) ?: '');
            if ($normalized === '') {
                continue;
            }
            $rowIndexesByPartNumber[$normalized][] = (int) $index;
        }

        foreach ($rowIndexesByPartNumber as $rowIndexes) {
            if (count($rowIndexes) < 2) {
                continue;
            }

            foreach ($rowIndexes as $rowIndex) {
                $excelRow = $startRow + $rowIndex;
                $ws->getStyle("D{$excelRow}")
                    ->getFont()
                    ->getColor()
                    ->setARGB('FF9C0006');
            }
        }
    }

    private function toArgbColor($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $hex = trim((string) $value);
        if ($hex === '') {
            return null;
        }

        if ($hex[0] === '#') {
            $hex = substr($hex, 1);
        }

        if (!preg_match('/^[0-9a-fA-F]+$/', $hex)) {
            return null;
        }

        if (strlen($hex) === 3) {
            $hex = strtoupper($hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]);
            return 'FF' . $hex;
        }

        if (strlen($hex) === 6) {
            return 'FF' . strtoupper($hex);
        }

        if (strlen($hex) === 8) {
            return strtoupper($hex);
        }

        return null;
    }

    private function toHoursFloat($value): ?float
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/\s+/', '', $raw);
        $raw = str_replace(',', '.', $raw);
        $raw = preg_replace('/[^\d.\-]/', '', $raw);
        if ($raw === '' || $raw === '-' || !is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function formatHours(float $value): string
    {
        return number_format($value, 2, ',', '');
    }

    private function rupiahFromRawPrice($raw): ?int
    {
        $norm = $this->normalizeMoneyRaw($raw);
        if ($norm === null) {
            return null;
        }
        $negative = str_starts_with($norm, '-');
        if ($negative) {
            $norm = substr($norm, 1);
        }

        $parts = explode('.', $norm, 2);
        $intPart = $parts[0] !== '' ? $parts[0] : '0';
        $decPart = $parts[1] ?? '';
        $decPart = substr($decPart . '00', 0, 2);

        $cents = ((int) $intPart * 100) + (int) $decPart;
        // Same half-up conversion as UI (centsToRupiahDigits).
        $rupiah = (int) floor(($cents + 50) / 100);
        return $negative ? -$rupiah : $rupiah;
    }

    private function rupiahFromRawPriceByQty(int $qty, $raw): ?int
    {
        if ($qty <= 0) {
            return 0;
        }
        $norm = $this->normalizeMoneyRaw($raw);
        if ($norm === null) {
            return null;
        }
        $negative = str_starts_with($norm, '-');
        if ($negative) {
            $norm = substr($norm, 1);
        }

        $parts = explode('.', $norm, 2);
        $intPart = $parts[0] !== '' ? $parts[0] : '0';
        $decPart = $parts[1] ?? '';
        $decPart = substr($decPart . '00', 0, 2);

        $centsEach = ((int) $intPart * 100) + (int) $decPart;
        $totalCents = $qty * $centsEach;
        $rupiah = (int) floor(($totalCents + 50) / 100);
        return $negative ? -$rupiah : $rupiah;
    }

    private function normalizeMoneyRaw($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        $s = preg_replace('/\x{00A0}/u', ' ', $s);
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[^\d,\.\-]/', '', $s);
        if ($s === '' || $s === '-') {
            return null;
        }

        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, '.')) {
            $parts = explode('.', $s);
            if (count($parts) !== 2) {
                $s = str_replace('.', '', $s);
            } else {
                $dec = $parts[1] ?? '';
                if (strlen($dec) > 2) {
                    $s = str_replace('.', '', $s);
                }
            }
        }

        $negative = str_starts_with($s, '-');
        $s = str_replace('-', '', $s);
        if ($s === '') {
            return null;
        }

        $firstDot = strpos($s, '.');
        if ($firstDot !== false) {
            $s = substr($s, 0, $firstDot + 1) . str_replace('.', '', substr($s, $firstDot + 1));
        }
        if (str_starts_with($s, '.')) {
            $s = '0' . $s;
        }

        $parts = explode('.', $s, 2);
        $intPart = ltrim($parts[0], '0');
        $intPart = $intPart === '' ? '0' : $intPart;
        if (isset($parts[1])) {
            $decPart = substr($parts[1], 0, 2);
            $s = $decPart !== '' ? ($intPart . '.' . $decPart) : $intPart;
        } else {
            $s = $intPart;
        }

        return $negative ? '-' . $s : $s;
    }

    private function toInt($value): ?int
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $digits = preg_replace('/[^\d\-]/', '', $raw);
        if ($digits === '' || $digits === '-') {
            return null;
        }
        return (int) $digits;
    }

    private function firstInt(array $candidates, ?int $default): ?int
    {
        foreach ($candidates as $candidate) {
            $parsed = $this->toInt($candidate);
            if ($parsed !== null) {
                return $parsed;
            }
        }
        return $default;
    }

    private function toPercentFraction($value, float $default): float
    {
        if ($value === null) {
            return $default;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return $default;
        }
        $raw = str_replace(['%', ' '], '', $raw);
        $raw = str_replace(',', '.', $raw);
        if (!is_numeric($raw)) {
            return $default;
        }

        $number = (float) $raw;
        if ($number > 1) {
            $number = $number / 100;
        }
        if ($number < 0) {
            return 0.0;
        }
        if ($number > 1) {
            return 1.0;
        }
        return $number;
    }

    private function firstNonEmptyString(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }
            $text = trim((string) $candidate);
            if ($text !== '') {
                return $text;
            }
        }
        return '';
    }
}
