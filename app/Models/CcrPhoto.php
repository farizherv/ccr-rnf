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
    protected $touches = ['item'];

    public function item()
    {
        return $this->belongsTo(CcrItem::class, 'ccr_item_id');
    }

}
