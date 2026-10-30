<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Loan extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_id',
        'society_membership_id',
        'lender_membership_id',
        'submitted_by',
        'loan_number',
        'principal_amount',
        'purpose',
        'term_months',
        'matures_on',
        'interest_rate_percent',
        'interest_method',
        'interest_basis',
        'policy_version',
        'loan_penalty_rate_percent',
        'quoted_interest_amount',
        'quoted_total_amount',
        'status',
        'request_key',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'disbursed_at',
        'settled_at',
        'legacy_source',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_rate_percent' => 'decimal:5',
            'loan_penalty_rate_percent' => 'decimal:5',
            'quoted_interest_amount' => 'decimal:2',
            'quoted_total_amount' => 'decimal:2',
            'term_months' => 'integer',
            'policy_version' => 'integer',
            'matures_on' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(
            SocietyMembership::class,
            'society_membership_id'
        );
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    // Legacy relationship retained for older records.
    public function lender(): BelongsTo
    {
        return $this->belongsTo(
            SocietyMembership::class,
            'lender_membership_id'
        );
    }

    public function events(): HasMany
    {
        return $this->hasMany(LoanEvent::class);
    }

    public function disbursement(): HasOne
    {
        return $this->hasOne(LoanDisbursement::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)
            ->orderBy('installment_number');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class)
            ->orderByDesc('paid_on')
            ->orderByDesc('id');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(LoanPenalty::class);
    }

    public function interestCredits(): HasMany
    {
        return $this->hasMany(LoanInterestCredit::class);
    }
}
