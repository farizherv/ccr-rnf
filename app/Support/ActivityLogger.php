<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Log an activity.
     *
     * @param string     $action      e.g. 'create', 'update', 'delete', 'submit', 'approve', 'reject'
     * @param Model|null $subject     The subject model (CcrReport, CcrItem, CcrPhoto)
     * @param array      $meta        Extra context data
     */
    public static function log(string $action, ?Model $subject = null, array $meta = []): void
    {
        try {
            $user = auth()->user();

            ActivityLog::create([
                'user_id'      => $user?->id,
                'user_name'    => $user?->name ?? $user?->username ?? 'System',
                'action'       => $action,
                'subject_type' => $subject ? self::subjectType($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'meta'         => !empty($meta) ? $meta : null,
                'ip_address'   => request()->ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let logging break the main flow
            report($e);
        }
    }

    /**
     * Resolve a short subject type string from the model.
     */
    private static function subjectType(Model $model): string
    {
        return match (true) {
            $model instanceof \App\Models\CcrReport => 'ccr_report',
            $model instanceof \App\Models\CcrItem   => 'ccr_item',
            $model instanceof \App\Models\CcrPhoto  => 'ccr_photo',
            default => class_basename($model),
        };
    }

    /**
     * Purge logs older than the given number of days.
     */
    public static function purge(int $days = 90): int
    {
        return ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
