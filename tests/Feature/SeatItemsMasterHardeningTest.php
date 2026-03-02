<?php

namespace Tests\Feature;

use App\Models\ItemMaster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatItemsMasterHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_seat_rejects_oversized_rows_payload(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $huge = str_repeat('X', (8 * 1024 * 1024) + 256);

        $response = $this->actingAs($user)->post(
            route('items_master.seat.sync'),
            [
                'rows_json' => json_encode([
                    [
                        'uid' => 'si_1',
                        'item' => $huge,
                    ],
                ]),
                'full_sync' => '1',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_sync_seat_trims_text_and_limits_photo_paths_per_row(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $photoPaths = [];
        for ($i = 1; $i <= 25; $i++) {
            $photoPaths[] = 'items_master/seat/p' . $i . '.jpg';
        }

        $response = $this->actingAs($user)->post(
            route('items_master.seat.sync'),
            [
                'rows_json' => json_encode([
                    [
                        'uid' => 'si_1',
                        'no' => '1',
                        'category' => str_repeat('C', 120),
                        'pn' => str_repeat('P', 120),
                        'item' => str_repeat('I', 400),
                        'purchase_price' => '123456',
                        'sales_price' => '654321',
                        'photo_paths' => $photoPaths,
                    ],
                ]),
                'full_sync' => '1',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('ok', true);

        $row = ItemMaster::query()->where('module', 'seat')->firstOrFail();
        $savedPaths = is_array($row->photo_paths) ? $row->photo_paths : [];

        $this->assertSame(80, strlen((string) $row->category));
        $this->assertSame(80, strlen((string) $row->pn));
        $this->assertSame(255, strlen((string) $row->item));
        $this->assertCount(10, $savedPaths);
    }

    public function test_sync_seat_rejects_when_expected_upload_count_exceeds_received(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post(
            route('items_master.seat.sync'),
            [
                'rows_json' => json_encode([
                    [
                        'uid' => 'si_1',
                        'no' => '1',
                        'item' => 'Seat Item',
                    ],
                ]),
                'expected_upload_count' => 5,
                'full_sync' => '1',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }
}

