<?php

namespace Tests\Feature;

use App\Models\CcrApprovalAction;
use App\Models\CcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CcrApprovalStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_uses_state_machine_and_idempotency_key(): void
    {
        $director = User::factory()->create(['role' => 'director']);
        $report = $this->makeReport('engine', 'waiting');

        $key = 'approve-key-001';

        $this->actingAs($director)
            ->post(route('director.monitoring.approve', ['id' => $report->id]), [
                'approve_note' => 'Approved by director',
                'idempotency_key' => $key,
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('approved', $report->approval_status);
        $this->assertSame('Approved by director', $report->director_note);

        $this->actingAs($director)
            ->post(route('director.monitoring.approve', ['id' => $report->id]), [
                'approve_note' => 'ignored duplicate',
                'idempotency_key' => $key,
            ])
            ->assertSessionHas('warning');

        $report->refresh();
        $this->assertSame('approved', $report->approval_status);
        $this->assertSame('Approved by director', $report->director_note);

        $this->assertDatabaseCount('ccr_approval_actions', 1);
        $this->assertDatabaseHas('ccr_approval_actions', [
            'ccr_report_id' => $report->id,
            'action' => 'approve',
            'idempotency_key' => $key,
            'was_applied' => 1,
            'from_status' => 'in_review',
            'to_status' => 'approved',
        ]);
    }

    public function test_reject_requires_valid_transition_from_waiting_or_in_review(): void
    {
        $director = User::factory()->create(['role' => 'director']);
        $report = $this->makeReport('seat', 'approved');

        $this->actingAs($director)
            ->post(route('director.monitoring.reject', ['id' => $report->id]), [
                'director_note' => 'Need revision',
                'idempotency_key' => 'reject-after-approved',
            ])
            ->assertSessionHas('warning');

        $report->refresh();
        $this->assertSame('approved', $report->approval_status);

        $this->assertDatabaseHas('ccr_approval_actions', [
            'ccr_report_id' => $report->id,
            'action' => 'reject',
            'idempotency_key' => 'reject-after-approved',
            'was_applied' => 0,
            'from_status' => 'approved',
            'to_status' => 'approved',
        ]);
    }

    private function makeReport(string $type, string $status): CcrReport
    {
        $report = CcrReport::query()->create([
            'type' => $type,
            'group_folder' => $type === 'seat' ? 'Operator Seat' : 'Engine',
            'component' => strtoupper($type) . '-STATE',
            'inspection_date' => now()->toDateString(),
            'make' => 'CAT',
            'model' => '320',
            'unit' => 'UNIT-01',
            'wo_pr' => 'WO-01',
            'sn' => 'SN-001',
            'smu' => '0',
            'customer' => 'PT TEST',
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
