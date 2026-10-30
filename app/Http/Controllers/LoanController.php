<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\Loans\DisburseLoan;
use App\Services\Loans\LoanBalances;
use App\Services\Loans\SubmitLoanApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(Request $request): View
    {
        $canManage = $request->user()->can('loans.view');

        abort_unless(
            $canManage
                || $request->user()->can('loans.view_own'),
            403
        );

        $society = $request->attributes->get('currentSociety');
        $membership = $request->attributes->get('currentMembership');

        $filters = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'approved',
                    'rejected',
                    'disbursed',
                    'settled',
                ]),
            ],
        ]);

        $loans = Loan::query()
            ->where('society_id', $society->id)
            ->when(
                ! $canManage,
                fn ($query) => $query->where(
                    'society_membership_id',
                    $membership->id
                )
            )
            ->when(
                ! empty($filters['status']),
                fn ($query) => $query->where(
                    'status',
                    $filters['status']
                )
            )
            ->with([
                'membership.user',
                'cycle',
                'submittedBy',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('loans.index', compact(
            'society',
            'loans',
            'canManage'
        ));
    }

    public function create(Request $request): View
    {
        abort_unless(
            $request->user()->can('loans.apply'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        $cycle = $society->cycles()
            ->where('status', 'active')
            ->with('policy')
            ->first();

        abort_unless(
            $cycle && $cycle->policy,
            404,
            'An active savings cycle is required.'
        );

        $canCaptureForMembers = $request->user()
            ->can('loans.create_for_members');

        $canConfirmDisbursement = $request->user()
            ->can('loans.disburse');

        $members = $canCaptureForMembers
            ? $society->memberships()
                ->with('user')
                ->where('status', 'active')
                ->orderBy('member_number')
                ->get()
            : collect();

        $applicationKey = (string) Str::uuid();
        $disbursementKey = (string) Str::uuid();

        return view('loans.create', compact(
            'society',
            'cycle',
            'members',
            'canCaptureForMembers',
            'canConfirmDisbursement',
            'applicationKey',
            'disbursementKey'
        ));
    }

    public function store(
        Request $request,
        SubmitLoanApplication $service,
        DisburseLoan $disburser
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('loans.apply'),
            403
        );

        $data = $request->validate([
            'principal_amount' => [
                'required',
                'regex:/^\d{1,10}(\.\d{1,2})?$/',
            ],

            'purpose' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],

            'society_membership_id' => [
                'nullable',
                'integer',
            ],

            'request_key' => [
                'required',
                'uuid',
            ],

            'already_disbursed' => [
                'sometimes',
                'boolean',
            ],

            'disbursed_on' => [
                'required_if:already_disbursed,1',
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:' .
                    now('Africa/Maseru')->toDateString(),
            ],

            'payment_method' => [
                'required_if:already_disbursed,1',
                'nullable',
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

            'disbursement_key' => [
                'required_if:already_disbursed,1',
                'nullable',
                'uuid',
            ],
        ]);

        $society = $request->attributes->get('currentSociety');

        $recordDisbursement = (bool) (
            $data['already_disbursed'] ?? false
        );

        if ($recordDisbursement) {
            abort_unless(
                $request->user()->can('loans.disburse'),
                403
            );
        }

        $loan = DB::transaction(function () use (
            $society,
            $request,
            $service,
            $disburser,
            $data,
            $recordDisbursement
        ) {
            $loan = $service->execute(
                $society,
                $request->user(),
                $data
            );

            if ($recordDisbursement) {
                $disburser->execute(
                    $society,
                    $request->user(),
                    $loan->id,
                    [
                        'disbursed_on' => $data['disbursed_on'],
                        'payment_method' => $data['payment_method'],
                        'payment_reference' =>
                            $data['payment_reference'] ?? null,
                        'notes' => null,
                        'idempotency_key' =>
                            $data['disbursement_key'],
                    ]
                );
            }

            return $loan;
        }, 3);

        return redirect()
            ->route('loans.show', $loan->id)
            ->with(
                'status',
                $recordDisbursement
                    ? 'Loan captured and disbursement recorded.'
                    : 'Loan captured successfully.'
            );
    }

    public function show(
        Request $request,
        int $loan,
        LoanBalances $balances
    ): View {
        $canManage = $request->user()->can('loans.view');

        abort_unless(
            $canManage
                || $request->user()->can('loans.view_own'),
            403
        );

        $society = $request->attributes->get('currentSociety');
        $membership = $request->attributes->get('currentMembership');

        $record = Loan::query()
            ->where('society_id', $society->id)
            ->when(
                ! $canManage,
                fn ($query) => $query->where(
                    'society_membership_id',
                    $membership->id
                )
            )
            ->with([
                'society',
                'cycle',
                'membership.user',
                'submittedBy',
                'reviewer',
                'events.actor',
                'disbursement.recorder',
                'installments.allocations',
                'repayments.recorder',
                'repayments.allocations',
                'penalties.payments',
                'penalties.waivers',
                'interestCredits',
            ])
            ->findOrFail($loan);

        $balance = $record->disbursement
            ? $balances->summary($record)
            : null;

        return view('loans.show', [
            'loan' => $record,
            'society' => $society,
            'balance' => $balance,
            'disbursementKey' => (string) Str::uuid(),
        ]);
    }
}
