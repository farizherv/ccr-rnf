<?php

namespace Tests\Feature;

use App\Models\CcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class QueuedExportDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.default', 'database');
        $_ENV['CCR_HEAVY_QUEUE_ENABLED'] = 'true';
        $_SERVER['CCR_HEAVY_QUEUE_ENABLED'] = 'true';
        $_ENV['CCR_HEAVY_PREVIEW_INLINE_FALLBACK'] = 'false';
        $_SERVER['CCR_HEAVY_PREVIEW_INLINE_FALLBACK'] = 'false';
        putenv('CCR_HEAVY_PREVIEW_INLINE_FALLBACK=false');
    }

    public function test_preview_pdf_endpoint_queues_build_job_when_preview_not_ready(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = CcrReport::query()->create([
            'type' => 'engine',
            'group_folder' => 'Engine',
            'component' => 'ENG-QUEUE',
            'inspection_date' => now()->toDateString(),
            'make' => 'CAT',
            'model' => '320',
            'sn' => 'SN-QUEUE',
            'smu' => '0',
            'customer' => 'PT TEST',
            'parts_payload' => [],
            'detail_payload' => [],
            'template_key' => 'engine_blank',
            'template_version' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('engine.preview.pdf', ['id' => $report->id]))
            ->assertStatus(503);

        $this->assertDatabaseHas('jobs', [
            'queue' => 'ccr-heavy',
        ]);
    }
}
