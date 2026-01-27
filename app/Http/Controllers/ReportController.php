<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Models\Member;

class ReportController extends Controller
{
    protected function canView(Society $society)
    {
        abort_unless(
            auth()->user()->canManageSociety($society),
            403
        );
    }

    protected function cycle(Society $society)
    {
        return $society->activeCycle ?? abort(403, 'No active cycle');
    }

    /* ================= Summary ================= */

    public function summary(Society $society)
    {
        $this->canView($society);
        $cycle = $this->cycle($society);

        $totalContributions = $cycle->transactions()->where('type', 'contribution')->sum('amount');
        $totalLoansIssued = $cycle->loans()->sum('principal');
        $totalRepayments = $cycle->transactions()->where('type', 'loan_repayment')->sum('amount');
        $outstandingBalance = $cycle->loans()->where('status', '!=', 'repaid')->sum('outstanding_balance');
        $totalInterest = $cycle->loans()->sum('interest');
        $availableFund = $totalContributions + $totalRepayments - $totalLoansIssued;

        $stats = [
            'total_contributions' => $totalContributions,
            'total_loans_issued' => $totalLoansIssued,
            'total_repayments' => $totalRepayments,
            'outstanding_balance' => $outstandingBalance,
            'total_interest' => $totalInterest,
            'available_fund' => $availableFund,
        ];

        return view('societies.reports.summary', compact('society', 'cycle', 'stats'));
    }

    /* ================= Members ================= */

    public function members(Society $society)
    {
        $this->canView($society);
        $cycle = $this->cycle($society);

        $members = $society->members()->with('user')->get();

        return view('societies.reports.members', compact('society', 'cycle', 'members'));
    }

    /* ================= Transactions ================= */

    public function transactions(Society $society)
    {
        $this->canView($society);
        $cycle = $this->cycle($society);

        $transactions = $cycle->transactions()
            ->with('member.user')
            ->latest()
            ->paginate(50);

        return view('societies.reports.transactions', compact('society', 'cycle', 'transactions'));
    }

    /* ================= Loans ================= */

    public function loans(Society $society)
    {
        $this->canView($society);
        $cycle = $this->cycle($society);

        $loans = $cycle->loans()->with('member.user')->paginate(50);

        return view('societies.reports.loans', compact('society', 'cycle', 'loans'));
    }

    /* ================= Member Statement ================= */

    public function memberStatement(Society $society, Member $member)
    {
        $cycle = $this->cycle($society);

        abort_unless(
            auth()->user()->isChairmanOf($society) ||
            auth()->user()->id === $member->user_id,
            403
        );

        $transactions = $cycle->transactions()
            ->where('member_id', $member->id)
            ->latest()
            ->get();

        $loans = $cycle->loans()
            ->where('member_id', $member->id)
            ->get();

        return view('societies.reports.member-statement', compact(
            'society', 'cycle', 'member', 'transactions', 'loans'
        ));
    }
}