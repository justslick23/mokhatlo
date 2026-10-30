<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInterestCredit extends Model
{
    protected $fillable = [
        'society_id',
        'loan_id',
        'loan_repayment_id',
        'beneficiary_membership_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(
            LoanRepayment::class,
            'loan_repayment_id'
        );
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(
            SocietyMembership::class,
            'beneficiary_membership_id'
        );
    }
}
