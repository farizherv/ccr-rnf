<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CcrDraft extends Model
{
    use HasFactory;

    protected $table = 'ccr_drafts';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'client_key',
        'draft_name',
        'ccr_payload',
        'parts_payload',
        'detail_payload',
        'items_payload',
        'last_saved_at',
    ];

    protected $casts = [
        'ccr_payload' => 'array',
        'parts_payload' => 'array',
        'detail_payload' => 'array',
        'items_payload' => 'array',
        'last_saved_at' => 'datetime',
    ];
}
