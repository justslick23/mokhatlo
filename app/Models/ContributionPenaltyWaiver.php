<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionPenaltyWaiver extends Model
{
    protected $fillable = [
        'society_id',
        'contribution_penalty_id',
        'amount',
        'reason',
        'idempotency_key',
        'waived_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The contribution penalty being waived.
     */
    public function penalty(): BelongsTo
    {
        return $this->belongsTo(
            ContributionPenalty::class,
            'contribution_penalty_id'
        );
    }

    /**
     * The society that owns this waiver.
     */
    public function society(): BelongsTo
    {
        return $this->belongsTo(
            Society::class,
            'society_id'
        );
    }

    /**
     * The user who authorised the waiver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'waived_by'
        );
    }
}
