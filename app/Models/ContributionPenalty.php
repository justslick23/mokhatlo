<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContributionPenalty extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_id',
        'contribution_obligation_id',
        'base_amount',
        'rate_percent',
        'assessed_amount',
        'cutoff_date',
        'assessed_on',
        'calculation_method',
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

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(
            ContributionObligation::class,
            'contribution_obligation_id'
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            ContributionPenaltyPayment::class,
            'contribution_penalty_id'
        );
    }

    public function waivers(): HasMany
    {
        return $this->hasMany(
            ContributionPenaltyWaiver::class,
            'contribution_penalty_id'
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
            fn ($payment) => Money::toCents($payment->amount)
        );
    }

    public function waivedCents(): int
    {
        $this->loadMissing('waivers');

        return $this->waivers->sum(
            fn ($waiver) => Money::toCents($waiver->amount)
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
