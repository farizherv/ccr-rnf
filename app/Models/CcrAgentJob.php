<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcrAgentJob extends Model
{
    protected $table = 'ccr_agent_jobs';

    protected $fillable = [
        'group','component','inspection_date','payload',
        'status','locked_at','locked_by','attempts','last_error','result'
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'inspection_date' => 'datetime',
        'locked_at' => 'datetime',
    ];
}
