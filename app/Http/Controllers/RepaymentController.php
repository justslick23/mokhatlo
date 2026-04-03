<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Models\Transaction;
use App\Models\Loan;
use App\Mail\TransactionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RepaymentController extends Controller
{
    /* ================= Helpers ================= */

    protected function currentCycleId(): int
    {
        $cycleId = session('current_cycle_id');
        abort_if(!$cycleId, 403, 'No active cycle');
        return $cycleId;
    }

    protected function societyMemberOnly(Society $society): void
    {
        $isMember = $society->members()
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->exists();

        abort_if(!$isMember, 403, 'You are not a member of this society.');
    }

    protected function financeOnly(Society $society)
    {
        return $this->financeAccess($society);
    }
    

    /* ================= Views ================= */

    public function index(Society $society)
    {
        $this->societyMemberOnly($society);
        $cycleId = $this->currentCycleId();

        $repayments = $society->transactions()
            ->where('type', 'loan_repayment')
            ->where('cycle_id', $cycleId)
            ->with(['member.user', 'loan'])
            ->latest()
            ->paginate(20);

        $totalRepayments = $society->transactions()
            ->where('type', 'loan_repayment')
            ->where('cycle_id', $cycleId)
            ->sum('amount');

        return view('repayments.index', compact(
            'society',
            'repayments',
            'totalRepayments'
        ));
    }

    public function create(Society $society)
    {
        $this->financeOnly($society);
        $cycleId = $this->currentCycleId();

        $loans = $society->loans()
            ->where('cycle_id', $cycleId)
            ->where('status', 'active')
            ->where('outstanding_balance', '>', 0)
            ->with('member.user')
            ->get();

        return view('repayments.create', compact('society', 'loans'));
    }


    /* ================= Actions ================= */

    public function store(Request $request, Society $society)
    {
        $this->financeOnly($society);
        $cycleId = $this->currentCycleId();
    
        $validated = $request->validate([
            'loan_id'          => 'required|exists:loans,id',
            'amount'           => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'notes'            => 'nullable|string',
        ]);
    
        $loan = Loan::where('id', $validated['loan_id'])
            ->where('society_id', $society->id)
            ->where('cycle_id', $cycleId)
            ->whereIn('status', ['active', 'overdue'])
            ->firstOrFail();
    
        abort_if(
            $validated['amount'] > $loan->outstanding_balance,
            422,
            'Repayment exceeds outstanding balance.'
        );
    
        // Calculate interest portion BEFORE recording repayment
        $interestOwed    = $loan->interest - $loan->interest_paid;
        $penaltyOwed     = $loan->penalty_amount;
        $remaining       = $validated['amount'];
    
        $penaltyPortion  = min($remaining, $penaltyOwed);
        $remaining      -= $penaltyPortion;
    
        $interestPortion = min($remaining, $interestOwed);
        $remaining      -= $interestPortion;
    
        $principalPortion = $remaining;
    
        // Record main repayment transaction
        $transaction = Transaction::create([
            'society_id'       => $society->id,
            'cycle_id'         => $cycleId,
            'member_id'        => $loan->member_id,
            'loan_id'          => $loan->id,
            'type'             => 'loan_repayment',
            'amount'           => $validated['amount'],
            'transaction_date' => $validated['transaction_date'],
            'notes'            => $validated['notes'],
            'recorded_by'      => auth()->id(),
        ]);
    
        // Record interest as separate transaction for reporting
        if ($interestPortion > 0) {
            Transaction::create([
                'society_id'       => $society->id,
                'cycle_id'         => $cycleId,
                'member_id'        => $loan->member_id,
                'loan_id'          => $loan->id,
                'type'             => 'loan_interest',
                'amount'           => $interestPortion,
                'transaction_date' => $validated['transaction_date'],
                'notes'            => "Interest portion of repayment on Loan #{$loan->id} (Principal: M{$principalPortion}, Interest: M{$interestPortion}, Penalty: M{$penaltyPortion})",
            ]);
        }
    
        // Update loan using model method
        $loan->recordRepayment((float) $validated['amount']);
    
        // Notify all members
        $transaction->load('loan');
        foreach ($society->members()->with('user')->get() as $m) {
            Mail::to($m->user->email)->send(new TransactionNotification($society, $transaction, $m));
        }
    
        return redirect()
            ->route('societies.repayments.index', $society)
            ->with('success', 'Repayment recorded successfully.');
    }

    public function destroy(Society $society, Transaction $transaction)
    {
        $this->treasurerOnly($society);
    
        abort_if(
            $transaction->type !== 'loan_repayment' ||
            $transaction->society_id !== $society->id,
            404
        );
    
        $loan = $transaction->loan;
    
        // Also delete the associated interest transaction if it exists
        Transaction::where('loan_id', $loan->id)
            ->where('type', 'loan_interest')
            ->where('transaction_date', $transaction->transaction_date)
            ->delete();
    
        $loan->amount_repaid       -= $transaction->amount;
        $loan->outstanding_balance += $transaction->amount;
        $loan->interest_paid        = max(0, $loan->interest_paid - /* recalculate if needed */ 0);
    
        if ($loan->outstanding_balance > 0 && $loan->status === 'repaid') {
            $loan->status = 'active';
        }
    
        $loan->save();
        $transaction->delete();
    
        return back()->with('success', 'Repayment deleted.');
    }
}