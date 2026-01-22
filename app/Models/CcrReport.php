<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CcrReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ccr_reports';

    protected $fillable = [
        'type',
        'template_key',
        'template_version',
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
        'docx_path',
        'docx_generated_at',
        'purge_at',
        'parts_payload',
        'detail_payload',
    ];

    protected $casts = [
        'inspection_date'   => 'datetime',
        'docx_generated_at' => 'datetime',
        'purge_at'          => 'datetime',
        'deleted_at'        => 'datetime',
        'template_version' => 'integer',
        'parts_payload' => 'array',
        'detail_payload' => 'array',
    ];


    public function items()
    {
        return $this->hasMany(CcrItem::class, 'ccr_report_id');
    }

    public function photos()
    {
        return $this->hasManyThrough(
            CcrPhoto::class,
            CcrItem::class,
            'ccr_report_id', // FK di CcrItem
            'ccr_item_id',   // FK di CcrPhoto
            'id',            // PK di CcrReport
            'id'             // PK di CcrItem
        );
    }
}
