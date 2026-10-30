<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanInstallment extends Model
{
    protected $fillable = [
        'society_id', 'loan_id', 'installment_number',
        'due_date', 'principal_due', 'interest_due', 'total_due',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_due' => 'decimal:2',
            'interest_due' => 'decimal:2',
            'total_due' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(
            LoanRepaymentAllocation::class,
            'loan_installment_id'
        );
    }

    
public function penalty(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(
        \App\Models\LoanPenalty::class,
        'loan_installment_id'
    );
}

}
