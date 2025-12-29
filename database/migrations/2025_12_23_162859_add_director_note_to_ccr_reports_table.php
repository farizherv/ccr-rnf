<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->string('to_role')->nullable(); // admin/operator/director
            $table->unsignedBigInteger('from_user_id')->nullable();

            $table->string('type')->nullable();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('url')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['to_user_id', 'read_at']);
            $table->index(['to_role', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
