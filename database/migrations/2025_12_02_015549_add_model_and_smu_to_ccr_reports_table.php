<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('ccr_reports', 'smu')) {
                $table->string('smu')->nullable()->after('sn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            if (Schema::hasColumn('ccr_reports', 'smu')) {
                $table->dropColumn('smu');
            }
        });
    }
};
