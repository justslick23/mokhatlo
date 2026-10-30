<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CyclePayoutPayment extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_payout_batch_id',
        'cycle_payout_item_id',
        'amount_cents',
        'savings_cents',
        'interest_cents',
        'penalty_cents',
        'paid_on',
        'payment_method',
        'payment_reference',
        'notes',
        'idempotency_key',
        'ledger_transaction_id',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'savings_cents' => 'integer',
            'interest_cents' => 'integer',
            'penalty_cents' => 'integer',
            'paid_on' => 'date',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            CyclePayoutBatch::class,
            'cycle_payout_batch_id'
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(
            CyclePayoutItem::class,
            'cycle_payout_item_id'
        );
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(
            LedgerTransaction::class,
            'ledger_transaction_id'
        );
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
