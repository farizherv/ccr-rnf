<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcrItem extends Model
{
    protected $fillable = [
        'ccr_report_id',
        'description',
    ];

    // setiap item berubah, report->updated_at ikut naik
    protected $touches = ['report'];

    public function report()
    {
        return $this->belongsTo(\App\Models\CcrReport::class, 'ccr_report_id');
    }

    public function photos()
    {
        return $this->hasMany(\App\Models\CcrPhoto::class, 'ccr_item_id');
    }
}
