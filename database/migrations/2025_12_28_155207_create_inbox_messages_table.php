<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inbox_messages')) {
            return;
        }

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('to_user_id');
            $table->unsignedBigInteger('from_user_id')->nullable();

            $table->string('type', 60)->default('info');
            $table->string('title', 160);
            $table->text('message')->nullable();

            $table->string('url', 255)->nullable();

            $table->boolean('is_read')->default(false);

            $table->timestamps();

            $table->index(['to_user_id', 'is_read']);
            $table->index('type');

            $table->foreign('to_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('from_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
