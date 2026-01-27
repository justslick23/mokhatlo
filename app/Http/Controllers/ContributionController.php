<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Models\Member;
use App\Models\Transaction;
use App\Mail\TransactionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContributionController extends Controller
{
    /**
     * List contributions (current cycle).
     */
    public function index(Society $society)
    {
        $this->authorizeFinance($society);

        $cycleId = session('current_cycle_id');

        $contributions = Transaction::with(['member.user'])
            ->where('society_id', $society->id)
            ->where('type', 'contribution')
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->latest()
            ->get();

        return view('contributions.index', compact('society', 'contributions'));
    }

    /**
     * Show form to record contribution.
     */
    public function create(Society $society)
    {
        $this->authorizeFinance($society);

        abort_if(!session('current_cycle_id'), 403, 'No active cycle');

        $members = $society->members()
            ->where('status', 'active')
            ->with('user')
            ->get();

        return view('contributions.create', compact('society', 'members'));
    }

    /**
     * Store contribution.
     */
    public function store(Request $request, Society $society)
    {
        $this->authorizeFinance($society);

        $cycleId = session('current_cycle_id');
        abort_if(!$cycleId, 403, 'No active cycle');

        $data = $request->validate([
            'member_id' => 'required|exists:members,id',
            'amount'    => 'required|numeric|min:1',
            'date'      => 'required|date',
            'notes'     => 'nullable|string|max:500',
        ]);

        $member = Member::where('society_id', $society->id)
            ->findOrFail($data['member_id']);

        $transaction = Transaction::create([
            'society_id' => $society->id,
            'member_id'  => $member->id,
            'cycle_id'   => $cycleId,
            'type'       => 'contribution',
            'amount'     => $data['amount'],
            'transaction_date'       => $data['date'],
            'notes'      => $data['notes'] ?? null,
        ]);

        // Notify all members of the transaction
        $members = $society->members()
            ->with('user')
            ->get();

        foreach ($members as $m) {
            Mail::to($m->user->email)->send(new TransactionNotification($society, $transaction, $m));
        }

        return redirect()
            ->route('societies.contributions.index', $society)
            ->with('success', 'Contribution recorded successfully.');
    }

    /**
     * View single contribution.
     */
    public function show(Society $society, Transaction $transaction)
    {
        $this->authorizeFinance($society);

        abort_if(
            $transaction->society_id !== $society->id ||
            $transaction->type !== 'contribution',
            404
        );

        $transaction->load('member.user');

        return view('contributions.show', compact('society', 'transaction'));
    }

    /* ===================== HELPERS ===================== */

    protected function authorizeFinance(Society $society)
    {
        abort_unless(
            auth()->user()->isChairmanOf($society)
            || auth()->user()->isTreasurerOf($society),
            403
        );
    }
}