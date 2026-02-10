<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('items_master')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        // FULLTEXT umumnya tersedia di MySQL/MariaDB (InnoDB)
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        // Catatan: Kalau index sudah ada, migration akan error.
        // Ini patch baru untuk environment fresh (atau belum punya fulltext).
        Schema::table('items_master', function (Blueprint $table) {
            $table->fullText(['pn', 'item', 'category'], 'items_master_fulltext');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('items_master')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('items_master', function (Blueprint $table) {
            $table->dropFullText('items_master_fulltext');
        });
    }
};
