<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CcrReport extends Model
{
    use HasFactory;

    protected $table = 'ccr_reports';

    protected $fillable = [
        'type',
        'group_folder',
        'component',
        'make',
        'model',
        'sn',
        'smu',
        'unit',
        'wo_pr',
        'customer',
        'inspection_date',
    ];

    protected $casts = [
        'inspection_date' => 'date:Y-m-d',
    ];

    public function items()
    {
        return $this->hasMany(CcrItem::class, 'ccr_report_id');
    }

    /**
     * ALL PHOTOS via ITEM (CCR Report → Item → Photo)
     */
    public function photos()
    {
        return $this->hasManyThrough(
            CcrPhoto::class,
            CcrItem::class,
            'ccr_report_id', // foreign key di CcrItem menuju report
            'ccr_item_id',   // foreign key di CcrPhoto menuju item
            'id',            // kunci utama CcrReport
            'id'             // kunci utama CcrItem
        );
    }
}
