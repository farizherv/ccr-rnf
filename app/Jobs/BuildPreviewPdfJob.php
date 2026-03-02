<?php

namespace App\Jobs;

use App\Http\Controllers\ExportEngineController;
use App\Http\Controllers\ExportSeatController;
use App\Support\CcrHeavyJobBroker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BuildPreviewPdfJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public string $type,
        public int $reportId,
    ) {
    }

    public function backoff(): array
    {
        return [5, 15, 45];
    }

    public function handle(
        CcrHeavyJobBroker $broker,
        ExportEngineController $engineExporter,
        ExportSeatController $seatExporter,
    ): void {
        $lock = Cache::lock(
            $broker->executionLockKey('preview', $this->type, $this->reportId),
            $broker->executionLockSeconds()
        );

        if (!$lock->get()) {
            return;
        }

        try {
            if ($this->type === 'seat') {
                $seatExporter->warmPreviewPdf($this->reportId);
            } else {
                $engineExporter->warmPreviewPdf($this->reportId);
            }

            $broker->markSuccess('preview', $this->type, $this->reportId);
        } catch (Throwable $e) {
            $broker->markFailure('preview', $this->type, $this->reportId, $e->getMessage());
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(Throwable $e): void
    {
        app(CcrHeavyJobBroker::class)->markFailure('preview', $this->type, $this->reportId, $e->getMessage());
    }
}
