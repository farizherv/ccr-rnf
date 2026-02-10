<?php

namespace App\Http\Controllers;

use App\Models\ItemMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Cache\TaggableStore;

class ItemsMasterController extends Controller
{
    /**
     * Simple typeahead search untuk master items.
     * Query params:
     * - module: seat|engine (default: seat)
     * - q: search text
     */
    public function search(Request $request)
    {
        $module = strtolower(trim((string) $request->query('module', 'seat')));
        if ($module === '') $module = 'seat';

        $q = trim((string) $request->query('q', ''));
        // Hardening: batasi panjang input (anti payload aneh / berat)
        if (strlen($q) > 80) $q = substr($q, 0, 80);
        if ($q === '') {
            return response()->json(['items' => []]);
        }

        // Normalisasi: angka/PN biasanya tanpa spasi
        $qPn = preg_replace('/\s+/', '', $q);

        $limit = (int) $request->query('limit', 20);
        if ($limit < 1) $limit = 20;
        if ($limit > 50) $limit = 50;

        // ===== CACHING (5-10 menit) =====
        // - Gunakan cache tags jika tersedia (Redis/Memcached). Kalau tidak, fallback cache biasa.
        $cacheKey = 'items_master_search:' . $module . ':' . md5(json_encode([$q, $qPn, $limit]));
        $ttl = now()->addMinutes(10);

        $store = Cache::getStore();
        $cache = ($store instanceof TaggableStore)
            ? Cache::tags(['items_master', $module])
            : Cache::store(config('cache.default'));

        $items = $cache->remember($cacheKey, $ttl, function () use ($module, $q, $qPn, $limit) {
            $driver = DB::connection()->getDriverName();
            $useFullText = in_array($driver, ['mysql', 'mariadb'], true) && strlen($q) >= 3;

            $qb = ItemMaster::query()->where('module', $module);

            if ($useFullText) {
                // boolean query: tiap token dikasih wildcard suffix (*)
                $tokens = preg_split('/\s+/', trim($q));
                $tokens = array_values(array_filter(array_map(function ($t) {
                    $t = preg_replace('/[^A-Za-z0-9\-_.]/', '', (string) $t);
                    return $t;
                }, $tokens)));

                // Kalau token hasilnya kosong, fallback ke LIKE.
                if (!empty($tokens)) {
                    $boolean = implode(' ', array_map(fn($t) => $t . '*', $tokens));

                    // FULLTEXT bisa error kalau index belum ada -> fallback LIKE.
                    try {
                        // select score utk sorting (lebih relevan)
                        $qb->selectRaw(
                            "id, no, category, pn, item, purchase_price, sales_price, MATCH(pn, item, category) AGAINST(? IN BOOLEAN MODE) AS score",
                            [$boolean]
                        );

                        $qb->where(function ($w) use ($q, $qPn, $boolean) {
                            $w->where('pn', 'like', $qPn . '%')
                              ->orWhere('pn', 'like', '%' . $qPn . '%')
                              ->orWhereRaw('MATCH(pn, item, category) AGAINST(? IN BOOLEAN MODE)', [$boolean])
                              ->orWhere('item', 'like', '%' . $q . '%')
                              ->orWhere('category', 'like', '%' . $q . '%');
                        });

                        $qb->orderByRaw(
                            "CASE WHEN pn = ? THEN 0 WHEN pn LIKE ? THEN 1 WHEN pn LIKE ? THEN 2 ELSE 3 END",
                            [$qPn, $qPn . '%', '%' . $qPn . '%']
                        );
                        $qb->orderByDesc('score');
                        $qb->orderBy('item');

                        return $qb->limit($limit)->get()->map(function ($it) {
                            return [
                                'id' => $it->id,
                                'no' => $it->no,
                                'category' => $it->category,
                                'pn' => $it->pn,
                                'item' => $it->item,
                                'purchase_price' => $it->purchase_price,
                                'sales_price' => $it->sales_price,
                            ];
                        })->all();
                    } catch (\Throwable $e) {
                        // ignore -> fallback LIKE
                    }
                }
            }

            // ===== fallback LIKE =====
            return $qb
                ->where(function ($w) use ($q, $qPn) {
                    $w->where('pn', 'like', $qPn . '%')
                      ->orWhere('pn', 'like', '%' . $qPn . '%')
                      ->orWhere('item', 'like', '%' . $q . '%')
                      ->orWhere('category', 'like', '%' . $q . '%');
                })
                ->orderByRaw("CASE WHEN pn LIKE ? THEN 0 WHEN pn LIKE ? THEN 1 ELSE 2 END", [$qPn.'%', '%'.$qPn.'%'])
                ->orderBy('item')
                ->limit($limit)
                ->get(['id','no','category','pn','item','purchase_price','sales_price'])
                ->toArray();
        });

        return response()->json([
            'items' => $items,
        ]);
    }
}
