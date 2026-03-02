<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxMessage extends Model
{
    protected $table = 'inbox_messages';

    protected $fillable = [
        'to_user_id',
        'from_user_id',
        'type',
        'title',
        'message',
        'url',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'deleted_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}
