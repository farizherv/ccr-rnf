<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemsMasterSeatSeeder extends Seeder
{
    public function run(): void
    {
        $path = resource_path('data/items_master_seat.php');
        if (!file_exists($path)) {
            $this->command?->warn('items_master_seat.php not found, skipping.');
            return;
        }

        $rows = include $path;
        if (!is_array($rows)) {
            $this->command?->warn('items_master_seat.php invalid, skipping.');
            return;
        }

        DB::table('items_master')->where('module', 'seat')->delete();

        $now = now();
        $batch = [];
        $count = 0;

        foreach ($rows as $r) {
            $item = trim((string)($r['item'] ?? ''));
            $pn = trim((string)($r['pn'] ?? ''));

            if ($item === '' && $pn === '') continue;

            $batch[] = [
                'module' => 'seat',
                'no' => isset($r['no']) ? (int)$r['no'] : null,
                'category' => isset($r['category']) && $r['category'] !== null ? (string)$r['category'] : null,
                'pn' => $pn !== '' ? $pn : null,
                'item' => $item !== '' ? $item : $pn,
                'purchase_price' => isset($r['purchase_price']) && $r['purchase_price'] !== null ? (int)$r['purchase_price'] : null,
                'sales_price' => isset($r['sales_price']) && $r['sales_price'] !== null ? (int)$r['sales_price'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                DB::table('items_master')->insert($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if (count($batch)) {
            DB::table('items_master')->insert($batch);
            $count += count($batch);
        }

        $this->command?->info("Seeded items_master (seat): {$count} rows");
    }
}
