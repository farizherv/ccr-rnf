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
        Schema::create('ccr_drafts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // engine | seat
            $table->string('draft_name', 190)->nullable();
            $table->json('ccr_payload')->nullable();
            $table->json('parts_payload')->nullable();
            $table->json('detail_payload')->nullable();
            $table->json('items_payload')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'last_saved_at'], 'ccr_drafts_user_type_saved_idx');
            $table->index(['user_id', 'updated_at'], 'ccr_drafts_user_updated_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ccr_drafts');
    }
};

