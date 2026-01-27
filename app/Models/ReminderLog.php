<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'society_id',
        'member_id',
        'loan_id',
        'type',
        'reminder_date',
        'sent',
        'sent_at',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'sent' => 'boolean',
        'sent_at' => 'datetime',
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

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    // Helper Methods
    public function markAsSent(): void
    {
        $this->sent = true;
        $this->sent_at = now();
        $this->save();
    }

    public static function hasBeenSent(int $memberId, string $type, ?int $loanId = null): bool
    {
        $query = static::where('member_id', $memberId)
            ->where('type', $type)
            ->where('reminder_date', now()->toDateString())
            ->where('sent', true);

        if ($loanId) {
            $query->where('loan_id', $loanId);
        }

        return $query->exists();
    }
}