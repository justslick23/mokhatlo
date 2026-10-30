<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContributionObligation extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_id',
        'society_membership_id',
        'period_start',
        'due_date',
        'required_amount',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'due_date' => 'date',
            'required_amount' => 'decimal:2',
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

    public function payments(): HasMany
    {
        return $this->hasMany(
            ContributionPayment::class,
            'contribution_obligation_id'
        );
    }

    
public function penalty(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(
        \App\Models\ContributionPenalty::class,
        'contribution_obligation_id'
    );
}

}
