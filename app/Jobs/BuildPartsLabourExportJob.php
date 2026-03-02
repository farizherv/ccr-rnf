<?php

namespace App\Jobs;

use App\Http\Controllers\ExportPartsLabourController;
use App\Support\CcrHeavyJobBroker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BuildPartsLabourExportJob implements ShouldQueue
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
        return [5, 20, 60];
    }

    public function handle(
        CcrHeavyJobBroker $broker,
        ExportPartsLabourController $exporter,
    ): void {
        $lock = Cache::lock(
            $broker->executionLockKey('parts', $this->type, $this->reportId),
            $broker->executionLockSeconds()
        );

        if (!$lock->get()) {
            return;
        }

        try {
            $exporter->warmCachedExport($this->reportId, $this->type);
            $broker->markSuccess('parts', $this->type, $this->reportId);
        } catch (Throwable $e) {
            $broker->markFailure('parts', $this->type, $this->reportId, $e->getMessage());
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(Throwable $e): void
    {
        app(CcrHeavyJobBroker::class)->markFailure('parts', $this->type, $this->reportId, $e->getMessage());
    }
}
