<?php

namespace App\Jobs;

use App\Support\Notifications\InboxAlertDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchInboxAlertJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 4;
    public int $timeout = 120;

    public function __construct(public int $inboxMessageId)
    {
    }

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(InboxAlertDispatcher $dispatcher): void
    {
        $lockTtl = max(30, (int) config('ccr_notifications.lock_seconds', 120));
        $lockKey = 'ccr:notify:inbox:' . max(1, $this->inboxMessageId);
        $lock = Cache::lock($lockKey, $lockTtl);

        if (!$lock->get()) {
            return;
        }

        try {
            $dispatcher->dispatchFromInboxMessageId($this->inboxMessageId);
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('DispatchInboxAlertJob failed', [
            'inbox_message_id' => $this->inboxMessageId,
            'error' => $e->getMessage(),
        ]);
    }
}

