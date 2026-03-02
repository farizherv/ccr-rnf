<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ccr_reports')) {
            return;
        }

        if (!Schema::hasColumn('ccr_reports', 'director_note')) {
            Schema::table('ccr_reports', function (Blueprint $table) {
                $table->text('director_note')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ccr_reports')) {
            return;
        }

        if (Schema::hasColumn('ccr_reports', 'director_note')) {
            Schema::table('ccr_reports', function (Blueprint $table) {
                $table->dropColumn('director_note');
            });
        }
    }
};
