<?php

namespace Tests\Feature;

use App\Models\CcrReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
        $_ENV['CCR_HEAVY_WORD_INLINE_FALLBACK'] = 'false';
        $_SERVER['CCR_HEAVY_WORD_INLINE_FALLBACK'] = 'false';
        putenv('CCR_HEAVY_WORD_INLINE_FALLBACK=false');
        $_ENV['CCR_HEAVY_PARTS_INLINE_FALLBACK'] = 'false';
        $_SERVER['CCR_HEAVY_PARTS_INLINE_FALLBACK'] = 'false';
        putenv('CCR_HEAVY_PARTS_INLINE_FALLBACK=false');
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

    public function test_engine_word_download_returns_503_and_queues_job_when_docx_not_ready(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = $this->makeReport('engine');

        $response = $this->actingAs($admin)
            ->get(route('engine.export.word', ['id' => $report->id]));

        $response->assertStatus(503);
        $response->assertHeader('Retry-After');
        $this->assertStringContainsString('File Word sedang diproses di antrian', $response->getContent());

        $job = DB::table('jobs')->where('queue', 'ccr-heavy')->latest('id')->first();
        $this->assertNotNull($job);
        $this->assertStringContainsString('BuildWordExportJob', (string) $job->payload);
    }

    public function test_seat_word_download_returns_503_and_queues_job_when_docx_not_ready(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = $this->makeReport('seat');

        $response = $this->actingAs($admin)
            ->get(route('seat.export.word', ['id' => $report->id]));

        $response->assertStatus(503);
        $response->assertHeader('Retry-After');
        $this->assertStringContainsString('File Word sedang diproses di antrian', $response->getContent());

        $job = DB::table('jobs')->where('queue', 'ccr-heavy')->latest('id')->first();
        $this->assertNotNull($job);
        $this->assertStringContainsString('BuildWordExportJob', (string) $job->payload);
    }

    public function test_parts_labour_download_returns_503_and_queues_job_when_cache_not_ready(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = $this->makeReport('engine');

        $response = $this->actingAs($admin)
            ->get(route('engine.export.parts_labour', ['id' => $report->id]));

        $response->assertStatus(503);
        $response->assertHeader('Retry-After');
        $this->assertStringContainsString('Export Parts & Labour sedang diproses di antrian', $response->getContent());

        $job = DB::table('jobs')->where('queue', 'ccr-heavy')->latest('id')->first();
        $this->assertNotNull($job);
        $this->assertStringContainsString('BuildPartsLabourExportJob', (string) $job->payload);
    }

    private function makeReport(string $type): CcrReport
    {
        return CcrReport::query()->create([
            'type' => $type,
            'group_folder' => $type === 'seat' ? 'Operator Seat' : 'Engine',
            'component' => strtoupper($type) . ' QUEUE TEST',
            'inspection_date' => now()->toDateString(),
            'make' => 'CAT',
            'model' => '320',
            'sn' => 'SN-QUEUE-' . strtoupper($type),
            'smu' => '0',
            'customer' => 'PT TEST',
            'parts_payload' => [],
            'detail_payload' => [],
            'template_key' => $type === 'seat' ? 'seat_blank' : 'engine_blank',
            'template_version' => 1,
        ]);
    }
}
