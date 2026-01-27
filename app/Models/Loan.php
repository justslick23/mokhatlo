<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'society_id',
        'member_id',
        'principal',
        'interest',
        'total_amount',
        'amount_repaid',
        'outstanding_balance',
        'penalty_amount',
        'issue_date',
        'due_date',
        'status',
        'purpose',
        'issued_by',
        'cycle_id',
    ];

    protected $casts = [
        'principal' => 'decimal:2',
        'interest' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_repaid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    // Relationships
    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }

    // Status Checks
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isRepaid(): bool
    {
        return $this->status === 'repaid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    public function isDue(): bool
    {
        return now()->greaterThan($this->due_date) && !$this->isRepaid();
    }

    public function cycle(): BelongsTo
{
    return $this->belongsTo(Cycle::class);
}


    public function isDueSoon(int $days = 3): bool
    {
        return now()->diffInDays($this->due_date, false) <= $days 
            && now()->lessThan($this->due_date)
            && !$this->isRepaid();
    }

    // Calculation Methods
    public function calculateInterest(): float
    {
        return $this->principal * ($this->society->interest_rate / 100);
    }

    public function applyPenalty(): void
    {
        if ($this->society->penalty_type === 'fixed') {
            $penalty = $this->society->penalty_value;
        } else {
            $penalty = $this->outstanding_balance * ($this->society->penalty_value / 100);
        }

        $this->penalty_amount += $penalty;
        $this->outstanding_balance += $penalty;
        $this->status = 'overdue';
        $this->save();
    }

    public function recordRepayment(float $amount): void
    {
        $this->amount_repaid += $amount;
        $this->outstanding_balance -= $amount;

        if ($this->outstanding_balance <= 0) {
            $this->outstanding_balance = 0;
            $this->status = 'repaid';
        }

        $this->save();
    }
}