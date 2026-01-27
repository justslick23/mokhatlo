<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Mail\TransactionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LoanController extends Controller
{
    protected function activeCycleId()
    {
        abort_if(!session('current_cycle_id'), 403, 'No active cycle');
        return session('current_cycle_id');
    }

    protected function authorizeOfficer(Society $society)
    {
        abort_unless(
            auth()->user()->isChairmanOf($society) ||
            auth()->user()->isTreasurerOf($society),
            403
        );
    }

    /*
    |--------------------------------------------------------------------------
    | List Loans
    |--------------------------------------------------------------------------
    */
    public function index(Society $society)
    {
        $this->authorizeOfficer($society);

        $loans = Loan::with('member.user')
            ->where('cycle_id', $this->activeCycleId())
            ->latest()
            ->get();

        return view('loans.index', compact('society', 'loans'));
    }

    /*
    |--------------------------------------------------------------------------
    | Create Loan Form
    |--------------------------------------------------------------------------
    */
    public function create(Society $society)
    {
        $this->authorizeOfficer($society);

        $members = $society->members()
            ->where('status', 'active')
            ->get();

        return view('loans.create', compact('society', 'members'));
    }

    /*
    |--------------------------------------------------------------------------
    | Store Loan
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, Society $society)
    {
        $this->authorizeOfficer($society);
    
        $cycleId = session('current_cycle_id');
        abort_if(!$cycleId, 403, 'No active cycle');
    
        $data = $request->validate([
            'member_id' => 'required|exists:members,id',
            'principal' => 'required|numeric|min:1',
            'due_date'  => 'required|date|after:today',
            'purpose'   => 'nullable|string|max:500',
        ]);
    
        $member = Member::findOrFail($data['member_id']);
        abort_if(!$member->canBorrow(), 403, 'Member cannot borrow');
    
        $available = $society->availableBalance($cycleId);
    
        abort_if(
            $data['principal'] > $available,
            422,
            "Loan amount exceeds available contributions (Available: {$available})"
        );
    
        $interest = round(
            $data['principal'] * ($society->interest_rate / 100),
            2
        );
    
        $total = $data['principal'] + $interest;
    
        DB::transaction(function () use (
            $society,
            $cycleId,
            $member,
            $data,
            $interest,
            $total
        ) {
    
            $loan = Loan::create([
                'society_id'          => $society->id,
                'cycle_id'            => $cycleId,
                'member_id'           => $member->id,
                'principal'           => $data['principal'],
                'interest'            => $interest,
                'total_amount'        => $total,
                'amount_repaid'       => 0,
                'outstanding_balance' => $total,
                'penalty_amount'      => 0,
                'issue_date'          => now(),
                'due_date'            => $data['due_date'],
                'status'              => 'active',
                'purpose'             => $data['purpose'],
                'issued_by'           => auth()->id(),
            ]);
    
            // 🔻 MONEY LEAVES THE POOL
            $transaction = Transaction::create([
                'society_id'       => $society->id,
                'cycle_id'         => $cycleId,
                'member_id'        => $member->id,
                'loan_id'          => $loan->id,
                'type'             => 'loan_disbursement',
                'amount'           => $data['principal'],
                'transaction_date' => now(),
                'notes'            => 'Loan issued',
                'recorded_by'      => auth()->id(),
            ]);

            // Load transaction with loan relationship and notify all members
            $transaction->load('loan');
            $members = $society->members()
                ->with('user')
                ->get();

            foreach ($members as $m) {
                Mail::to($m->user->email)->send(new TransactionNotification($society, $transaction, $m));
            }
        });
    
        return redirect()
            ->route('societies.loans.index', $society)
            ->with('success', 'Loan issued successfully.');
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | View Loan
    |--------------------------------------------------------------------------
    */
    public function show(Society $society, Loan $loan)
    {
        $this->authorizeOfficer($society);

        abort_unless(
            $loan->cycle_id == session('current_cycle_id'),
            403
        );

        return view('loans.show', compact('society', 'loan'));
    }

    /*
    |--------------------------------------------------------------------------
    | Write Off Loan
    |--------------------------------------------------------------------------
    */
    public function writeOff(Society $society, Loan $loan)
    {
        $this->authorizeOfficer($society);

        $loan->update([
            'status' => 'written_off',
            'outstanding_balance' => 0,
        ]);

        return back()->with('success', 'Loan written off.');
    }

    /*
    |--------------------------------------------------------------------------
    | Reactivate Loan
    |--------------------------------------------------------------------------
    */
    public function reactivate(Society $society, Loan $loan)
    {
        $this->authorizeOfficer($society);

        abort_if($loan->status !== 'written_off', 400);

        $loan->update([
            'status' => 'active',
        ]);

        return back()->with('success', 'Loan reactivated.');
    }
}