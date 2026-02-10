<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemMaster extends Model
{
    protected $table = 'items_master';

    protected $fillable = [
        'module',
        'no',
        'category',
        'pn',
        'item',
        'purchase_price',
        'sales_price',
    ];

    protected $casts = [
        'no' => 'integer',
        'purchase_price' => 'integer',
        'sales_price' => 'integer',
    ];
}
