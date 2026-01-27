<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cycle extends Model
{
    protected $fillable = [
        'society_id',
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function society()
    {
        return $this->belongsTo(Society::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get total penalties collected in this cycle
     */
    public function getTotalPenalties()
    {
        return $this->loans()->sum('penalty_amount');
    }

    /**
     * Distribute penalties among active members
     * Returns array of member_id => penalty_share
     */
    public function distributePenalties()
    {
        $totalPenalties = $this->getTotalPenalties();
        
        if ($totalPenalties <= 0) {
            return [];
        }

        $activeMembers = $this->society->members()
            ->where('status', 'active')
            ->count();

        if ($activeMembers <= 0) {
            return [];
        }

        $sharePerMember = $totalPenalties / $activeMembers;
        
        return $this->society->members()
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(fn($member) => [$member->id => $sharePerMember])
            ->toArray();
    }

    /**
     * Get cycle settlement summary
     */
    public function getSettlementSummary()
    {
        $totalContributions = $this->transactions()
            ->where('type', 'contribution')
            ->sum('amount');

        $totalLoansIssued = $this->loans()->sum('principal');
        
        $totalRepayments = $this->transactions()
            ->where('type', 'loan_repayment')
            ->sum('amount');

        $totalBorrowerInterest = $this->loans()->sum('interest');
        
        $totalPenalties = $this->getTotalPenalties();
        
        $activeMembers = $this->society->members()
            ->where('status', 'active')
            ->count();

        $penaltyPerMember = $activeMembers > 0 ? $totalPenalties / $activeMembers : 0;

        return [
            'total_contributions' => $totalContributions,
            'total_loans_issued' => $totalLoansIssued,
            'total_repayments' => $totalRepayments,
            'total_borrower_interest' => $totalBorrowerInterest,
            'total_penalties' => $totalPenalties,
            'active_members' => $activeMembers,
            'penalty_per_member' => $penaltyPerMember,
            'pool_balance' => $totalContributions + $totalRepayments - $totalLoansIssued,
        ];
    }
}