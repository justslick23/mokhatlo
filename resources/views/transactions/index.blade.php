
@extends('layouts.app')

@section('title', 'Financial Transactions')

@section('content')

<div class="mb-4">
    <p class="eyebrow mb-2">Accounting & Finance</p>

    <h1 class="page-title">Financial Transactions</h1>

    <p class="muted mt-2 mb-0">
        Review posted financial activity and balanced
        ledger entries for {{ $society->name }}.
    </p>
</div>

<!-- Filters -->
<div class="card-soft mb-4">
    <div class="card-body">

        <form method="GET"
              action="{{ route('transactions.index') }}">

            <div class="row g-3 align-items-end">

                <div class="col-md-3">
                    <label for="cycle_id" class="form-label">
                        Savings Cycle
                    </label>

                    <select
                        name="cycle_id"
                        id="cycle_id"
                        class="form-select"
                    >
                        <option value="">All Cycles</option>

                        @foreach($cycles as $cycle)
                            <option
                                value="{{ $cycle->id }}"
                                @selected(
                                    (string) request('cycle_id')
                                        === (string) $cycle->id
                                )
                            >
                                {{ $cycle->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="type" class="form-label">
                        Transaction Type
                    </label>

                    <select name="type"
                            id="type"
                            class="form-select">

                        <option value="">All Types</option>

                        <option
                            value="contribution_payment"
                            @selected(request('type') === 'contribution_payment')
                        >
                            Contributions
                        </option>

                        <option
                            value="contribution_penalty_payment"
                            @selected(request('type') === 'contribution_penalty_payment')
                        >
                            Penalty Payments
                        </option>

                        
                        <option
                        value="loan_disbursement"
                        @selected(request('type') === 'loan_disbursement')
                        >
                        Loan Disbursements
                        </option>

                        <option
                        value="loan_repayment"
                        @selected(request('type') === 'loan_repayment')
                        >
                        Loan Repayments
                        </option>

                        
                        <option value="loan_penalty_payment"
                        @selected(request('type') === 'loan_penalty_payment')}>
                        Loan Penalty Payments
                        </option>


                    </select>
                </div>

                <div class="col-md-2">
                    <label for="date_from" class="form-label">
                        From
                    </label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        class="form-control"
                        value="{{ request('date_from') }}"
                    >
                </div>

                <div class="col-md-2">
                    <label for="date_to" class="form-label">
                        To
                    </label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        class="form-control"
                        value="{{ request('date_to') }}"
                    >
                </div>

                <div class="col-md-2">
                    <button type="submit"
                            class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-2"></i>
                        Filter
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- Transactions -->
<div class="card-soft">
    <div class="card-body">

        <div class="d-flex justify-content-between
                    align-items-center mb-4">

            <h2 class="section-title mb-0">
                Transaction Register
            </h2>

            <span class="small muted">
                {{ $transactions->total() }} records
            </span>
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle mk-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Recorded By</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($transactions as $transaction)

                        @php
                            $debits = $transaction->lines->sum(
                                fn ($line) =>
                                    \App\Support\Money::toCents(
                                        $line->debit_amount
                                    )
                            );

                            $credits = $transaction->lines->sum(
                                fn ($line) =>
                                    \App\Support\Money::toCents(
                                        $line->credit_amount
                                    )
                            );

                            $balanced = $debits === $credits;

                           
                            $typeLabel = match($transaction->source_type) {
                                'contribution_payment' => 'Contribution',
                                'contribution_penalty_payment' => 'Penalty Payment',
                                'loan_disbursement' => 'Loan Disbursement',
                                'loan_penalty_payment' => 'Loan Penalty Payment',
                                'loan_repayment' => 'Loan Repayment',
                                default => ucwords(str_replace(
                                    '_',
                                    ' ',
                                    $transaction->source_type
                                )),
                            };

                        @endphp

                        <tr>
                            <td>
                                {{ $transaction->posting_date->format('d M Y') }}
                            </td>

                            <td>
                                <strong>
                                    TXN-{{ str_pad(
                                        $transaction->id,
                                        6,
                                        '0',
                                        STR_PAD_LEFT
                                    ) }}
                                </strong>

                                <div class="small muted mt-1">
                                    {{ $typeLabel }}
                                </div>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    {{ $transaction->description }}
                                </div>

                                <details class="mk-ledger-details mt-2">
                                    <summary>
                                        View Ledger Entries
                                    </summary>

                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm
                                                      mk-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Account</th>
                                                    <th class="text-end">Debit</th>
                                                    <th class="text-end">Credit</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach($transaction->lines as $line)
                                                    <tr>
                                                        <td>
                                                            {{ $line->account->name }}
                                                        </td>

                                                        <td class="text-end">
                                                            M {{ number_format(
                                                                (float) $line->debit_amount,
                                                                2
                                                            ) }}
                                                        </td>

                                                        <td class="text-end">
                                                            M {{ number_format(
                                                                (float) $line->credit_amount,
                                                                2
                                                            ) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                </details>
                            </td>

                            <td>
                                {{ $transaction->creator?->name ?? 'System' }}
                            </td>

                            <td class="text-end">
                                <strong>
                                    M {{ number_format($debits / 100, 2) }}
                                </strong>

                                <div class="small mt-1">
                                    @if($balanced)
                                        <span class="text-success">
                                            <i class="bi bi-check-circle"></i>
                                            Balanced
                                        </span>
                                    @else
                                        <span class="text-danger">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Unbalanced
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">

                                    <i class="bi bi-journal-text fs-2 d-block mb-3"></i>

                                    <h3 class="h6 fw-bold">
                                        No financial transactions
                                    </h3>

                                    <p class="mb-0">
                                        Posted contributions and penalty
                                        payments will appear here.
                                    </p>

                                </div>
                            </td>
                        </tr>

                    @endforelse
                </tbody>
            </table>

        </div>

        <div class="mt-4">
            {{ $transactions->links() }}
        </div>

    </div>
</div>

@endsection
