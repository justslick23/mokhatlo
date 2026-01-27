<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class YearEndController extends Controller
{
    protected function chairmanOnly(Society $society)
    {
        abort_unless(
            auth()->user()->isChairmanOf($society),
            403,
            'Only chairman allowed.'
        );
    }

    protected function activeCycle(Society $society)
    {
        $cycle = $society->activeCycle;
        abort_if(!$cycle, 403, 'No active cycle.');
        return $cycle;
    }

    /* ================= Projection (Live During Cycle) ================= */

    public function projection(Society $society)
    {
        abort_unless(
            auth()->user()->canManageSociety($society),
            403
        );

        $cycle = $this->activeCycle($society);
        $data = $this->calculate($society, $cycle);
        
        // Calculate cycle progress
        $totalDays = $cycle->start_date->diffInDays($cycle->end_date);
        $elapsedDays = $cycle->start_date->diffInDays(now());
        $daysRemaining = max(0, $cycle->end_date->diffInDays(now(), false));
        $cycleProgress = $totalDays > 0 ? min(100, (($elapsedDays / $totalDays) * 100)) : 0;

        return view('societies.year-end.projection', compact('society', 'cycle', 'data', 'daysRemaining', 'cycleProgress'));
    }

    /* ================= Preview ================= */

    public function preview(Society $society)
    {
        $this->chairmanOnly($society);
        $cycle = $this->activeCycle($society);

        abort_if(
            now()->lt($cycle->end_date),
            403,
            'Cycle not ended yet.'
        );

        abort_if($cycle->status === 'closed', 403, 'Already settled.');

        $data = $this->calculate($society, $cycle);

        return view('societies.year-end.preview', compact('society', 'cycle', 'data'));
    }

    /* ================= Process ================= */

    public function process(Society $society)
    {
        $this->chairmanOnly($society);
        $cycle = $this->activeCycle($society);

        DB::transaction(function () use ($society, $cycle) {

            $data = $this->calculate($society, $cycle);

            foreach ($data['rows'] as $row) {
                Transaction::create([
                    'society_id' => $society->id,
                    'cycle_id'   => $cycle->id,
                    'member_id'  => $row['member']->id,
                    'type'       => 'year_end_payout',
                    'amount'     => $row['payout'],
                    'transaction_date' => now(),
                    'notes' => 'Year-end settlement',
                ]);
            }

            $cycle->update(['status' => 'closed']);
        });

        return redirect()
            ->route('societies.year-end.history', $society)
            ->with('success', 'Year-end settled successfully.');
    }

    /* ================= History ================= */

    public function history(Society $society)
    {
        abort_unless(
            auth()->user()->canManageSociety($society),
            403
        );

        $payouts = $society->transactions()
            ->where('type', 'year_end_payout')
            ->with('member.user')
            ->latest()
            ->get();

        return view('societies.year-end.history', compact('society', 'payouts'));
    }

    /* ================= Core Calculation (OPTIMIZED) ================= */

    private function calculate(Society $society, $cycle)
    {
        // ✅ Get all active members upfront
        $members = $society->members()
            ->where('status', 'active')
            ->with('user')
            ->get();

        // ✅ SINGLE query: Get all contributions at once (grouped by member)
        $contributionsByMember = DB::table('transactions')
            ->where('cycle_id', $cycle->id)
            ->where('type', 'contribution')
            ->groupBy('member_id')
            ->select('member_id', DB::raw('SUM(amount) as total'))
            ->pluck('total', 'member_id');

        // ✅ SINGLE query: Get total contributions
        $totalContributions = $cycle->transactions()
            ->where('type', 'contribution')
            ->sum('amount');

        // ✅ SINGLE query: Interest belongs to borrowers
        $totalInterest = $cycle->loans()->sum('interest');
        
        // ✅ SINGLE query: Penalties go to the shared pool
        $totalPenalties = $cycle->loans()->sum('penalty_amount');

        // ✅ SINGLE query: Get all outstanding loans (grouped by member)
        $outstandingByMember = DB::table('loans')
            ->where('cycle_id', $cycle->id)
            ->where('status', '!=', 'paid')
            ->groupBy('member_id')
            ->select('member_id', DB::raw('SUM(outstanding_balance) as total'))
            ->pluck('total', 'member_id');

        $rows = [];
        $memberCount = $members->count();

        // ✅ Loop only processes data (no additional queries)
        foreach ($members as $member) {
            
            // Get from previously loaded data (no query)
            $contrib = $contributionsByMember->get($member->id, 0);
            $outstanding = $outstandingByMember->get($member->id, 0);

            // No interest share (goes to borrower)
            $interestShare = 0;
            
            // Penalty share: divided equally among all active members
            $penaltyShare = $memberCount > 0 ? ($totalPenalties / $memberCount) : 0;

            // Final payout = contributions + penalty share - outstanding loans
            $payout = $contrib + $penaltyShare - $outstanding;

            $rows[] = [
                'member' => $member,
                'contributions' => $contrib,
                'interestShare' => $interestShare,
                'penaltyShare' => $penaltyShare,
                'outstanding' => $outstanding,
                'payout' => max($payout, 0),
            ];
        }

        return compact(
            'totalContributions',
            'totalInterest',
            'totalPenalties',
            'rows'
        );
    }
}