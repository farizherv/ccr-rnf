<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            $table->dateTime('docx_generated_at')->nullable()->after('docx_path');
        });
    }

    public function down(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            $table->dropColumn('docx_generated_at');
        });
    }
};
