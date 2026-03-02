<?php

namespace Tests\Feature;

use App\Models\CcrDraft;
use App\Models\CcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EngineWorksheetPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parts_styles_notes_and_tools_are_persisted_via_autosave(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $report = $this->makeEngineReport();

        $partsPayload = [
            'meta' => [
                'template_key' => 'engine_blank',
                'template_version' => 'v1',
                'no_unit' => 'UNIT-77',
            ],
            'rows' => [
                ['qty' => '1', 'part_description' => 'Piston', 'total' => '12000'],
                ['qty' => '2', 'part_description' => 'Ring', 'total' => '16000'],
            ],
            'styles' => [
                '0:3' => ['bold' => true, 'italic' => true, 'align' => 'center', 'color' => '#111111'],
                '1:4' => ['underline' => true, 'align' => 'right', 'color' => '#DC2626'],
            ],
            'notes' => [
                '0:3' => 'Catatan style 1',
                '1:4' => 'Catatan style 2',
            ],
            'tools' => [
                'active' => 'format',
                'last_color' => '#DC2626',
            ],
            'ts' => 1700000001000,
        ];

        $response = $this->actingAs($user)->postJson(
            route('engine.worksheet.autosave', ['id' => $report->id]),
            [
                'parts_payload' => $partsPayload,
                'parts_payload_rev' => 0,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('stale.parts', false);

        $report->refresh();
        $saved = (array) $report->parts_payload;

        $this->assertSame($partsPayload['styles'], $saved['styles'] ?? []);
        $this->assertSame($partsPayload['notes'], $saved['notes'] ?? []);
        $this->assertSame($partsPayload['tools'], $saved['tools'] ?? []);
        $this->assertSame($partsPayload['ts'], (int) ($report->parts_payload_rev ?? 0));
    }

    public function test_stale_autosave_payload_is_ignored(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $currentRev = 1700000002000;
        $freshPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'rows' => [['qty' => '1', 'part_description' => 'Fresh']],
            'styles' => ['0:1' => ['bold' => true]],
            'notes' => ['0:1' => 'Newest'],
            'ts' => $currentRev,
        ];

        $report = $this->makeEngineReport([
            'parts_payload' => $freshPayload,
            'parts_payload_rev' => $currentRev,
        ]);

        $stalePayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'rows' => [['qty' => '9', 'part_description' => 'Stale']],
            'styles' => ['0:1' => ['bold' => false]],
            'notes' => ['0:1' => 'Old value'],
            'ts' => $currentRev - 500,
        ];

        $response = $this->actingAs($user)->postJson(
            route('engine.worksheet.autosave', ['id' => $report->id]),
            [
                'parts_payload' => $stalePayload,
                'parts_payload_rev' => $currentRev - 1000,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('stale.parts', true)
            ->assertJsonPath('saved', false);

        $report->refresh();
        $saved = (array) $report->parts_payload;

        $this->assertSame($freshPayload['rows'], $saved['rows'] ?? []);
        $this->assertSame($freshPayload['styles'], $saved['styles'] ?? []);
        $this->assertSame($freshPayload['notes'], $saved['notes'] ?? []);
        $this->assertSame($currentRev, (int) ($report->parts_payload_rev ?? 0));
    }

    public function test_update_header_accepts_payload_without_ts_when_client_revision_is_current(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $report = $this->makeEngineReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'rows' => [['qty' => '1', 'part_description' => 'Old']],
                'styles' => ['0:0' => ['bold' => false]],
            ],
            'detail_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'main_rows' => [['component_desc' => 'Old Detail']],
            ],
            'parts_payload_rev' => 3210,
            'detail_payload_rev' => 6540,
        ]);

        $newPartsPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'rows' => [['qty' => '3', 'part_description' => 'Updated']],
            'styles' => ['0:0' => ['bold' => true, 'italic' => true]],
            'notes' => ['0:0' => 'Persist after edit submit'],
            'tools' => ['active' => 'font'],
        ];

        $newDetailPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'main_rows' => [['component_desc' => 'Updated Detail']],
            'totals' => ['sales_tax_percent' => 11],
        ];

        $response = $this->actingAs($user)->put(
            route('engine.update.header', ['id' => $report->id]),
            [
                'group_folder' => 'Engine',
                'component' => 'Updated Engine',
                'inspection_date' => now()->toDateString(),
                'parts_payload' => json_encode($newPartsPayload),
                'detail_payload' => json_encode($newDetailPayload),
                'parts_payload_rev' => 3210,
                'detail_payload_rev' => 6540,
            ]
        );

        $response
            ->assertRedirect(route('ccr.manage.engine'))
            ->assertSessionHas('success');

        $report->refresh();
        $savedParts = (array) $report->parts_payload;
        $savedDetail = (array) $report->detail_payload;

        $this->assertSame($newPartsPayload['styles'], $savedParts['styles'] ?? []);
        $this->assertSame($newPartsPayload['notes'], $savedParts['notes'] ?? []);
        $this->assertSame($newPartsPayload['tools'], $savedParts['tools'] ?? []);
        $this->assertSame($newDetailPayload['main_rows'], $savedDetail['main_rows'] ?? []);
        $this->assertGreaterThan(3210, (int) ($report->parts_payload_rev ?? 0));
        $this->assertGreaterThan(6540, (int) ($report->detail_payload_rev ?? 0));
    }

    public function test_update_header_without_ts_treats_submit_as_authoritative_even_when_client_revision_is_old(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $report = $this->makeEngineReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'rows' => [['qty' => '4', 'part_description' => 'Latest']],
                'styles' => ['0:0' => ['bold' => true]],
                'notes' => ['0:0' => 'Latest note'],
            ],
            'parts_payload_rev' => 700,
        ]);

        $stalePartsPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'rows' => [['qty' => '1', 'part_description' => 'Stale']],
            'styles' => ['0:0' => ['bold' => false]],
            'notes' => ['0:0' => 'Stale note'],
        ];

        $response = $this->actingAs($user)->put(
            route('engine.update.header', ['id' => $report->id]),
            [
                'group_folder' => 'Engine',
                'component' => 'Engine With Stale Submit',
                'inspection_date' => now()->toDateString(),
                'parts_payload' => json_encode($stalePartsPayload),
                'parts_payload_rev' => 699,
                'detail_payload' => json_encode((array) ($report->detail_payload ?? [])),
                'detail_payload_rev' => (int) ($report->detail_payload_rev ?? 0),
            ]
        );

        $response
            ->assertRedirect(route('ccr.manage.engine'))
            ->assertSessionHas('success');

        $report->refresh();
        $savedParts = (array) $report->parts_payload;

        $this->assertSame('Stale', data_get($savedParts, 'rows.0.part_description'));
        $this->assertSame(['bold' => false], data_get($savedParts, 'styles.0:0'));
        $this->assertSame('Stale note', data_get($savedParts, 'notes.0:0'));
        $this->assertGreaterThan(700, (int) ($report->parts_payload_rev ?? 0));
    }

    public function test_store_persists_parts_styles_notes_tools_payload(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $partsPayload = [
            'meta' => [
                'template_key' => 'engine_blank',
                'template_version' => 'v1',
                'no_unit' => 'UNIT-STORE',
            ],
            'rows' => [
                ['qty' => '2', 'part_description' => 'Stored row', 'total' => '22000'],
            ],
            'styles' => [
                '0:3' => ['bold' => true, 'align' => 'center', 'color' => '#111111'],
            ],
            'notes' => [
                '0:3' => 'Stored note',
            ],
            'tools' => [
                'last_action' => 'save_note',
                'last_action_at' => 1700000003000,
            ],
            'ts' => 1700000003000,
        ];

        $detailPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'main_rows' => [['component_desc' => 'Stored detail']],
            'totals' => ['sales_tax_percent' => 11],
            'ts' => 1700000003001,
        ];

        $response = $this->actingAs($user)->post(route('engine.store'), [
            'group_folder' => 'Engine',
            'component' => 'Engine Stored',
            'inspection_date' => now()->toDateString(),
            'parts_payload' => json_encode($partsPayload),
            'detail_payload' => json_encode($detailPayload),
            'items' => [
                [
                    'description' => 'Item 1',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('ccr.manage.engine'))
            ->assertSessionHas('success');

        $report = CcrReport::query()->latest('id')->firstOrFail();
        $savedParts = (array) $report->parts_payload;
        $savedDetail = (array) $report->detail_payload;

        $this->assertSame($partsPayload['styles'], $savedParts['styles'] ?? []);
        $this->assertSame($partsPayload['notes'], $savedParts['notes'] ?? []);
        $this->assertSame($partsPayload['tools'], $savedParts['tools'] ?? []);
        $this->assertSame($detailPayload['main_rows'], $savedDetail['main_rows'] ?? []);
        $this->assertSame($partsPayload['ts'], (int) ($report->parts_payload_rev ?? 0));
        $this->assertSame($detailPayload['ts'], (int) ($report->detail_payload_rev ?? 0));
    }

    public function test_autosave_preserves_existing_styles_notes_tools_when_incoming_payload_is_empty_formatting(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $report = $this->makeEngineReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'rows' => [['qty' => '1', 'part_description' => 'Existing']],
                'styles' => ['0:0' => ['bold' => true]],
                'notes' => ['0:0' => 'Existing note'],
                'tools' => ['last_action' => 'format_toggle'],
                'ts' => 1700000004100,
            ],
            'parts_payload_rev' => 1700000004100,
        ]);

        $incomingPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'rows' => [['qty' => '1', 'part_description' => 'Existing']],
            'styles' => [],
            'notes' => [],
            'tools' => [],
            'ts' => 1700000004200,
        ];

        $response = $this->actingAs($user)->postJson(
            route('engine.worksheet.autosave', ['id' => $report->id]),
            [
                'parts_payload' => $incomingPayload,
                'parts_payload_rev' => 1700000004100,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('ok', true);

        $report->refresh();
        $savedParts = (array) $report->parts_payload;

        $this->assertSame(['bold' => true], data_get($savedParts, 'styles.0:0'));
        $this->assertSame('Existing note', data_get($savedParts, 'notes.0:0'));
        $this->assertSame('format_toggle', data_get($savedParts, 'tools.last_action'));
    }

    public function test_autosave_replays_styles_from_tools_when_styles_map_is_empty(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $report = $this->makeEngineReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'rows' => [['qty' => '1', 'part_description' => 'Base']],
                'styles' => [],
                'notes' => [],
                'tools' => [],
                'ts' => 1700000004300,
            ],
            'parts_payload_rev' => 1700000004300,
        ]);

        $incomingPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'rows' => [['qty' => '1', 'part_description' => 'Base']],
            'styles' => [],
            'notes' => [],
            'tools' => [
                'last_action' => 'format_toggle',
                'last_cell' => '0:1',
                'selected_range' => ['r1' => 0, 'r2' => 0, 'c1' => 1, 'c2' => 1],
                'format_prop' => 'bold',
                'format_value' => true,
            ],
            'ts' => 1700000004400,
        ];

        $response = $this->actingAs($user)->postJson(
            route('engine.worksheet.autosave', ['id' => $report->id]),
            [
                'parts_payload' => $incomingPayload,
                'parts_payload_rev' => 1700000004300,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('ok', true);

        $report->refresh();
        $savedParts = (array) $report->parts_payload;

        $this->assertSame(['bold' => true], data_get($savedParts, 'styles.0:1'));
        $this->assertSame('format_toggle', data_get($savedParts, 'tools.last_action'));
    }

    public function test_autosave_replays_note_from_tools_when_notes_map_is_empty(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $report = $this->makeEngineReport([
            'parts_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'rows' => [['qty' => '1', 'part_description' => 'Base']],
                'styles' => [],
                'notes' => [],
                'tools' => [],
                'ts' => 1700000004500,
            ],
            'parts_payload_rev' => 1700000004500,
        ]);

        $incomingPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'rows' => [['qty' => '1', 'part_description' => 'Base']],
            'styles' => [],
            'notes' => [],
            'tools' => [
                'last_action' => 'save_note',
                'last_cell' => '0:4',
                'note_text' => 'Catatan dari tools',
            ],
            'ts' => 1700000004600,
        ];

        $response = $this->actingAs($user)->postJson(
            route('engine.worksheet.autosave', ['id' => $report->id]),
            [
                'parts_payload' => $incomingPayload,
                'parts_payload_rev' => 1700000004500,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('ok', true);

        $report->refresh();
        $savedParts = (array) $report->parts_payload;

        $this->assertSame('Catatan dari tools', data_get($savedParts, 'notes.0:4'));
        $this->assertSame('save_note', data_get($savedParts, 'tools.last_action'));
    }

    public function test_store_uses_server_draft_payload_when_form_payload_is_incomplete(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $draftId = (string) Str::ulid();

        CcrDraft::query()->create([
            'id' => $draftId,
            'user_id' => (int) $user->id,
            'type' => 'engine',
            'client_key' => 'engine:test:draft',
            'draft_name' => 'Engine Draft Test',
            'parts_payload' => [
                'meta' => [
                    'template_key' => 'engine_blank',
                    'template_version' => 'v1',
                    'no_unit' => 'DR-01',
                ],
                'rows' => [
                    ['qty' => '1', 'part_description' => 'Draft Row', 'total' => '25000'],
                ],
                'styles' => [
                    '0:0' => ['bold' => true],
                ],
                'notes' => [
                    '0:0' => 'Draft note',
                ],
                'tools' => [
                    'last_action' => 'save_note',
                ],
                'ts' => 1700000005000,
            ],
            'detail_payload' => [
                'meta' => [
                    'template_key' => 'engine_blank',
                    'template_version' => 'v1',
                ],
                'main_rows' => [
                    ['component_desc' => 'Draft detail row'],
                ],
                'totals' => [
                    'sales_tax_percent' => 11,
                ],
                'ts' => 1700000005001,
            ],
            'last_saved_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('engine.store'), [
            'group_folder' => 'Engine',
            'component' => 'Engine Draft Fallback',
            'inspection_date' => now()->toDateString(),
            'draft_id' => $draftId,
            'draft_client_key' => 'engine:test:draft',
            // Simulasi bug create: form kirim payload minim (meta-only).
            'parts_payload' => json_encode([
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            ]),
            'detail_payload' => json_encode([
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            ]),
            'items' => [
                [
                    'description' => 'Item 1',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('ccr.manage.engine'))
            ->assertSessionHas('success');

        $report = CcrReport::query()->latest('id')->firstOrFail();
        $savedParts = (array) $report->parts_payload;
        $savedDetail = (array) $report->detail_payload;

        $this->assertSame('Draft Row', data_get($savedParts, 'rows.0.part_description'));
        $this->assertSame(['bold' => true], data_get($savedParts, 'styles.0:0'));
        $this->assertSame('Draft note', data_get($savedParts, 'notes.0:0'));
        $this->assertSame('Draft detail row', data_get($savedDetail, 'main_rows.0.component_desc'));
    }

    public function test_store_rejects_invalid_parts_payload_json(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->from(route('engine.create'))
            ->post(route('engine.store'), [
                'group_folder' => 'Engine',
                'component' => 'Engine Invalid JSON',
                'inspection_date' => now()->toDateString(),
                'parts_payload' => '{"meta":{"template_key":"engine_blank"}', // broken JSON
                'detail_payload' => json_encode([
                    'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                    'main_rows' => [['component_desc' => 'detail']],
                ]),
                'items' => [
                    [
                        'description' => 'Item 1',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('engine.create'))
            ->assertSessionHasErrors(['parts_payload']);
    }

    public function test_store_merges_sparse_incoming_formatting_with_richer_server_draft(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $draftId = (string) Str::ulid();

        CcrDraft::query()->create([
            'id' => $draftId,
            'user_id' => (int) $user->id,
            'type' => 'engine',
            'client_key' => 'engine:test:merge',
            'draft_name' => 'Engine Draft Merge',
            'parts_payload' => [
                'meta' => [
                    'template_key' => 'engine_blank',
                    'template_version' => 'v1',
                ],
                'rows' => [
                    ['qty' => '1', 'part_description' => 'Draft row'],
                ],
                'styles' => [
                    '0:3' => ['bold' => true],
                    '1:4' => ['italic' => true],
                ],
                'notes' => [
                    '0:3' => 'Draft note A',
                    '1:4' => 'Draft note B',
                ],
                'tools' => [
                    'last_action' => 'save_note',
                    'last_action_at' => 1700000010100,
                ],
                'ts' => 1700000010100,
            ],
            'detail_payload' => [
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'main_rows' => [['component_desc' => 'Draft detail row']],
                'totals' => ['sales_tax_percent' => 11],
            ],
            'last_saved_at' => now(),
        ]);

        $incomingParts = [
            'meta' => [
                'template_key' => 'engine_blank',
                'template_version' => 'v1',
            ],
            'rows' => [
                ['qty' => '1', 'part_description' => 'Incoming row'],
            ],
            'styles' => [
                '0:3' => ['bold' => true],
            ],
            'notes' => [
                '0:3' => 'Incoming note A',
            ],
            'tools' => [
                'last_action' => 'save_note',
                'last_action_at' => 1700000010000,
            ],
            'ts' => 1700000010000,
        ];

        $response = $this->actingAs($user)->post(route('engine.store'), [
            'group_folder' => 'Engine',
            'component' => 'Engine Merge Draft',
            'inspection_date' => now()->toDateString(),
            'draft_id' => $draftId,
            'draft_client_key' => 'engine:test:merge',
            'parts_payload' => json_encode($incomingParts),
            'detail_payload' => json_encode([
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'main_rows' => [['component_desc' => 'Incoming detail row']],
                'totals' => ['sales_tax_percent' => 11],
            ]),
            'items' => [
                [
                    'description' => 'Item 1',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('ccr.manage.engine'))
            ->assertSessionHas('success');

        $report = CcrReport::query()->latest('id')->firstOrFail();
        $savedParts = (array) $report->parts_payload;

        $this->assertSame(['bold' => true], data_get($savedParts, 'styles.0:3'));
        $this->assertSame(['italic' => true], data_get($savedParts, 'styles.1:4'));
        $this->assertSame('Incoming note A', data_get($savedParts, 'notes.0:3'));
        $this->assertSame('Draft note B', data_get($savedParts, 'notes.1:4'));
    }

    public function test_store_replays_note_from_tools_when_notes_map_is_empty(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $partsPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'rows' => [
                ['qty' => '1', 'part_description' => 'Row 1'],
            ],
            'styles' => [
                '0:4' => ['bold' => true],
            ],
            'notes' => [],
            'tools' => [
                'last_action' => 'save_note',
                'last_cell' => '0:4',
                'note_text' => 'Note dari tools create',
            ],
            'ts' => 1700000020000,
        ];

        $response = $this->actingAs($user)->post(route('engine.store'), [
            'group_folder' => 'Engine',
            'component' => 'Engine Replay Note',
            'inspection_date' => now()->toDateString(),
            'parts_payload' => json_encode($partsPayload),
            'detail_payload' => json_encode([
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'main_rows' => [['component_desc' => 'Detail 1']],
                'totals' => ['sales_tax_percent' => 11],
            ]),
            'items' => [
                [
                    'description' => 'Item 1',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('ccr.manage.engine'))
            ->assertSessionHas('success');

        $report = CcrReport::query()->latest('id')->firstOrFail();
        $savedParts = (array) $report->parts_payload;

        $this->assertSame('Note dari tools create', data_get($savedParts, 'notes.0:4'));
    }

    public function test_store_sanitizes_parts_formatting_payload_for_scale_safety(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $partsPayload = [
            'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
            'rows' => [
                [
                    'qty' => '1',
                    'part_number' => 'PN-1',
                    'part_description' => str_repeat('A', 500),
                ],
            ],
            'styles' => [
                '0:4' => ['bold' => true, 'align' => 'center', 'color' => 'dc2626', 'bg' => '#ffff00', 'junk' => 'x'],
                '999:1' => ['bold' => true],
                '1:99' => ['italic' => true],
            ],
            'notes' => [
                '0:4' => str_repeat('N', 1200),
                '700:1' => 'invalid key',
            ],
            'tools' => [
                'last_action' => 'save_note',
                'last_action_at' => 1700000025000,
                'last_cell' => '999:9',
                'selected_range' => ['r1' => 0, 'r2' => 9999, 'c1' => 0, 'c2' => 9999],
                'note_text' => str_repeat('T', 1200),
                'unknown' => ['bad' => true],
            ],
            'ts' => 1700000025000,
        ];

        $response = $this->actingAs($user)->post(route('engine.store'), [
            'group_folder' => 'Engine',
            'component' => 'Engine Sanitize',
            'inspection_date' => now()->toDateString(),
            'parts_payload' => json_encode($partsPayload),
            'detail_payload' => json_encode([
                'meta' => ['template_key' => 'engine_blank', 'template_version' => 'v1'],
                'main_rows' => [['component_desc' => 'Detail 1']],
                'totals' => ['sales_tax_percent' => 11],
            ]),
            'items' => [
                [
                    'description' => 'Item 1',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('ccr.manage.engine'))
            ->assertSessionHas('success');

        $report = CcrReport::query()->latest('id')->firstOrFail();
        $savedParts = (array) $report->parts_payload;

        $this->assertSame(['bold' => true, 'align' => 'center', 'color' => '#DC2626', 'bg' => '#FFFF00'], data_get($savedParts, 'styles.0:4'));
        $this->assertNull(data_get($savedParts, 'styles.999:1'));
        $this->assertNull(data_get($savedParts, 'styles.1:99'));
        $this->assertSame(1000, strlen((string) data_get($savedParts, 'notes.0:4')));
        $this->assertNull(data_get($savedParts, 'notes.700:1'));
        $this->assertNull(data_get($savedParts, 'tools.last_cell'));
        $this->assertSame(1000, strlen((string) data_get($savedParts, 'tools.note_text')));
        $this->assertSame(['r1' => 0, 'r2' => 499, 'c1' => 0, 'c2' => 9], data_get($savedParts, 'tools.selected_range'));
        $this->assertNull(data_get($savedParts, 'tools.unknown'));
    }

    private function makeEngineReport(array $overrides = []): CcrReport
    {
        $defaults = [
            'type' => 'engine',
            'group_folder' => 'Engine',
            'component' => 'Engine Test',
            'inspection_date' => now()->toDateString(),
            'make' => 'CAT',
            'model' => '3408',
            'sn' => 'SN-001',
            'customer' => 'PT Test',
            'parts_payload' => [],
            'detail_payload' => [],
            'template_key' => 'engine_blank',
            'template_version' => 1,
        ];

        return CcrReport::query()->create(array_merge($defaults, $overrides));
    }
}
