<?php

namespace Tests\Feature;

use App\Models\CcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class EnginePartsLabourExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_parts_labour_applies_notes_as_excel_comments(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $report = $this->makeEngineReport([
            'parts_payload' => [
                'meta' => [
                    'template_key' => 'engine_blank',
                    'template_version' => 'v1',
                    'no_unit' => 'q7',
                ],
                'rows' => [
                    [
                        'qty' => '1',
                        'uom' => 'SET',
                        'part_number' => 'MX913349',
                        'part_description' => 'bearing set crankshaft',
                        'purchase_price' => '473000',
                        'total' => '473000',
                        'sales_price' => '1392160',
                        'extended_price' => '1392160',
                    ],
                ],
                'styles' => [
                    '0:4' => ['bold' => true],
                ],
                'notes' => [
                    '0:4' => 'Catatan export q7',
                ],
            ],
            'detail_payload' => [
                'meta' => [
                    'template_key' => 'engine_blank',
                    'template_version' => 'v1',
                ],
                'main_rows' => [],
                'painting_rows' => [],
                'external_rows' => [],
                'misc' => [],
                'totals' => [],
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('engine.export.parts_labour', ['id' => $report->id]));

        $response->assertOk();

        $baseResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);

        $file = $baseResponse->getFile();
        $this->assertNotNull($file);

        $spreadsheet = IOFactory::load($file->getPathname());
        try {
            $sheet = $spreadsheet->getSheetByName('PARTS & LABOUR WORKSHEET');
            $this->assertNotNull($sheet);
            $this->assertSame('Catatan export q7', trim((string) $sheet->getComment('E4')->getText()));
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public function test_export_detail_sheet_expands_rows_for_component_painting_and_external_sections(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $mainRows = [];
        for ($i = 1; $i <= 43; $i++) {
            $mainRows[] = [
                'seg' => (string) $i,
                'code' => 'CODE-' . $i,
                'component_desc' => 'COMP-' . $i,
                'work_desc' => 'WORK-' . $i,
                'work_order' => 'WO-' . $i,
                'hours' => (string) $i,
                'labour_charge' => (string) ($i * 1000),
                'parts_charge' => (string) ($i * 2000),
            ];
        }

        $paintingRows = [];
        for ($i = 1; $i <= 7; $i++) {
            $paintingRows[] = [
                'item' => 'PAINT-' . $i,
                'qty' => (string) $i,
                'uom' => 'LTRS',
                'unit_price' => '10000',
                'total' => (string) ($i * 10000),
            ];
        }

        $externalRows = [];
        for ($i = 1; $i <= 10; $i++) {
            $externalRows[] = [
                'service' => 'SERVICE-' . $i,
                'amount' => (string) ($i * 50000),
                'remark' => 'REMARK-' . $i,
            ];
        }

        $report = $this->makeEngineReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'rows' => [
                    ['qty' => '1', 'part_number' => 'PN-1', 'part_description' => 'Part A'],
                ],
            ],
            'detail_payload' => [
                'meta' => [
                    'template_key' => 'engine_blank',
                    'template_version' => 'v1',
                ],
                'main_rows' => $mainRows,
                'painting_rows' => $paintingRows,
                'external_rows' => $externalRows,
                'misc' => [],
                'totals' => [],
            ],
        ]);

        $this->withExportedDetailSheet($user, $report->id, function ($sheet): void {
            $this->assertSame('COMP-43', trim((string) $sheet->getCell('E52')->getValue()));
            $this->assertSame(43000, (int) $sheet->getCell('K52')->getValue());
            $this->assertSame('FF000000', $sheet->getStyle('K52')->getFont()->getColor()->getARGB());
            $this->assertSame('PAINT-7', trim((string) $sheet->getCell('D64')->getValue()));
            $this->assertSame(70000, (int) $sheet->getCell('I64')->getValue());
            $this->assertSame('SERVICE-10', trim((string) $sheet->getCell('F79')->getValue()));
            $this->assertSame(500000, (int) $sheet->getCell('I79')->getValue());
            $this->assertSame('TOTAL LABOUR', trim((string) $sheet->getCell('J80')->getValue()));
            $this->assertFalse($sheet->cellExists('XFD78'));

            // Overflow rows must keep website-like colspan layout.
            $this->assertRangeMerged($sheet, 'D64:E64');
            $this->assertRangeMerged($sheet, 'J64:K64');
            $this->assertRangeMerged($sheet, 'A79:E79');
            $this->assertRangeMerged($sheet, 'F79:H79');
            $this->assertRangeMerged($sheet, 'J79:K79');
            $this->assertRangeMerged($sheet, 'A80:I80');
            $this->assertRangeMerged($sheet, 'J80:K80');

            // Overflow rows must preserve section-closing borders on the real last row only.
            $this->assertSame('none', $sheet->getStyle('A50')->getBorders()->getBottom()->getBorderStyle());
            $this->assertSame('double', $sheet->getStyle('A52')->getBorders()->getBottom()->getBorderStyle());
            $this->assertSame('hair', $sheet->getStyle('D64')->getBorders()->getBottom()->getBorderStyle());
            $this->assertSame('thin', $sheet->getStyle('D65')->getBorders()->getBottom()->getBorderStyle());
            $this->assertSame('hair', $sheet->getStyle('L78')->getBorders()->getBottom()->getBorderStyle());
            $this->assertSame('thin', $sheet->getStyle('L79')->getBorders()->getBottom()->getBorderStyle());

            // Overflow rows must also preserve per-column template style (green cells + currency formats).
            $this->assertSame('FF92D050', $sheet->getStyle('F64')->getFill()->getStartColor()->getARGB());
            $this->assertSame('FF92D050', $sheet->getStyle('H64')->getFill()->getStartColor()->getARGB());
            $this->assertStringContainsString('Rp', $sheet->getStyle('H64')->getNumberFormat()->getFormatCode());
            $this->assertSame('FF92D050', $sheet->getStyle('I78')->getFill()->getStartColor()->getARGB());
            $this->assertStringContainsString('Rp', $sheet->getStyle('I78')->getNumberFormat()->getFormatCode());
        });
    }

    public function test_export_detail_sheet_hides_unused_rows_when_data_is_short(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $report = $this->makeEngineReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'rows' => [
                    ['qty' => '1', 'part_number' => 'PN-2', 'part_description' => 'Part B'],
                ],
            ],
            'detail_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'main_rows' => [
                    [
                        'seg' => '1',
                        'code' => 'A1',
                        'component_desc' => 'Short main',
                        'work_desc' => 'Short work',
                        'hours' => '1',
                        'labour_charge' => '1000',
                        'parts_charge' => '2000',
                    ],
                    [
                        'seg' => '2',
                        'code' => 'A2',
                        'component_desc' => 'Short main 2',
                        'work_desc' => 'Short work 2',
                        'hours' => '2',
                        'labour_charge' => '2000',
                        'parts_charge' => '3000',
                    ],
                ],
                'painting_rows' => [
                    ['item' => 'PAINT-1', 'qty' => '1', 'uom' => 'LTRS', 'unit_price' => '1000', 'total' => '1000'],
                ],
                'external_rows' => [
                    ['service' => 'SERVICE-1', 'amount' => '5000', 'remark' => 'Short'],
                ],
                'misc' => [],
                'totals' => [],
            ],
        ]);

        $this->withExportedDetailSheet($user, $report->id, function ($sheet): void {
            $this->assertTrue($sheet->getRowDimension(10)->getVisible());
            $this->assertTrue($sheet->getRowDimension(11)->getVisible());
            $this->assertFalse($sheet->getRowDimension(12)->getVisible());
            $this->assertTrue($sheet->getRowDimension(56)->getVisible());
            $this->assertFalse($sheet->getRowDimension(57)->getVisible());
            $this->assertTrue($sheet->getRowDimension(67)->getVisible());
            $this->assertFalse($sheet->getRowDimension(68)->getVisible());
        });
    }

    public function test_export_detail_sheet_keeps_template_style_for_overflow_without_main_expansion(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $mainRows = [];
        for ($i = 1; $i <= 7; $i++) {
            $mainRows[] = [
                'seg' => (string) $i,
                'code' => 'M-' . $i,
                'component_desc' => 'MAIN-' . $i,
                'work_desc' => 'WORK-' . $i,
                'hours' => (string) $i,
                'labour_charge' => (string) ($i * 1000),
                'parts_charge' => (string) ($i * 2000),
            ];
        }

        $paintingRows = [];
        for ($i = 1; $i <= 7; $i++) {
            $paintingRows[] = [
                'item' => 'P-' . $i,
                'qty' => (string) $i,
                'uom' => 'LTRS',
                'unit_price' => '10000',
                'total' => (string) ($i * 10000),
            ];
        }

        $externalRows = [];
        for ($i = 1; $i <= 13; $i++) {
            $externalRows[] = [
                'service' => 'E-' . $i,
                'amount' => (string) ($i * 10000),
                'remark' => '',
            ];
        }

        $report = $this->makeEngineReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'rows' => [
                    ['qty' => '1', 'part_number' => 'PN-3', 'part_description' => 'Part C'],
                ],
            ],
            'detail_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'main_rows' => $mainRows,
                'painting_rows' => $paintingRows,
                'external_rows' => $externalRows,
                'misc' => [],
                'totals' => [],
            ],
        ]);

        $this->withExportedDetailSheet($user, $report->id, function (Worksheet $sheet): void {
            $this->assertSame('P-7', trim((string) $sheet->getCell('D62')->getValue()));
            $this->assertSame('E-13', trim((string) $sheet->getCell('F80')->getValue()));
            $this->assertSame('TOTAL LABOUR', trim((string) $sheet->getCell('J81')->getValue()));

            // Overflow painting row must match template middle-row styling.
            $this->assertCellStyleMatches($sheet, 'F57', 'F62');
            $this->assertCellStyleMatches($sheet, 'H57', 'H62');
            $this->assertCellStyleMatches($sheet, 'I57', 'I62');

            // Overflow external rows must match template middle-row styling.
            $this->assertCellStyleMatches($sheet, 'F68', 'F75');
            $this->assertCellStyleMatches($sheet, 'I68', 'I75');
            $this->assertCellStyleMatches($sheet, 'F68', 'F79');
            $this->assertCellStyleMatches($sheet, 'I68', 'I79');
        });
    }

    private function withExportedDetailSheet(User $user, int $reportId, callable $assertions): void
    {
        $response = $this->actingAs($user)
            ->get(route('engine.export.parts_labour', ['id' => $reportId]));

        $response->assertOk();

        $baseResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);

        $file = $baseResponse->getFile();
        $this->assertNotNull($file);

        $spreadsheet = IOFactory::load($file->getPathname());
        try {
            $sheet = $spreadsheet->getSheetByName('DETAIL');
            $this->assertNotNull($sheet);
            $assertions($sheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function assertCellStyleMatches(Worksheet $sheet, string $sourceCell, string $targetCell): void
    {
        $source = $sheet->getStyle($sourceCell);
        $target = $sheet->getStyle($targetCell);

        $this->assertSame(
            $source->getFill()->getStartColor()->getARGB(),
            $target->getFill()->getStartColor()->getARGB(),
            "Fill mismatch {$sourceCell} -> {$targetCell}"
        );
        $this->assertSame(
            $source->getNumberFormat()->getFormatCode(),
            $target->getNumberFormat()->getFormatCode(),
            "Format mismatch {$sourceCell} -> {$targetCell}"
        );
        $this->assertSame(
            $source->getBorders()->getTop()->getBorderStyle(),
            $target->getBorders()->getTop()->getBorderStyle(),
            "Top border mismatch {$sourceCell} -> {$targetCell}"
        );
        $this->assertSame(
            $source->getBorders()->getBottom()->getBorderStyle(),
            $target->getBorders()->getBottom()->getBorderStyle(),
            "Bottom border mismatch {$sourceCell} -> {$targetCell}"
        );
        $this->assertSame(
            $source->getBorders()->getLeft()->getBorderStyle(),
            $target->getBorders()->getLeft()->getBorderStyle(),
            "Left border mismatch {$sourceCell} -> {$targetCell}"
        );
        $this->assertSame(
            $source->getBorders()->getRight()->getBorderStyle(),
            $target->getBorders()->getRight()->getBorderStyle(),
            "Right border mismatch {$sourceCell} -> {$targetCell}"
        );
    }

    private function assertRangeMerged(Worksheet $sheet, string $range): void
    {
        $this->assertArrayHasKey($range, $sheet->getMergeCells(), "Missing merged range {$range}");
    }

    private function makeEngineReport(array $overrides = []): CcrReport
    {
        $defaults = [
            'type' => 'engine',
            'group_folder' => 'Engine',
            'component' => 'Engine Export Test',
            'inspection_date' => now()->toDateString(),
            'make' => 'CAT',
            'model' => '3408',
            'sn' => 'SN-EXPORT-001',
            'customer' => 'PT Export Test',
            'parts_payload' => [],
            'detail_payload' => [],
            'template_key' => 'engine_blank',
            'template_version' => 1,
        ];

        return CcrReport::query()->create(array_merge($defaults, $overrides));
    }
}
