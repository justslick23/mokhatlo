<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CyclePayoutItem extends Model
{
    protected $fillable = [
        'cycle_payout_draft_id',
        'society_id',
        'society_membership_id',
        'member_name',
        'member_number',
        'membership_status',
        'savings_cents',
        'interest_refund_cents',
        'penalty_share_cents',
        'gross_payout_cents',
    ];

    protected function casts(): array
    {
        return [
            'savings_cents' => 'integer',
            'interest_refund_cents' => 'integer',
            'penalty_share_cents' => 'integer',
            'gross_payout_cents' => 'integer',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(
            CyclePayoutDraft::class,
            'cycle_payout_draft_id'
        );
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(
            SocietyMembership::class,
            'society_membership_id'
        );
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(
        \App\Models\CyclePayoutPayment::class,
        'cycle_payout_item_id'
    );
}
}
