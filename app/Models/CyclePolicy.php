<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CyclePolicy extends Model
{
    protected $fillable = [
        'society_id',
        'cycle_id',
        'version',
        'minimum_monthly_contribution',
        'contribution_due_day',
        'contribution_grace_days',
        'contribution_penalty_percent',
        'loan_interest_percent',
        'loan_interest_method',
        'loan_interest_basis',
        'loan_max_term_months',
        'loan_penalty_percent',
    ];

    protected function casts(): array
    {
        return [
            'minimum_monthly_contribution' => 'decimal:2',
            'contribution_penalty_percent' => 'decimal:5',
            'loan_interest_percent' => 'decimal:5',
            'loan_penalty_percent' => 'decimal:5',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }
}