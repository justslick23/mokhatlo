<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CyclePayoutDraft extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_id',
        'revision',
        'as_of_date',
        'status',
        'distribution_method',
        'savings_total_cents',
        'interest_total_cents',
        'penalty_pool_cents',
        'penalty_distributed_cents',
        'penalty_reserved_cents',
        'gross_total_cents',
        'liquid_assets_cents',
        'blockers',
        'source_hash',
        'request_key',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'as_of_date' => 'date',
            'savings_total_cents' => 'integer',
            'interest_total_cents' => 'integer',
            'penalty_pool_cents' => 'integer',
            'penalty_distributed_cents' => 'integer',
            'penalty_reserved_cents' => 'integer',
            'gross_total_cents' => 'integer',
            'liquid_assets_cents' => 'integer',
            'blockers' => 'array',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            CyclePayoutItem::class,
            'cycle_payout_draft_id'
        )->orderBy('society_membership_id');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(
            CyclePayoutReconciliation::class,
            'cycle_payout_draft_id'
        );
    }

    public function approval(): HasOne
    {
        return $this->hasOne(
            CyclePayoutApproval::class,
            'cycle_payout_draft_id'
        );
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
    public function batch(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            \App\Models\CyclePayoutBatch::class,
            'cycle_payout_draft_id'
        );
    }

}
