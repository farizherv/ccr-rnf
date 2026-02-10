<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\ItemsMasterSeatSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Master items (seat) dari excel (resources/data/items_master_seat.php)
        // ✅ Hardening: jangan jalankan seeder default di production secara otomatis.
        // Jalankan manual saat perlu:
        //   php artisan db:seed --class=ItemsMasterSeatSeeder
        if (!app()->environment('production')) {
            $this->call(ItemsMasterSeatSeeder::class);
        }

        // NOTE: jangan auto-create user dummy di seeder default (rawan nyangkut di staging/prod).
        // User::factory(10)->create();
    }
}
