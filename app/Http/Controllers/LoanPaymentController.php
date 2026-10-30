<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\Loans\DisburseLoan;
use App\Services\Loans\LoanBalances;
use App\Services\Loans\RecordLoanRepayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanPaymentController extends Controller
{
    public function disburse(
        Request $request,
        int $loan,
        DisburseLoan $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('loans.disburse'),
            403
        );

        $data = $request->validate([
            'disbursed_on' => [
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
                'nullable', 'string', 'max:120',
            ],
            'notes' => [
                'nullable', 'string', 'max:1000',
            ],
            'idempotency_key' => [
                'required', 'uuid',
            ],
        ]);

        $society = $request->attributes->get(
            'currentSociety'
        );

        $service->execute(
            $society,
            $request->user(),
            $loan,
            $data
        );

        return redirect()
            ->route('loans.show', $loan)
            ->with(
                'status',
                'Loan disbursed successfully. Repayment schedule generated.'
            );
    }

    public function createRepayment(
        Request $request,
        int $loan,
        LoanBalances $balances
    ): View {
        abort_unless(
            $request->user()->can('loans.record_repayment'),
            403
        );

        $society = $request->attributes->get(
            'currentSociety'
        );

        $record = Loan::query()
            ->where('society_id', $society->id)
            ->whereKey($loan)
            ->with([
                'membership.user',
                'disbursement',
            ])
            ->firstOrFail();

        abort_unless(
            $record->status === 'disbursed',
            422,
            'This loan is not open for repayment.'
        );

        return view('loans.repay', [
            'loan' => $record,
            'society' => $society,
            'balance' => $balances->summary($record),
            'repaymentKey' => (string) Str::uuid(),
        ]);
    }

    public function storeRepayment(
        Request $request,
        int $loan,
        RecordLoanRepayment $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('loans.record_repayment'),
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
                'nullable', 'string', 'max:120',
            ],
            'notes' => [
                'nullable', 'string', 'max:1000',
            ],
            'idempotency_key' => [
                'required', 'uuid',
            ],
        ]);

        $society = $request->attributes->get(
            'currentSociety'
        );

        $service->execute(
            $society,
            $request->user(),
            $loan,
            $data
        );

        return redirect()
            ->route('loans.show', $loan)
            ->with('status', 'Loan repayment recorded successfully.');
    }
}
