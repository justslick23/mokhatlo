<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CyclePayoutApproval extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_id',
        'cycle_payout_draft_id',
        'cycle_payout_reconciliation_id',
        'approved_by',
        'approved_at',
        'approval_notes',
        'approved_source_hash',
        'request_key',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(
            CyclePayoutDraft::class,
            'cycle_payout_draft_id'
        );
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(
            CyclePayoutReconciliation::class,
            'cycle_payout_reconciliation_id'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }
}
