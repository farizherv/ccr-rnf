<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InboxMessage;

class PurgeInboxMessages extends Command
{
    protected $signature = 'inbox:purge';
    protected $description = 'Hapus notifikasi inbox yang lebih dari 7 hari (hard delete)';

    public function handle(): int
    {
        $deleted = InboxMessage::where('created_at', '<', now()->subDays(7))->delete();
        $this->info("Deleted {$deleted} inbox messages older than 7 days.");
        return self::SUCCESS;
    }
}
