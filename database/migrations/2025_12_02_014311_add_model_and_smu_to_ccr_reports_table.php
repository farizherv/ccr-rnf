<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            
            // Tambah kolom model jika belum ada
            if (!Schema::hasColumn('ccr_reports', 'model')) {
                $table->string('model')->nullable()->after('make');
            }

            // Tambah kolom smu jika belum ada
            if (!Schema::hasColumn('ccr_reports', 'smu')) {
                $table->string('smu')->nullable()->after('sn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {

            if (Schema::hasColumn('ccr_reports', 'model')) {
                $table->dropColumn('model');
            }

            if (Schema::hasColumn('ccr_reports', 'smu')) {
                $table->dropColumn('smu');
            }
        });
    }
};
