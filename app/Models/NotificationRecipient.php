<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NotificationRecipient extends Model
{
    protected $fillable = [
        'email',
        'name',
        'is_active',
        'notify_waiting',
        'notify_approved',
        'notify_rejected',
        'last_notified_at',
        'last_error',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notify_waiting' => 'boolean',
        'notify_approved' => 'boolean',
        'notify_rejected' => 'boolean',
        'last_notified_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'waiting' => $query->where('notify_waiting', true),
            'approved' => $query->where('notify_approved', true),
            'rejected' => $query->where('notify_rejected', true),
            default => $query->where(function (Builder $inner): void {
                $inner->where('notify_waiting', true)
                    ->orWhere('notify_approved', true)
                    ->orWhere('notify_rejected', true);
            }),
        };
    }
}
