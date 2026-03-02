<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('web_push_subscriptions')) {
            return;
        }

        Schema::create('web_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->char('endpoint_hash', 64);
            $table->text('endpoint');
            $table->string('public_key', 512);
            $table->string('auth_token', 512);
            $table->string('content_encoding', 32)->default('aesgcm');
            $table->string('user_agent', 512)->nullable();
            $table->unsignedSmallInteger('fail_count')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique('endpoint_hash');
            $table->index(['user_id', 'disabled_at']);
            $table->index(['disabled_at', 'updated_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_push_subscriptions');
    }
};

