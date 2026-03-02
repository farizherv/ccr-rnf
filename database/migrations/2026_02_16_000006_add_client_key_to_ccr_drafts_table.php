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
        Schema::table('ccr_drafts', function (Blueprint $table) {
            $table->string('client_key', 120)->nullable()->after('type');
            $table->unique(['user_id', 'type', 'client_key'], 'ccr_drafts_user_type_client_unq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ccr_drafts', function (Blueprint $table) {
            $table->dropUnique('ccr_drafts_user_type_client_unq');
            $table->dropColumn('client_key');
        });
    }
};

