<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CyclePayoutBatch extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_id',
        'cycle_payout_draft_id',
        'cycle_payout_approval_id',
        'status',
        'approved_source_hash',
        'gross_total_cents',
        'penalty_distributed_cents',
        'penalty_journal_id',
        'request_key',
        'started_by',
        'started_at',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_total_cents' => 'integer',
            'penalty_distributed_cents' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function approval(): BelongsTo
    {
        return $this->belongsTo(
            CyclePayoutApproval::class,
            'cycle_payout_approval_id'
        );
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            CyclePayoutPayment::class,
            'cycle_payout_batch_id'
        );
    }

    public function penaltyJournal(): BelongsTo
    {
        return $this->belongsTo(
            LedgerTransaction::class,
            'penalty_journal_id'
        );
    }
}
