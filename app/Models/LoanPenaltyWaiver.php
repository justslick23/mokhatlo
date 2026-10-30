<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPenaltyWaiver extends Model
{
    protected $fillable = [
        'society_id', 'loan_penalty_id',
        'amount', 'reason', 'idempotency_key', 'waived_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function loanPenalty(): BelongsTo
    {
        return $this->belongsTo(
            LoanPenalty::class,
            'loan_penalty_id'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }
}
