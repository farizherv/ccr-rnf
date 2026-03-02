<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes on FK columns for faster eager loading
        $this->createIndexIfMissing('ccr_items', ['ccr_report_id'], 'ccr_items_report_id_idx');
        $this->createIndexIfMissing('ccr_photos', ['ccr_item_id'], 'ccr_photos_item_id_idx');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('ccr_photos', 'ccr_photos_item_id_idx');
        $this->dropIndexIfExists('ccr_items', 'ccr_items_report_id_idx');
    }

    private function createIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (\Throwable $e) {
            if (!str_contains(strtolower($e->getMessage()), 'already exists')
                && !str_contains(strtolower($e->getMessage()), 'duplicate key name')
                && !str_contains(strtolower($e->getMessage()), 'duplicate index')) {
                throw $e;
            }
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (\Throwable $e) {
            // Ignore if index doesn't exist
        }
    }
};
