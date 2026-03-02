<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIndexIfMissing(
            'ccr_reports',
            ['group_folder', 'deleted_at', 'created_at'],
            'ccr_reports_group_deleted_created_idx'
        );

        $this->createIndexIfMissing(
            'ccr_reports',
            ['type', 'deleted_at', 'created_at'],
            'ccr_reports_type_deleted_created_idx'
        );

        $this->createIndexIfMissing(
            'ccr_reports',
            ['approval_status', 'submitted_at'],
            'ccr_reports_approval_submitted_idx'
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('ccr_reports', 'ccr_reports_approval_submitted_idx');
        $this->dropIndexIfExists('ccr_reports', 'ccr_reports_type_deleted_created_idx');
        $this->dropIndexIfExists('ccr_reports', 'ccr_reports_group_deleted_created_idx');
    }

    private function createIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (\Throwable $e) {
            if (!$this->isDuplicateIndexException($e)) {
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
            if (!$this->isMissingIndexException($e)) {
                throw $e;
            }
        }
    }

    private function isDuplicateIndexException(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'already exists')
            || str_contains($message, 'duplicate key name')
            || str_contains($message, 'duplicate index');
    }

    private function isMissingIndexException(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'check that column/key exists')
            || str_contains($message, 'no such index')
            || str_contains($message, 'does not exist')
            || str_contains($message, 'unknown key name');
    }
};

