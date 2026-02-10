<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            // last-write-wins: simpan "client timestamp" terakhir yang diterapkan
            if (!Schema::hasColumn('ccr_reports', 'parts_payload_rev')) {
                $table->unsignedBigInteger('parts_payload_rev')->nullable()->after('parts_payload');
            }

            if (!Schema::hasColumn('ccr_reports', 'detail_payload_rev')) {
                $table->unsignedBigInteger('detail_payload_rev')->nullable()->after('detail_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ccr_reports', function (Blueprint $table) {
            if (Schema::hasColumn('ccr_reports', 'detail_payload_rev')) {
                $table->dropColumn('detail_payload_rev');
            }
            if (Schema::hasColumn('ccr_reports', 'parts_payload_rev')) {
                $table->dropColumn('parts_payload_rev');
            }
        });
    }
};
