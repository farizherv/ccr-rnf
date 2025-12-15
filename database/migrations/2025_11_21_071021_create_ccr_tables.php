<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccr_reports', function (Blueprint $table) {
            $table->id();
            $table->string('group_folder');    // Engine, Transmission, etc
            $table->string('component');
            $table->string('unit');
            $table->string('job_no')->nullable();
            $table->string('sn')->nullable();
            $table->string('customer')->nullable();
            $table->string('make')->nullable();
            $table->string('reff_wo')->nullable();
            $table->date('inspection_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('docx_path')->nullable();
            $table->timestamps();
        });

        Schema::create('ccr_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ccr_report_id')->constrained()->onDelete('cascade');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ccr_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ccr_item_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccr_photos');
        Schema::dropIfExists('ccr_items');
        Schema::dropIfExists('ccr_reports');
    }
};
