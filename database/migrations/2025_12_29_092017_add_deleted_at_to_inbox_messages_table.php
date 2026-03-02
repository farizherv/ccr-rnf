<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('inbox_messages') || Schema::hasColumn('inbox_messages', 'deleted_at')) {
            return;
        }

        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('inbox_messages') || !Schema::hasColumn('inbox_messages', 'deleted_at')) {
            return;
        }

        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
