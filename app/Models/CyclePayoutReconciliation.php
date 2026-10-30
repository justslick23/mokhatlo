<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CyclePayoutReconciliation extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_payout_draft_id',
        'reconciled_on',
        'ledger_cash_cents',
        'ledger_bank_cents',
        'ledger_mobile_money_cents',
        'confirmed_cash_cents',
        'confirmed_bank_cents',
        'confirmed_mobile_money_cents',
        'cash_count_notes',
        'bank_statement_reference',
        'mobile_money_reference',
        'notes',
        'source_hash',
        'request_key',
        'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'reconciled_on' => 'date',
            'ledger_cash_cents' => 'integer',
            'ledger_bank_cents' => 'integer',
            'ledger_mobile_money_cents' => 'integer',
            'confirmed_cash_cents' => 'integer',
            'confirmed_bank_cents' => 'integer',
            'confirmed_mobile_money_cents' => 'integer',
        ];
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(
            CyclePayoutDraft::class,
            'cycle_payout_draft_id'
        );
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'confirmed_by'
        );
    }
}
