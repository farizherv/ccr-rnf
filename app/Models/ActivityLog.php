<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'subject_type',
        'subject_id',
        'meta',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Human-readable action label.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'create'      => 'Buat Laporan',
            'update'      => 'Edit Laporan',
            'delete'      => 'Hapus Item',
            'delete_photo'=> 'Hapus Foto',
            'bulk_delete' => 'Hapus Massal',
            'submit'      => 'Submit ke Direktur',
            'approve'     => 'Approve',
            'reject'      => 'Reject',
            'restore'     => 'Restore',
            default       => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Badge color for the action.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'create'       => 'bg-green-100 text-green-800',
            'update'       => 'bg-blue-100 text-blue-800',
            'submit'       => 'bg-yellow-100 text-yellow-800',
            'approve'      => 'bg-emerald-100 text-emerald-800',
            'reject'       => 'bg-red-100 text-red-800',
            'delete', 'delete_photo', 'bulk_delete' => 'bg-red-100 text-red-700',
            'restore'      => 'bg-purple-100 text-purple-800',
            default        => 'bg-gray-100 text-gray-800',
        };
    }
}
