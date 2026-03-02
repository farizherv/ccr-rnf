<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_recipients')) {
            return;
        }

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->nullable()->unique();
            $table->string('name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_waiting')->default(true);
            $table->boolean('notify_approved')->default(true);
            $table->boolean('notify_rejected')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'updated_at'], 'notif_recipients_active_updated_idx');
            $table->index(['notify_waiting', 'notify_approved', 'notify_rejected'], 'notif_recipients_status_idx');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
    }
};
