<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            $table->string('wo_pr')->nullable()->after('unit'); 
            // kamu bisa ganti after('unit') sesuai kolom di database
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            $table->dropColumn('wo_pr');
        });
    }
};
