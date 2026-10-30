<?php

namespace App\Http\Controllers;

use App\Models\LoanPenalty;
use App\Services\Loans\ManageLoanPenalty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanPenaltyController extends Controller
{
    private function visibleQuery(Request $request)
    {
        $society = $request->attributes->get('currentSociety');
        $membership = $request->attributes->get('currentMembership');

        $canViewAll = $request->user()->can('loans.penalties.view');

        abort_unless(
            $canViewAll
            || $request->user()->can('loans.penalties.view_own'),
            403
        );

        return LoanPenalty::query()
            ->where('society_id', $society->id)
            ->when(
                ! $canViewAll,
                fn ($query) => $query->whereHas(
                    'loan',
                    fn ($q) => $q->where(
                        'society_membership_id',
                        $membership->id
                    )
                )
            );
    }

    public function index(Request $request): View
    {
        $penalties = $this->visibleQuery($request)
            ->with([
                'loan.membership.user',
                'installment',
                'payments',
                'waivers',
            ])
            ->orderByDesc('assessed_on')
            ->orderByDesc('id')
            ->paginate(20);

        return view('loans.penalties.index', [
            'penalties' => $penalties,
            'canManage' => $request->user()
                ->can('loans.penalties.view'),
        ]);
    }

    public function show(
        Request $request,
        int $penalty
    ): View {
        $record = $this->visibleQuery($request)
            ->with([
                'loan.membership.user',
                'installment',
                'payments.recorder',
                'waivers.approver',
            ])
            ->findOrFail($penalty);

        return view('loans.penalties.show', [
            'penalty' => $record,
            'paymentKey' => (string) Str::uuid(),
            'waiverKey' => (string) Str::uuid(),
        ]);
    }

    public function pay(
        Request $request,
        int $penalty,
        ManageLoanPenalty $manager
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('loans.penalties.collect'),
            403
        );

        $data = $request->validate([
            'amount' => [
                'required',
                'regex:/^\d{1,16}(\.\d{1,2})?$/',
            ],
            'paid_on' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' .
                    now('Africa/Maseru')->toDateString(),
            ],
            'payment_method' => [
                'required',
                Rule::in(['cash', 'bank_transfer', 'mobile_money']),
            ],
            'payment_reference' => [
                'nullable', 'string', 'max:120',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $society = $request->attributes->get('currentSociety');

        $manager->pay(
            $society,
            $request->user(),
            $penalty,
            $data
        );

        return redirect()
            ->route('loans.penalties.show', $penalty)
            ->with('status', 'Loan penalty payment recorded.');
    }

    public function waive(
        Request $request,
        int $penalty,
        ManageLoanPenalty $manager
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('loans.penalties.waive'),
            403
        );

        $data = $request->validate([
            'amount' => [
                'required',
                'regex:/^\d{1,16}(\.\d{1,2})?$/',
            ],
            'reason' => [
                'required', 'string', 'min:10', 'max:2000',
            ],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $society = $request->attributes->get('currentSociety');

        $manager->waive(
            $society,
            $request->user(),
            $penalty,
            $data
        );

        return redirect()
            ->route('loans.penalties.show', $penalty)
            ->with('status', 'Loan penalty waiver recorded.');
    }
}
