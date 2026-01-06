<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ccr_agent_jobs', function (Blueprint $table) {
            $table->id();

            $table->string('group')->nullable();          // Seat/Engine/etc
            $table->string('component')->nullable();      // nama unit/component
            $table->dateTime('inspection_date')->nullable();

            $table->json('payload')->nullable();          // data lengkap job
            $table->string('status')->default('pending'); // pending|processing|done|failed
            $table->dateTime('locked_at')->nullable();
            $table->string('locked_by')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->json('result')->nullable();           // hasil: link docx/pdf, path synology, dll

            $table->timestamps();

            $table->index(['status', 'locked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccr_agent_jobs');
    }
};
