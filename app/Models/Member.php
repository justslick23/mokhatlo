<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'society_id',
        'role',
        'status',
        'joined_date',
    ];

    protected $casts = [
        'joined_date' => 'date',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

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

    // Computed Properties
    public function getTotalContributionsAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'contribution')
            ->sum('amount');
    }

    public function getActiveLoansTotalAttribute(): float
    {
        return $this->loans()
            ->whereIn('status', ['active', 'overdue'])
            ->sum('outstanding_balance');
    }

    public function getBalanceAttribute(): float
    {
        return $this->total_contributions - $this->active_loans_total;
    }

    // Role Checks
    public function isChairman(): bool
    {
        return $this->role === 'chairman';
    }

    public function isTreasurer(): bool
    {
        return $this->role === 'treasurer';
    }

    public function isSecretary(): bool
    {
        return $this->role === 'secretary';
    }

    public function isOfficer(): bool
    {
        return in_array($this->role, ['chairman', 'treasurer', 'secretary']);
    }

    // Status Checks
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasOverdueLoans(): bool
    {
        return $this->loans()->where('status', 'overdue')->exists();
    }

    public function canBorrow(): bool
    {
        return $this->isActive() && !$this->hasOverdueLoans();
    }


}