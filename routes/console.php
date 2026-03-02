<?php

use App\Models\CcrDraft;
use App\Models\CcrReport;
use App\Models\ActivityLog;
use App\Models\WebPushSubscription;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

// ✅ Scheduler taruh di sini (Laravel 11/12)
Schedule::command('inbox:purge')->daily();

// Activity log purge (90 days)
Schedule::call(function () {
    $days = max(30, (int) env('ACTIVITY_LOG_RETENTION_DAYS', 90));
    ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
})
    ->dailyAt('04:00')
    ->name('ccr:activity-log-purge')
    ->withoutOverlapping();

$enableHorizonSnapshot = filter_var(env('SCHEDULE_HORIZON_SNAPSHOT', false), FILTER_VALIDATE_BOOLEAN);
if ($enableHorizonSnapshot) {
    Schedule::command('horizon:snapshot')->everyFiveMinutes();
}

$enableBackups = filter_var(env('SCHEDULE_BACKUPS', false), FILTER_VALIDATE_BOOLEAN);
if ($enableBackups) {
    Schedule::command('backup:clean')->dailyAt('01:30');
    Schedule::command('backup:run --only-db')->dailyAt('02:00');
    Schedule::command('backup:run --only-files')->dailyAt('02:30');
    Schedule::command('backup:monitor')->dailyAt('03:00');
}

$enableDraftPrune = filter_var(env('SCHEDULE_DRAFT_PRUNE', true), FILTER_VALIDATE_BOOLEAN);
if ($enableDraftPrune) {
    Schedule::call(function () {
        $retentionDays = max(7, (int) env('CCR_DRAFT_RETENTION_DAYS', 30));
        CcrDraft::query()
            ->where('updated_at', '<', now()->subDays($retentionDays))
            ->delete();
    })
        ->dailyAt('03:20')
        ->name('ccr:drafts-prune')
        ->withoutOverlapping();
}

$enableTrashPurge = filter_var(env('SCHEDULE_TRASH_PURGE', true), FILTER_VALIDATE_BOOLEAN);
if ($enableTrashPurge) {
    Schedule::call(function () {
        CcrReport::query()
            ->onlyTrashed()
            ->whereNotNull('purge_at')
            ->where('purge_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($reports) {
                foreach ($reports as $report) {
                    try {
                        $report->loadMissing(['photos', 'items']);

                        foreach ($report->photos as $photo) {
                            $path = trim((string) ($photo->path ?? ''));
                            if ($path !== '') {
                                Storage::disk('public')->delete($path);
                            }
                        }

                        $report->photos()->delete();
                        $report->items()->delete();
                        $report->forceDelete();
                    } catch (\Throwable $e) {
                        Log::warning('ccr:trash-purge failed for report', [
                            'report_id' => (int) ($report->id ?? 0),
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            });
    })
        ->dailyAt('03:40')
        ->name('ccr:trash-purge')
        ->withoutOverlapping();
}

$enableWebPushPrune = filter_var(env('SCHEDULE_WEB_PUSH_PRUNE', true), FILTER_VALIDATE_BOOLEAN);
if ($enableWebPushPrune) {
    Schedule::call(function () {
        $retentionDays = max(30, (int) env('WEB_PUSH_SUBSCRIPTION_RETENTION_DAYS', 90));
        WebPushSubscription::query()
            ->whereNotNull('disabled_at')
            ->where('disabled_at', '<', now()->subDays($retentionDays))
            ->delete();
    })
        ->dailyAt('03:50')
        ->name('ccr:webpush-prune')
        ->withoutOverlapping();
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
