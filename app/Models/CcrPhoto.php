<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcrPhoto extends Model
{
    protected $fillable = [
        'ccr_item_id',
        'path',
    ];

    // setiap photo berubah -> item touched -> report touched (via CcrItem::$touches)
    protected $touches = ['item'];

    public function item()
    {
        return $this->belongsTo(\App\Models\CcrItem::class, 'ccr_item_id');
    }
}
