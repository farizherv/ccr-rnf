<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('items_master')) {
            return;
        }

        if (!Schema::hasColumn('items_master', 'photo_paths')) {
            Schema::table('items_master', function (Blueprint $table) {
                $table->longText('photo_paths')->nullable()->after('sales_price');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('items_master')) {
            return;
        }

        if (Schema::hasColumn('items_master', 'photo_paths')) {
            Schema::table('items_master', function (Blueprint $table) {
                $table->dropColumn('photo_paths');
            });
        }
    }
};
