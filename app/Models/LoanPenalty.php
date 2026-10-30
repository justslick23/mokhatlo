<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanPenalty extends Model
{
    protected $fillable = [
        'society_id', 'loan_id', 'loan_installment_id',
        'base_amount', 'rate_percent', 'assessed_amount',
        'cutoff_date', 'assessed_on', 'calculation_method',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'rate_percent' => 'decimal:5',
            'assessed_amount' => 'decimal:2',
            'cutoff_date' => 'date',
            'assessed_on' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(
            LoanInstallment::class,
            'loan_installment_id'
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            LoanPenaltyPayment::class
        );
    }

    public function waivers(): HasMany
    {
        return $this->hasMany(
            LoanPenaltyWaiver::class
        );
    }

    public function assessedCents(): int
    {
        return Money::toCents($this->assessed_amount);
    }

    public function paidCents(): int
    {
        $this->loadMissing('payments');

        return $this->payments->sum(
            fn ($row) => Money::toCents($row->amount)
        );
    }

    public function waivedCents(): int
    {
        $this->loadMissing('waivers');

        return $this->waivers->sum(
            fn ($row) => Money::toCents($row->amount)
        );
    }

    public function outstandingCents(): int
    {
        return max(
            0,
            $this->assessedCents()
                - $this->paidCents()
                - $this->waivedCents()
        );
    }
}
