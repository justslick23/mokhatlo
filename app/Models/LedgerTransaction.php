<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerTransaction extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_id',
        'source_type',
        'source_id',
        'posting_date',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(
            LedgerLine::class,
            'ledger_transaction_id'
        );
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    
public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(
        \App\Models\User::class,
        'created_by'
    );
}

}
