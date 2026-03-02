<?php

namespace Tests\Feature;

use App\Models\CcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class SeatPartsLabourExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_seat_export_parts_labour_includes_manual_charges_on_detail_sheet(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $report = $this->makeSeatReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'seat_blank', 'template_version' => 'v1'],
                'rows' => [
                    ['qty' => '1', 'part_number' => 'SEAT-1', 'part_description' => 'Seat Part 1'],
                ],
            ],
            'detail_payload' => [
                'meta' => ['template_key' => 'seat_blank', 'template_version' => 'v1'],
                'main_rows' => [
                    [
                        'seg' => '1',
                        'code' => 'SC-01',
                        'component_desc' => 'Seat Component',
                        'work_desc' => 'Seat Work',
                        'work_order' => 'WO-SEAT-01',
                        'hours' => '2',
                        'labour_charge' => '450000',
                        'parts_charge' => '900000',
                    ],
                ],
                'painting_rows' => [],
                'external_rows' => [],
                'misc' => [],
                'totals' => [],
            ],
        ]);

        $this->withExportedDetailSheet($user, $report->id, function (Worksheet $sheet): void {
            $this->assertSame('Seat Component', trim((string) $sheet->getCell('E10')->getValue()));
            $this->assertSame(450000, (int) $sheet->getCell('K10')->getValue());
            $this->assertSame(900000, (int) $sheet->getCell('L10')->getValue());
            $this->assertSame('FF000000', $sheet->getStyle('K10')->getFont()->getColor()->getARGB());
        });
    }

    public function test_seat_export_route_returns_404_for_non_seat_report(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $engineReport = CcrReport::query()->create([
            'type' => 'engine',
            'group_folder' => 'Engine',
            'component' => 'Engine Report',
            'inspection_date' => now()->toDateString(),
            'parts_payload' => [],
            'detail_payload' => [],
            'template_key' => 'engine_blank',
            'template_version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('seat.export.parts_labour', ['id' => $engineReport->id]))
            ->assertNotFound();
    }

    public function test_seat_export_uses_seat_specific_parts_layout(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $report = $this->makeSeatReport([
            'parts_payload' => [
                'meta' => [
                    'template_key' => 'seat_part_list_operator_seat',
                    'template_version' => 'v1',
                    'no_unit' => 'ST-UNIT-01',
                ],
                'rows' => [
                    [
                        'qty' => '2',
                        'uom' => 'EA',
                        'part_number' => 'SEAT-2',
                        'part_description' => 'Trim seat',
                        'part_section' => 'Seat',
                        'sales_price' => '600000',
                        'extended_price' => '1200000',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('seat.export.parts_labour', ['id' => $report->id]));

        $response->assertOk();
        $baseResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);

        $file = $baseResponse->getFile();
        $this->assertNotNull($file);

        $spreadsheet = IOFactory::load($file->getPathname());
        try {
            $sheet = $spreadsheet->getSheetByName('PARTS & LABOUR WORKSHEET');
            $this->assertNotNull($sheet);

            $this->assertSame('Items', trim((string) $sheet->getCell('A3')->getValue()));
            $this->assertSame('Quantity', trim((string) $sheet->getCell('B3')->getValue()));
            $this->assertSame('Uom', trim((string) $sheet->getCell('C3')->getValue()));
            $this->assertSame('Part Number', trim((string) $sheet->getCell('D3')->getValue()));
            $this->assertSame('Part Description', trim((string) $sheet->getCell('E3')->getValue()));
            $this->assertSame('Part Section', trim((string) $sheet->getCell('F3')->getValue()));
            $this->assertSame('', trim((string) $sheet->getCell('G3')->getValue()));
            $this->assertSame('Sales Price', trim((string) $sheet->getCell('H3')->getValue()));
            $this->assertSame('Extended Price', trim((string) $sheet->getCell('I3')->getValue()));
            $this->assertSame('ST-UNIT-01', trim((string) $sheet->getCell('D1')->getValue()));

            $this->assertSame(1, (int) $sheet->getCell('A5')->getValue());
            $this->assertSame(2, (int) $sheet->getCell('B5')->getValue());
            $this->assertSame('EA', trim((string) $sheet->getCell('C5')->getValue()));
            $this->assertSame('SEAT-2', trim((string) $sheet->getCell('D5')->getValue()));
            $this->assertSame('Trim seat', trim((string) $sheet->getCell('E5')->getValue()));
            $this->assertSame('Seat', trim((string) $sheet->getCell('F5')->getValue()));
            $this->assertSame(600000, (int) $sheet->getCell('H5')->getValue());
            $this->assertSame(1200000, (int) $sheet->getCell('I5')->getValue());
            $this->assertSame(1200000, (int) $sheet->getCell('I35')->getValue());
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public function test_seat_export_shows_only_two_detail_rows_when_component_and_external_are_empty(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $mainRows = [];
        for ($i = 1; $i <= 44; $i++) {
            $mainRows[] = [
                'seg' => (string) $i,
                'code' => '',
                'component_desc' => '',
                'work_desc' => '',
                'work_order' => '',
                'hours' => '0,00',
                'labour_charge' => '0',
                'parts_charge' => '0',
            ];
        }

        $externalRows = [];
        for ($i = 1; $i <= 8; $i++) {
            $externalRows[] = [
                'service' => '',
                'remark' => '',
                'amount' => '0',
            ];
        }

        $report = $this->makeSeatReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'seat_part_list_operator_seat', 'template_version' => 'v1'],
                'rows' => [],
            ],
            'detail_payload' => [
                'meta' => ['template_key' => 'seat_part_list_operator_seat', 'template_version' => 'v1'],
                'main_rows' => $mainRows,
                'painting_rows' => [],
                'external_rows' => $externalRows,
                'misc' => [],
                'totals' => [],
            ],
        ]);

        $this->withExportedDetailSheet($user, $report->id, function (Worksheet $sheet): void {
            $this->assertTrue($sheet->getRowDimension(10)->getVisible(), 'Main row 10 should stay visible');
            $this->assertTrue($sheet->getRowDimension(11)->getVisible(), 'Main row 11 should stay visible');
            $this->assertFalse($sheet->getRowDimension(12)->getVisible(), 'Main row 12 should be hidden');
            $this->assertTrue($sheet->getRowDimension(67)->getVisible(), 'External row 67 should stay visible');
            $this->assertTrue($sheet->getRowDimension(68)->getVisible(), 'External row 68 should stay visible');
            $this->assertFalse($sheet->getRowDimension(69)->getVisible(), 'External row 69 should be hidden');
        });
    }

    private function withExportedDetailSheet(User $user, int $reportId, callable $assertions): void
    {
        $response = $this->actingAs($user)
            ->get(route('seat.export.parts_labour', ['id' => $reportId]));

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

    private function makeSeatReport(array $overrides = []): CcrReport
    {
        $defaults = [
            'type' => 'seat',
            'group_folder' => 'Operator Seat',
            'component' => 'Seat Export Test',
            'inspection_date' => now()->toDateString(),
            'make' => 'CAT',
            'model' => '320',
            'sn' => 'SN-SEAT-001',
            'customer' => 'PT Seat Export Test',
            'parts_payload' => [],
            'detail_payload' => [],
            'template_key' => 'seat_blank',
            'template_version' => 1,
        ];

        return CcrReport::query()->create(array_merge($defaults, $overrides));
    }
}
