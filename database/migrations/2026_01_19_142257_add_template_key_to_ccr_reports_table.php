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
        $table->string('template_key')->nullable()->after('type');
        $table->unsignedSmallInteger('template_version')->default(1)->after('template_key');
    });
}

public function down(): void
{
    Schema::table('ccr_reports', function (Blueprint $table) {
        $table->dropColumn(['template_key','template_version']);
    });
}

};
