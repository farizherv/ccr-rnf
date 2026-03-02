<?php

namespace Tests\Feature;

use App\Models\CcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPreviewTypeGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_seat_preview_and_download_routes_reject_engine_report_id(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $engineReport = $this->makeReport('engine');

        $this->actingAs($user)
            ->get(route('seat.preview', ['id' => $engineReport->id]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('seat.preview.pdf', ['id' => $engineReport->id]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('seat.export.word', ['id' => $engineReport->id]))
            ->assertNotFound();
    }

    public function test_engine_preview_and_download_routes_reject_seat_report_id(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $seatReport = $this->makeReport('seat');

        $this->actingAs($user)
            ->get(route('engine.preview', ['id' => $seatReport->id]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('engine.preview.pdf', ['id' => $seatReport->id]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('engine.export.word', ['id' => $seatReport->id]))
            ->assertNotFound();
    }

    private function makeReport(string $type): CcrReport
    {
        return CcrReport::query()->create([
            'type' => $type,
            'group_folder' => $type === 'seat' ? 'Operator Seat' : 'Engine',
            'component' => strtoupper($type) . ' TYPE GUARD',
            'inspection_date' => now()->toDateString(),
            'make' => 'CAT',
            'model' => '320',
            'unit' => 'UNIT-01',
            'wo_pr' => 'WO-PR-01',
            'sn' => 'SN-001',
            'smu' => '0',
            'customer' => 'PT Type Guard',
            'parts_payload' => [],
            'detail_payload' => [],
            'template_key' => $type === 'seat' ? 'seat_blank' : 'engine_blank',
            'template_version' => 1,
        ]);
    }
}

