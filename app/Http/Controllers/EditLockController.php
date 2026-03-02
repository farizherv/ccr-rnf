<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class EditLockController extends Controller
{
    private const LOCK_TTL = 300; // 5 minutes

    /**
     * Check who is editing a report. Returns lock info.
     */
    public static function checkLock(int $reportId): ?array
    {
        $lock = Cache::get("ccr:editing:{$reportId}");
        if (!$lock) return null;

        // Lock is still active
        return $lock;
    }

    /**
     * Acquire a lock for the current user.
     */
    public static function acquireLock(int $reportId): array
    {
        $user = auth()->user();
        $lock = [
            'user_id'   => (int) $user->id,
            'user_name' => $user->name ?? $user->username ?? 'Unknown',
            'locked_at' => now()->toIso8601String(),
        ];

        Cache::put("ccr:editing:{$reportId}", $lock, self::LOCK_TTL);

        return $lock;
    }

    /**
     * Heartbeat — refresh the lock TTL.
     */
    public function heartbeat(Request $request, int $id)
    {
        $existing = Cache::get("ccr:editing:{$id}");

        // Only the lock owner can refresh
        if ($existing && (int) ($existing['user_id'] ?? 0) === (int) auth()->id()) {
            Cache::put("ccr:editing:{$id}", $existing, self::LOCK_TTL);
            return response()->json(['ok' => true, 'ttl' => self::LOCK_TTL]);
        }

        // If no lock exists, acquire it
        if (!$existing) {
            self::acquireLock($id);
            return response()->json(['ok' => true, 'acquired' => true, 'ttl' => self::LOCK_TTL]);
        }

        // Someone else holds the lock
        return response()->json([
            'ok' => false,
            'locked_by' => $existing['user_name'] ?? 'Unknown',
        ], 423);
    }

    /**
     * Release — clear the lock.
     */
    public function release(Request $request, int $id)
    {
        $existing = Cache::get("ccr:editing:{$id}");

        // Only the lock owner (or if no lock exists) can release
        if (!$existing || (int) ($existing['user_id'] ?? 0) === (int) auth()->id()) {
            Cache::forget("ccr:editing:{$id}");
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false, 'message' => 'Bukan pemilik lock.'], 403);
    }
}
