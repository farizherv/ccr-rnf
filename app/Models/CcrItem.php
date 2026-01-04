<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcrPhoto extends Model
{
    protected $fillable = [
        'ccr_item_id',
        'path',
    ];

    // FOTO MILIK ITEM
    public function item()
    {
        return $this->belongsTo(\App\Models\CcrItem::class, 'ccr_item_id');
    }

    // setiap photo berubah -> item->updated_at naik
    // lalu item akan touch report (karena CcrItem punya $touches)
    protected $touches = ['item'];
}
