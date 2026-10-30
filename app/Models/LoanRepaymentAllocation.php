<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepaymentAllocation extends Model
{
    protected $fillable = [
        'loan_id', 'loan_repayment_id',
        'loan_installment_id', 'principal_amount',
        'interest_amount',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
        ];
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(
            LoanRepayment::class,
            'loan_repayment_id'
        );
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(
            LoanInstallment::class,
            'loan_installment_id'
        );
    }
}
