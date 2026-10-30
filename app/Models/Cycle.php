<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cycle extends Model
{
    protected $fillable = [
        'society_id',
        'name',
        'code',
        'start_date',
        'end_date',
        'status',
        'active_slot',
        'activated_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function policy(): HasOne
    {
        return $this->hasOne(CyclePolicy::class);
    }

    
public function contributionObligations(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(
        \App\Models\ContributionObligation::class
    );
}


public function loans(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(
        \App\Models\Loan::class
    );
}

}