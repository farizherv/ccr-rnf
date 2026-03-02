<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccr_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ccr_report_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 20);
            $table->string('idempotency_key', 120);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->boolean('was_applied')->default(false);
            $table->string('note_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['ccr_report_id', 'action', 'idempotency_key'],
                'ccr_approval_actions_idem_unique'
            );
            $table->index(['ccr_report_id', 'created_at'], 'ccr_approval_actions_report_created_idx');
            $table->index(['actor_id', 'created_at'], 'ccr_approval_actions_actor_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccr_approval_actions');
    }
};
