<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerLine extends Model
{
    protected $fillable = [
        'ledger_transaction_id',
        'ledger_account_id',
        'debit_amount',
        'credit_amount',
    ];

    protected function casts(): array
    {
        return [
            'debit_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            LedgerTransaction::class,
            'ledger_transaction_id'
        );
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            LedgerAccount::class,
            'ledger_account_id'
        );
    }
}
