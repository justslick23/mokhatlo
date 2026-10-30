<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPenaltyPayment extends Model
{
    protected $fillable = [
        'society_id', 'loan_penalty_id', 'amount',
        'paid_on', 'payment_method', 'payment_reference',
        'notes', 'idempotency_key', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function loanPenalty(): BelongsTo
    {
        return $this->belongsTo(
            LoanPenalty::class,
            'loan_penalty_id'
        );
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
