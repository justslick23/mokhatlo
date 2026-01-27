<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'society_id',
        'cycle_id',
        'member_id',
        'loan_id',
        'type',
        'amount',
        'transaction_date',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
    ];

    /* ================= Relationships ================= */

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /* ================= Scopes ================= */

    public function scopeForCycle($query, int $cycleId)
    {
        return $query->where('cycle_id', $cycleId);
    }
}
