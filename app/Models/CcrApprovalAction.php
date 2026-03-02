<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CcrApprovalAction extends Model
{
    protected $table = 'ccr_approval_actions';

    protected $fillable = [
        'ccr_report_id',
        'actor_id',
        'action',
        'idempotency_key',
        'from_status',
        'to_status',
        'was_applied',
        'note_hash',
    ];

    protected $casts = [
        'ccr_report_id' => 'integer',
        'actor_id' => 'integer',
        'was_applied' => 'boolean',
    ];
}
