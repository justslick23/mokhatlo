<?php

namespace App\Http\Controllers;

use App\Models\ContributionPenalty;
use App\Services\Penalties\ManageContributionPenalty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContributionPenaltyController extends Controller
{
    public function index(Request $request): View
    {
        $society = $request->attributes->get('currentSociety');
        $membership = $request->attributes->get(
            'currentMembership'
        );

        $canManage = $request->user()->can('penalties.view');

        abort_unless(
            $canManage
            || $request->user()->can('penalties.view_own'),
            403
        );

        $penalties = ContributionPenalty::query()
            ->where('society_id', $society->id)
            ->when(
                ! $canManage,
                fn ($query) => $query->whereHas(
                    'obligation',
                    fn ($q) => $q->where(
                        'society_membership_id',
                        $membership->id
                    )
                )
            )
            ->with([
                'obligation.membership.user',
                'cycle',
                'payments',
                'waivers',
            ])
            ->orderByDesc('assessed_on')
            ->orderByDesc('id')
            ->paginate(20);

        return view('penalties.index', compact(
            'society',
            'penalties',
            'canManage'
        ));
    }

    public function show(
        Request $request,
        int $penalty
    ): View {
        $society = $request->attributes->get('currentSociety');
        $membership = $request->attributes->get(
            'currentMembership'
        );

        $canManage = $request->user()->can('penalties.view');

        abort_unless(
            $canManage
            || $request->user()->can('penalties.view_own'),
            403
        );

        $record = ContributionPenalty::query()
            ->where('society_id', $society->id)
            ->when(
                ! $canManage,
                fn ($query) => $query->whereHas(
                    'obligation',
                    fn ($q) => $q->where(
                        'society_membership_id',
                        $membership->id
                    )
                )
            )
            ->with([
                'cycle',
                'obligation.membership.user',
                'payments.recorder',
                'waivers.approver',
            ])
            ->findOrFail($penalty);

        return view('penalties.show', [
            'society' => $society,
            'penalty' => $record,
            'paymentKey' => (string) Str::uuid(),
            'waiverKey' => (string) Str::uuid(),
        ]);
    }

    public function recordPayment(
        Request $request,
        int $penalty,
        ManageContributionPenalty $manager
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('penalties.record_payment'),
            403
        );

        $data = $request->validate([
            'amount' => [
                'required',
                'string',
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
                Rule::in([
                    'cash',
                    'bank_transfer',
                    'mobile_money',
                ]),
            ],
            'payment_reference' => [
                'nullable',
                'string',
                'max:120',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'idempotency_key' => [
                'required',
                'uuid',
            ],
        ]);

        $society = $request->attributes->get('currentSociety');

        $manager->recordPayment(
            $society,
            $request->user(),
            $penalty,
            $data
        );

        return redirect()
            ->route('penalties.show', $penalty)
            ->with('status', 'Penalty payment recorded.');
    }

    public function waive(
        Request $request,
        int $penalty,
        ManageContributionPenalty $manager
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('penalties.waive'),
            403
        );

        $data = $request->validate([
            'amount' => [
                'required',
                'string',
                'regex:/^\d{1,16}(\.\d{1,2})?$/',
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'idempotency_key' => [
                'required',
                'uuid',
            ],
        ]);

        $society = $request->attributes->get('currentSociety');

        $manager->waive(
            $society,
            $request->user(),
            $penalty,
            $data
        );

        return redirect()
            ->route('penalties.show', $penalty)
            ->with('status', 'Penalty waiver recorded.');
    }
}
