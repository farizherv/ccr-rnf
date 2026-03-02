<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('ccr_reports', 'parts_payload')) {
                $table->longText('parts_payload')->nullable();
            }

            if (!Schema::hasColumn('ccr_reports', 'detail_payload')) {
                $table->longText('detail_payload')->nullable();
            }

            if (!Schema::hasColumn('ccr_reports', 'template_key')) {
                $table->string('template_key', 80)->nullable();
            }

            if (!Schema::hasColumn('ccr_reports', 'template_version')) {
                $table->unsignedInteger('template_version')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            if (Schema::hasColumn('ccr_reports', 'template_version')) {
                $table->dropColumn('template_version');
            }
            if (Schema::hasColumn('ccr_reports', 'template_key')) {
                $table->dropColumn('template_key');
            }
            if (Schema::hasColumn('ccr_reports', 'detail_payload')) {
                $table->dropColumn('detail_payload');
            }
            if (Schema::hasColumn('ccr_reports', 'parts_payload')) {
                $table->dropColumn('parts_payload');
            }
        });
    }
};
