<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Society extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'minimum_contribution',
        'interest_rate',
        'penalty_type',
        'penalty_value',
        'contribution_due_day',
        'repayment_period_months',
        'bank_account_reference',
        'status',
    ];

    protected $casts = [
        'minimum_contribution' => 'decimal:2',
        'interest_rate'        => 'decimal:2',
        'penalty_value'        => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', 'active');
    }

    /** 🔁 NEW: Cycles */
    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }

    public function activeCycle(): HasOne
    {
        return $this->hasOne(Cycle::class)->where('status', 'active');
    }

    /** 🔗 Financials are now cycle-aware */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }



    /*
    |--------------------------------------------------------------------------
    | Computed Attributes (ACTIVE CYCLE ONLY)
    |--------------------------------------------------------------------------
    */

    public function getTotalContributionsAttribute(): float
    {
        return $this->activeCycle
            ? $this->activeCycle->transactions()
                ->where('type', 'contribution')
                ->sum('amount')
            : 0;
    }

    public function availableBalance(int $cycleId): float
{
    $totalIn = $this->transactions()
        ->where('cycle_id', $cycleId)
        ->whereIn('type', ['contribution', 'loan_repayment'])
        ->sum('amount');

    $totalOut = $this->transactions()
        ->where('cycle_id', $cycleId)
        ->where('type', 'loan_disbursement')
        ->sum('amount');

    return round($totalIn - $totalOut, 2);
}


    public function getTotalLoansIssuedAttribute(): float
    {
        return $this->activeCycle
            ? $this->activeCycle->transactions()
                ->where('type', 'loan_issued')
                ->sum('amount')
            : 0;
    }

    public function getTotalRepaymentsAttribute(): float
    {
        return $this->activeCycle
            ? $this->activeCycle->transactions()
                ->where('type', 'loan_repayment')
                ->sum('amount')
            : 0;
    }

    public function getTotalPenaltiesAttribute(): float
    {
        return $this->activeCycle
            ? $this->activeCycle->transactions()
                ->where('type', 'penalty')
                ->sum('amount')
            : 0;
    }

    public function getAvailableFundAttribute(): float
    {
        return
            $this->total_contributions
            - $this->total_loans_issued
            + $this->total_repayments
            + $this->total_penalties;
    }

    /*
    |--------------------------------------------------------------------------
    | Business Rules
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasMinimumMembers(): bool
    {
        return $this->activeMembers()->count() >= 3;
    }

    /** ✅ Year-end check now belongs to ACTIVE CYCLE */
    public function canProcessYearEnd(): bool
    {
        if (!$this->activeCycle) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->activeCycle->end_date)
            && $this->activeCycle->status === 'active';
    }
}
