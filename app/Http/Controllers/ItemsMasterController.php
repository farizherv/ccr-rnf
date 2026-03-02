<?php

namespace App\Http\Controllers;

use App\Models\ItemMaster;
use Illuminate\Cache\TaggableStore;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ItemsMasterController extends Controller
{
    private const SEAT_SYNC_MAX_ROWS = 3000;
    private const SEAT_SYNC_MAX_PAYLOAD_BYTES = 8 * 1024 * 1024;
    private const SEAT_SYNC_MAX_PHOTOS_PER_ROW = 10;
    private const SEAT_SYNC_MAX_TOTAL_UPLOADS = 400;
    private const SEAT_SYNC_MAX_FILE_BYTES = 8 * 1024 * 1024;
    private const SEAT_SYNC_LOCK_SECONDS = 20;
    private const SEAT_SYNC_MAX_CATEGORY_CHARS = 80;
    private const SEAT_SYNC_MAX_PN_CHARS = 80;
    private const SEAT_SYNC_MAX_ITEM_CHARS = 255;

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

    /**
     * Sinkron master items seat (global) dari tab Items.
     * - full sync: data lama yang tidak ada di payload akan dihapus
     * - dukung multi foto per item (max 10)
     */
    public function syncSeat(Request $request)
    {
        $rawRows = $request->input('rows_json', '[]');
        $rowsPayloadBytes = $this->payloadByteSize($rawRows);
        if ($rowsPayloadBytes > self::SEAT_SYNC_MAX_PAYLOAD_BYTES) {
            return response()->json([
                'ok' => false,
                'message' => 'Payload rows terlalu besar (' . number_format($rowsPayloadBytes / 1024 / 1024, 2) . ' MB). Batas ' . number_format(self::SEAT_SYNC_MAX_PAYLOAD_BYTES / 1024 / 1024, 2) . ' MB.',
            ], 422);
        }

        if (is_array($rawRows)) {
            $rowsInput = $rawRows;
        } else {
            $decoded = json_decode((string) $rawRows, true);
            if (!is_array($decoded)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'rows_json tidak valid',
                ], 422);
            }
            $rowsInput = $decoded;
        }

        if (count($rowsInput) > self::SEAT_SYNC_MAX_ROWS) {
            return response()->json([
                'ok' => false,
                'message' => 'Terlalu banyak row (maks ' . self::SEAT_SYNC_MAX_ROWS . ')',
            ], 422);
        }

        $rows = $this->sanitizeSeatRows($rowsInput);
        $fullSync = filter_var($request->input('full_sync', true), FILTER_VALIDATE_BOOLEAN);
        $uploadFilesFlat = $this->normalizeUploadedImageFiles($request->file('photos'));
        $actualUploadCount = count($uploadFilesFlat);
        $expectedUploadCount = max(0, (int) $request->input('expected_upload_count', 0));
        if ($expectedUploadCount > $actualUploadCount) {
            return response()->json([
                'ok' => false,
                'message' => 'Sebagian foto tidak diterima server (dipilih: ' . $expectedUploadCount . ', diterima: ' . $actualUploadCount . '). Kemungkinan batas max_file_uploads / post_max_size terlalu kecil.',
            ], 422);
        }
        if ($actualUploadCount > self::SEAT_SYNC_MAX_TOTAL_UPLOADS) {
            return response()->json([
                'ok' => false,
                'message' => 'Jumlah upload foto terlalu banyak dalam satu sync (maks ' . self::SEAT_SYNC_MAX_TOTAL_UPLOADS . ' file).',
            ], 422);
        }
        foreach ($uploadFilesFlat as $file) {
            if (!$this->isAcceptableUploadFile($file)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Upload foto tidak valid atau melebihi batas ukuran per file.',
                ], 422);
            }
        }

        $lockKey = 'items_master:seat:sync:lock';
        if (!Cache::add($lockKey, (string) microtime(true), now()->addSeconds(self::SEAT_SYNC_LOCK_SECONDS))) {
            return response()->json([
                'ok' => false,
                'message' => 'Sinkronisasi sedang diproses. Coba lagi beberapa detik.',
            ], 429);
        }

        try {
            $fingerprint = sha1((string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $fingerprintKey = 'items_master:seat:sync:last_fingerprint';

            if ($actualUploadCount === 0 && $fingerprint !== '' && hash_equals((string) Cache::get($fingerprintKey, ''), $fingerprint)) {
                $all = ItemMaster::query()
                    ->where('module', 'seat')
                    ->orderByRaw('CASE WHEN no IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('no')
                    ->orderBy('item')
                    ->get(['id', 'no', 'category', 'pn', 'item', 'purchase_price', 'sales_price', 'photo_paths']);

                $currentRows = $this->sanitizeSeatRows($this->mapRowsForClient($all->all()));
                $currentFingerprint = sha1((string) json_encode($currentRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                if (hash_equals($currentFingerprint, $fingerprint)) {
                    return response()->json([
                        'ok' => true,
                        'rows' => $this->mapRowsForClient($all->all()),
                        'skipped' => true,
                    ]);
                }
            }

            DB::transaction(function () use ($request, $rows, $fullSync) {
                $keptIds = [];

                foreach ($rows as $row) {
                    $uid = (string) ($row['uid'] ?? '');
                    if ($uid === '') continue;

                    $id = $this->parseItemIdFromUid($uid);

                    $model = null;
                    if ($id !== null) {
                        $model = ItemMaster::query()
                            ->where('module', 'seat')
                            ->whereKey($id)
                            ->first();
                    }
                    if (!$model) {
                        $model = new ItemMaster();
                        $model->module = 'seat';
                    }

                    $model->no = (int) ($row['no'] ?? 0) ?: null;
                    $model->category = (string) ($row['category'] ?? '');
                    $model->pn = (string) ($row['pn'] ?? '');
                    $model->item = (string) ($row['item'] ?? '');
                    $model->purchase_price = ((string) ($row['purchase_price'] ?? '') !== '')
                        ? (int) $row['purchase_price']
                        : null;
                    $model->sales_price = ((string) ($row['sales_price'] ?? '') !== '')
                        ? (int) $row['sales_price']
                        : null;

                    $existingFromRow = $this->normalizePhotoPaths($row['photo_paths'] ?? [], self::SEAT_SYNC_MAX_PHOTOS_PER_ROW);
                    $existingFromDb = $this->normalizePhotoPaths($model->photo_paths ?? []);

                    $uploaded = [];
                    $uploadFiles = $request->file("photos.$uid", []);
                    if (!is_array($uploadFiles)) $uploadFiles = [$uploadFiles];

                    $availableSlots = max(0, self::SEAT_SYNC_MAX_PHOTOS_PER_ROW - count($existingFromRow));
                    foreach ($uploadFiles as $file) {
                        if ($availableSlots <= 0) break;
                        if (!$this->isAcceptableUploadFile($file)) continue;
                        $stored = $file->store('items_master/seat', 'public');
                        if ($stored) {
                            $uploaded[] = $stored;
                            $availableSlots--;
                        }
                    }

                    $nextPaths = array_values(array_unique(array_merge($existingFromRow, $uploaded)));
                    if (count($nextPaths) > self::SEAT_SYNC_MAX_PHOTOS_PER_ROW) {
                        $nextPaths = array_slice($nextPaths, 0, self::SEAT_SYNC_MAX_PHOTOS_PER_ROW);
                    }

                    $toDelete = array_diff($existingFromDb, $nextPaths);
                    foreach ($toDelete as $path) {
                        if (is_string($path) && $path !== '' && !preg_match('#^https?://#i', $path)) {
                            Storage::disk('public')->delete($path);
                        }
                    }

                    $model->photo_paths = $nextPaths;
                    $model->save();

                    $keptIds[] = (int) $model->id;
                }

                if ($fullSync) {
                    $toDeleteQuery = ItemMaster::query()->where('module', 'seat');
                    if (!empty($keptIds)) {
                        $toDeleteQuery->whereNotIn('id', $keptIds);
                    }

                    $toDeleteRows = $toDeleteQuery->get(['id', 'photo_paths']);
                    foreach ($toDeleteRows as $row) {
                        $paths = $this->normalizePhotoPaths($row->photo_paths ?? []);
                        foreach ($paths as $path) {
                            if (is_string($path) && $path !== '' && !preg_match('#^https?://#i', $path)) {
                                Storage::disk('public')->delete($path);
                            }
                        }
                    }

                    if (empty($keptIds)) {
                        ItemMaster::query()->where('module', 'seat')->delete();
                    } else {
                        ItemMaster::query()
                            ->where('module', 'seat')
                            ->whereNotIn('id', $keptIds)
                            ->delete();
                    }
                }
            });

            Cache::put($fingerprintKey, $fingerprint, now()->addMinutes(10));

            $all = ItemMaster::query()
                ->where('module', 'seat')
                ->orderByRaw('CASE WHEN no IS NULL THEN 1 ELSE 0 END')
                ->orderBy('no')
                ->orderBy('item')
                ->get(['id', 'no', 'category', 'pn', 'item', 'purchase_price', 'sales_price', 'photo_paths']);

            $rows = $this->mapRowsForClient($all->all());

            $store = Cache::getStore();
            if ($store instanceof TaggableStore) {
                Cache::tags(['items_master', 'seat'])->flush();
            }

            return response()->json([
                'ok' => true,
                'rows' => $rows,
            ]);
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function sanitizeSeatRows(array $rows): array
    {
        $out = [];
        $usedUid = [];

        foreach ($rows as $i => $row) {
            if (count($out) >= self::SEAT_SYNC_MAX_ROWS) {
                break;
            }
            if (!is_array($row)) continue;

            $uid = trim((string) ($row['uid'] ?? ''));
            if ($uid === '') $uid = 'si_' . ($i + 1);
            $uid = preg_replace('/[^A-Za-z0-9_\-]/', '_', $uid);
            if ($uid === '') $uid = 'si_' . ($i + 1);

            if (isset($usedUid[$uid])) $uid .= '_' . ($i + 1);
            $usedUid[$uid] = true;

            $clean = [
                'uid' => $uid,
                'no' => preg_replace('/[^\d]/', '', (string) ($row['no'] ?? ($i + 1))),
                'category' => $this->limitTextLength(trim((string) ($row['category'] ?? '')), self::SEAT_SYNC_MAX_CATEGORY_CHARS),
                'pn' => $this->limitTextLength(trim((string) ($row['pn'] ?? '')), self::SEAT_SYNC_MAX_PN_CHARS),
                'item' => $this->limitTextLength(trim((string) ($row['item'] ?? $row['items'] ?? '')), self::SEAT_SYNC_MAX_ITEM_CHARS),
                'purchase_price' => preg_replace('/[^\d]/', '', (string) ($row['purchase_price'] ?? '')),
                'sales_price' => preg_replace('/[^\d]/', '', (string) ($row['sales_price'] ?? '')),
                'photo_paths' => $this->normalizePhotoPaths($row['photo_paths'] ?? [], self::SEAT_SYNC_MAX_PHOTOS_PER_ROW),
            ];

            $hasNewUploads = filter_var($row['_has_new_photos'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $hasValue = (
                $clean['category'] !== '' ||
                $clean['pn'] !== '' ||
                $clean['item'] !== '' ||
                $clean['purchase_price'] !== '' ||
                $clean['sales_price'] !== '' ||
                !empty($clean['photo_paths']) ||
                $hasNewUploads
            );

            if (!$hasValue) continue;
            if ($clean['no'] === '') $clean['no'] = (string) ($i + 1);

            $out[] = $clean;
        }

        return $out;
    }

    private function normalizePhotoPaths($paths, ?int $limit = null): array
    {
        if (!is_array($paths)) return [];

        $out = [];
        foreach ($paths as $p) {
            $path = '';
            if (is_string($p)) {
                $path = trim($p);
            } elseif (is_array($p)) {
                $path = trim((string) ($p['path'] ?? $p['url'] ?? ''));
            }

            if ($path === '') continue;
            if (strlen($path) > 1024) continue;

            if (!preg_match('#^https?://#i', $path)) {
                $path = ltrim($path, '/');
                if (str_starts_with($path, 'storage/')) $path = substr($path, 8);
                if (str_starts_with($path, 'public/')) $path = substr($path, 7);
                if (str_contains($path, '..')) continue;
            }

            $out[] = $path;
        }

        $normalized = array_values(array_unique(array_filter($out, fn ($x) => is_string($x) && trim($x) !== '')));
        if ($limit !== null && $limit > 0 && count($normalized) > $limit) {
            $normalized = array_slice($normalized, 0, $limit);
        }

        return $normalized;
    }

    private function parseItemIdFromUid(string $uid): ?int
    {
        if (preg_match('/^im_(\d+)$/', $uid, $m)) {
            $id = (int) $m[1];
            return $id > 0 ? $id : null;
        }

        if (preg_match('/^(\d+)$/', $uid, $m)) {
            $id = (int) $m[1];
            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function mapRowsForClient(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $paths = $this->normalizePhotoPaths($row->photo_paths ?? [], self::SEAT_SYNC_MAX_PHOTOS_PER_ROW);

            $out[] = [
                'uid' => 'im_' . (int) $row->id,
                'no' => (string) ($row->no ?? ($i + 1)),
                'category' => (string) ($row->category ?? ''),
                'pn' => (string) ($row->pn ?? ''),
                'item' => (string) ($row->item ?? ''),
                'purchase_price' => preg_replace('/[^\d]/', '', (string) ($row->purchase_price ?? '')),
                'sales_price' => preg_replace('/[^\d]/', '', (string) ($row->sales_price ?? '')),
                'photo_paths' => $paths,
            ];
        }
        return $out;
    }

    private function normalizeUploadedImageFiles(mixed $value): array
    {
        $out = [];
        $queue = [];

        if (is_array($value)) {
            $queue = array_values($value);
        } elseif ($value instanceof UploadedFile) {
            $queue = [$value];
        }

        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($current instanceof UploadedFile) {
                if ($current->isValid()) {
                    $out[] = $current;
                }
                continue;
            }

            if (is_array($current)) {
                foreach ($current as $nested) {
                    $queue[] = $nested;
                }
            }
        }

        return $out;
    }

    private function isAcceptableUploadFile(mixed $file): bool
    {
        if (!$file instanceof UploadedFile) {
            return false;
        }
        if (!$file->isValid()) {
            return false;
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::SEAT_SYNC_MAX_FILE_BYTES) {
            return false;
        }

        $mime = strtolower(trim((string) $file->getMimeType()));
        if ($mime !== '' && !str_starts_with($mime, 'image/')) {
            return false;
        }

        return true;
    }

    private function payloadByteSize(mixed $raw): int
    {
        if ($raw === null) return 0;
        if (is_string($raw)) return strlen($raw);
        if (is_array($raw)) {
            $json = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($json) ? strlen($json) : 0;
        }

        return strlen((string) $raw);
    }

    private function limitTextLength(string $value, int $maxChars): string
    {
        $value = trim($value);
        if ($value === '' || $maxChars <= 0) return '';

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) > $maxChars) {
                return mb_substr($value, 0, $maxChars);
            }
            return $value;
        }

        if (strlen($value) > $maxChars) {
            return substr($value, 0, $maxChars);
        }

        return $value;
    }
}
