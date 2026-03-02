<?php

namespace Tests\Feature;

use App\Models\CcrItem;
use App\Models\CcrPhoto;
use App\Models\CcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorksheetMutationRevisionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_delete_item_rejects_stale_revision(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $report = $this->makeReport('engine', 20, 30);
        $item = CcrItem::query()->create([
            'ccr_report_id' => $report->id,
            'description' => 'Item engine',
        ]);

        Storage::disk('public')->put('photos/test-engine.jpg', 'content');
        CcrPhoto::query()->create([
            'ccr_item_id' => $item->id,
            'path' => 'photos/test-engine.jpg',
        ]);

        $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->delete(route('engine.item.delete', ['item' => $item->id]), [
                'parts_payload_rev' => 10,
                'detail_payload_rev' => 10,
            ])
            ->assertStatus(409)
            ->assertJsonPath('stale', true);

        $this->assertDatabaseHas('ccr_items', ['id' => $item->id]);

        $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->delete(route('engine.item.delete', ['item' => $item->id]), [
                'parts_payload_rev' => 20,
                'detail_payload_rev' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('ccr_items', ['id' => $item->id]);
    }

    public function test_seat_delete_item_rejects_stale_revision(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $report = $this->makeReport('seat', 51, 70);
        $item = CcrItem::query()->create([
            'ccr_report_id' => $report->id,
            'description' => 'Item seat',
        ]);

        Storage::disk('public')->put('photos/test-seat.jpg', 'content');
        CcrPhoto::query()->create([
            'ccr_item_id' => $item->id,
            'path' => 'photos/test-seat.jpg',
        ]);

        $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->delete(route('seat.item.delete', ['item' => $item->id]), [
                'parts_payload_rev' => 50,
                'detail_payload_rev' => 68,
            ])
            ->assertStatus(409)
            ->assertJsonPath('stale', true);

        $this->assertDatabaseHas('ccr_items', ['id' => $item->id]);

        $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->delete(route('seat.item.delete', ['item' => $item->id]), [
                'parts_payload_rev' => 51,
                'detail_payload_rev' => 70,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('ccr_items', ['id' => $item->id]);
    }

    private function makeReport(string $type, int $partsRev, int $detailRev): CcrReport
    {
        return CcrReport::query()->create([
            'type' => $type,
            'group_folder' => $type === 'seat' ? 'Operator Seat' : 'Engine',
            'component' => strtoupper($type) . '-REV',
            'inspection_date' => now()->toDateString(),
            'make' => 'CAT',
            'model' => '320',
            'unit' => 'UNIT',
            'wo_pr' => 'WO',
            'sn' => 'SN',
            'smu' => '0',
            'customer' => 'PT TEST',
            'parts_payload' => ['rows' => [], 'ts' => $partsRev],
            'detail_payload' => ['rows' => [], 'ts' => $detailRev],
            'parts_payload_rev' => $partsRev,
            'detail_payload_rev' => $detailRev,
            'template_key' => $type === 'seat' ? 'seat_blank' : 'engine_blank',
            'template_version' => 1,
            'approval_status' => 'draft',
        ]);
    }
}
