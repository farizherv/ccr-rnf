<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('id');
            $table->index('created_by', 'ccr_reports_created_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            $table->dropIndex('ccr_reports_created_by_idx');
            $table->dropColumn('created_by');
        });
    }
};
