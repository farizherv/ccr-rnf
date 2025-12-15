<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcrItem extends Model
{
    protected $fillable = [
        'ccr_report_id',
        'description',
    ];

    public function report()
    {
        return $this->belongsTo(CcrReport::class, 'ccr_report_id');
    }

    public function photos()
    {
        return $this->hasMany(CcrPhoto::class);
    }
}
