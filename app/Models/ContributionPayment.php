<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionPayment extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_id',
        'society_membership_id',
        'contribution_obligation_id',
        'amount',
        'paid_on',
        'payment_method',
        'payment_reference',
        'notes',
        'idempotency_key',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(
            ContributionObligation::class,
            'contribution_obligation_id'
        );
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(
            SocietyMembership::class,
            'society_membership_id'
        );
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }
}
