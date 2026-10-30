<?php

namespace App\Http\Controllers;

use App\Models\CyclePayoutDraft;
use App\Services\Payouts\ApproveCyclePayoutDraft;
use App\Services\Payouts\CalculateCyclePayout;
use App\Services\Payouts\CreateCyclePayoutDraft;
use App\Services\Payouts\CurrentSocietyCashBalances;
use App\Services\Payouts\ReconcileCyclePayoutDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CyclePayoutController extends Controller
{
    public function index(
        Request $request,
        CalculateCyclePayout $calculator
    ): View {
        abort_unless(
            $request->user()->can('payouts.view'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        $filters = $request->validate([
            'cycle_id' => ['nullable', 'integer'],
        ]);

        $cycles = $society->cycles()
            ->orderByDesc('start_date')
            ->get();

        $cycle = ! empty($filters['cycle_id'])
            ? $society->cycles()
                ->findOrFail($filters['cycle_id'])
            : (
                $cycles->firstWhere('status', 'active')
                ?? $cycles->first()
            );

        $preview = $cycle
            ? $calculator->build($society, $cycle->id)
            : null;

        $drafts = CyclePayoutDraft::query()
            ->where('society_id', $society->id)
            ->when(
                $cycle,
                fn ($query) => $query->where(
                    'cycle_id',
                    $cycle->id
                )
            )
            ->with(['creator', 'reconciliation', 'approval'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('payouts.index', [
            'society' => $society,
            'cycles' => $cycles,
            'cycle' => $cycle,
            'preview' => $preview,
            'drafts' => $drafts,
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function store(
        Request $request,
        CreateCyclePayoutDraft $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('payouts.prepare'),
            403
        );

        $data = $request->validate([
            'cycle_id' => ['required', 'integer'],

            'as_of_date' => [
                'required',
                'date_format:Y-m-d',
                Rule::in([
                    now('Africa/Maseru')->toDateString(),
                ]),
            ],

            'request_key' => ['required', 'uuid'],
        ]);

        $society = $request->attributes->get('currentSociety');

        $draft = $service->execute(
            $society,
            $request->user(),
            (int) $data['cycle_id'],
            $data['request_key'],
            $data['as_of_date']
        );

        return redirect()
            ->route('payouts.drafts.show', $draft->id)
            ->with(
                'status',
                'Payout draft saved. No funds have been transferred.'
            );
    }

    public function show(
        Request $request,
        int $draft,
        CurrentSocietyCashBalances $balances
    ): View {
        abort_unless(
            $request->user()->can('payouts.view'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        $record = CyclePayoutDraft::query()
            ->where('society_id', $society->id)
            ->with([
                'cycle',
                'creator',
                'items',
                'reconciliation.confirmer',
                'approval.approver',
            ])
            ->findOrFail($draft);

        $today = now('Africa/Maseru')->toDateString();

        $isCurrent = $record->as_of_date
            ->toDateString() === $today;

        $canReconcile = $request->user()
            ->can('payouts.reconcile')
            && $record->status === 'draft'
            && ! $record->reconciliation
            && $isCurrent;

        $canApprove = $request->user()
            ->can('payouts.approve')
            && $record->status === 'draft'
            && $record->reconciliation
            && $isCurrent
            && count($record->blockers ?? []) === 0
            && (int) $record->created_by
                !== (int) $request->user()->id
            && (int) $record->reconciliation->confirmed_by
                !== (int) $request->user()->id;

        return view('payouts.show', [
            'society' => $society,
            'draft' => $record,
            'ledgerBalances' => $balances->build($society),
            'canReconcile' => $canReconcile,
            'canApprove' => $canApprove,
            'reconciliationKey' => (string) Str::uuid(),
            'approvalKey' => (string) Str::uuid(),
        ]);
    }

    public function reconcile(
        Request $request,
        int $draft,
        ReconcileCyclePayoutDraft $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('payouts.reconcile'),
            403
        );

        $data = $request->validate([
            'cash_amount' => [
                'required',
                'regex:/^\d{1,15}(\.\d{1,2})?$/',
            ],

            'bank_amount' => [
                'required',
                'regex:/^\d{1,15}(\.\d{1,2})?$/',
            ],

            'mobile_money_amount' => [
                'required',
                'regex:/^\d{1,15}(\.\d{1,2})?$/',
            ],

            'cash_count_notes' => [
                'required', 'string', 'min:10', 'max:2000',
            ],

            'bank_statement_reference' => [
                'nullable', 'string', 'max:160',
            ],

            'mobile_money_reference' => [
                'nullable', 'string', 'max:160',
            ],

            'notes' => ['nullable', 'string', 'max:2000'],

            'request_key' => ['required', 'uuid'],

            'confirmed' => ['accepted'],
        ]);

        $society = $request->attributes->get('currentSociety');

        $service->execute(
            $society,
            $request->user(),
            $draft,
            $data
        );

        return redirect()
            ->route('payouts.drafts.show', $draft)
            ->with(
                'status',
                'Balances reconciled successfully. ' .
                'The payout still requires independent approval.'
            );
    }

    public function approve(
        Request $request,
        int $draft,
        ApproveCyclePayoutDraft $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('payouts.approve'),
            403
        );

        $data = $request->validate([
            'approval_notes' => [
                'required', 'string', 'min:10', 'max:2000',
            ],

            'request_key' => ['required', 'uuid'],

            'approved_terms' => ['accepted'],
        ]);

        $society = $request->attributes->get('currentSociety');

        $service->execute(
            $society,
            $request->user(),
            $draft,
            $data
        );

        return redirect()
            ->route('payouts.drafts.show', $draft)
            ->with(
                'status',
                'Payout batch approved. ' .
                'No payments or ledger postings have been executed.'
            );
    }
}
