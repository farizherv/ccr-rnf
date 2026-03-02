<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebPushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint_hash',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'fail_count',
        'last_error',
        'last_seen_at',
        'disabled_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'fail_count' => 'integer',
        'last_seen_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

