<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('action', 50);            // submit, approve, reject, create, update, delete, bulk_delete, restore
            $table->string('subject_type', 50);       // ccr_report, ccr_item, ccr_photo
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();          // extra context: {component, type, note, count, ...}
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'activity_logs_created_at_idx');
            $table->index(['user_id', 'created_at'], 'activity_logs_user_created_idx');
            $table->index(['subject_type', 'subject_id'], 'activity_logs_subject_idx');
            $table->index('action', 'activity_logs_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
