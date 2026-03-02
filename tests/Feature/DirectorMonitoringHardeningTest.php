<?php

namespace Tests\Feature;

use App\Models\CcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorMonitoringHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_applies_server_side_filters(): void
    {
        $director = User::factory()->create(['role' => 'director']);

        $this->makeReport('engine', 'MON-ENGINE-ALPHA', 'PT A', 'waiting');
        $this->makeReport('seat', 'MON-SEAT-BETA', 'PT B', 'waiting');
        $this->makeReport('engine', 'MON-ENGINE-APPROVED', 'PT A', 'approved');

        $response = $this->actingAs($director)
            ->get(route('director.monitoring', [
                'type' => 'engine',
                'customer' => 'PT A',
                'q' => 'ALPHA',
                'sort' => 'submitted_newest',
                'per_page' => 25,
            ]));

        $response->assertOk();
        $response->assertSee('MON-ENGINE-ALPHA');
        $response->assertDontSee('MON-SEAT-BETA');
        $response->assertDontSee('MON-ENGINE-APPROVED');
    }

    public function test_approve_is_guarded_against_double_processing(): void
    {
        $director = User::factory()->create(['role' => 'director']);
        $report = $this->makeReport('engine', 'MON-GUARD-APPROVE', 'PT Guard', 'waiting');

        $this->actingAs($director)
            ->post(route('director.monitoring.approve', ['id' => $report->id]), [
                'approve_note' => 'ok',
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('approved', $report->approval_status);

        $this->actingAs($director)
            ->post(route('director.monitoring.approve', ['id' => $report->id]), [
                'approve_note' => 'second try',
            ])
            ->assertSessionHas('warning');

        $report->refresh();
        $this->assertSame('approved', $report->approval_status);
    }

    public function test_reject_note_is_sanitized_and_required_after_sanitize(): void
    {
        $director = User::factory()->create(['role' => 'director']);
        $report = $this->makeReport('seat', 'MON-GUARD-REJECT', 'PT Guard', 'waiting');

        $this->actingAs($director)
            ->from(route('director.monitoring'))
            ->post(route('director.monitoring.reject', ['id' => $report->id]), [
                '_reject_id' => $report->id,
                'director_note' => '<b>   </b>',
            ])
            ->assertRedirect(route('director.monitoring'))
            ->assertSessionHasErrors('director_note');

        $report->refresh();
        $this->assertSame('waiting', $report->approval_status);

        $this->actingAs($director)
            ->post(route('director.monitoring.reject', ['id' => $report->id]), [
                '_reject_id' => $report->id,
                'director_note' => '<script>alert(1)</script> Mohon revisi',
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('rejected', $report->approval_status);
        $this->assertSame('alert(1) Mohon revisi', $report->director_note);
    }

    private function makeReport(string $type, string $component, string $customer, string $status): CcrReport
    {
        $report = CcrReport::query()->create([
            'type' => $type,
            'group_folder' => $type === 'seat' ? 'Operator Seat' : 'Engine',
            'component' => $component,
            'inspection_date' => now()->toDateString(),
            'make' => 'CAT',
            'model' => '320',
            'unit' => 'UNIT-01',
            'wo_pr' => 'WO-PR-01',
            'sn' => 'SN-001',
            'smu' => '0',
            'customer' => $customer,
            'parts_payload' => [],
            'detail_payload' => [],
            'template_key' => $type === 'seat' ? 'seat_blank' : 'engine_blank',
            'template_version' => 1,
        ]);

        $report->approval_status = $status;
        $report->submitted_at = now();
        $report->save();

        return $report;
    }
}
