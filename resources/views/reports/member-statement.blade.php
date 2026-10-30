
@extends('layouts.app')

@section('title', 'Member Statement')

@section('content')

@php
    $money = fn (int $cents) =>
        'M ' . number_format($cents / 100, 2);

    $savingsPaid = $rows
        ->where('category', 'Savings')
        ->sum('amount_cents');

    $loansReceived = $rows
        ->where('category', 'Loan')
        ->sum('amount_cents');

    $repaymentsPaid = $rows
        ->where('category', 'Loan Repayment')
        ->sum('amount_cents');

    $interestRefunds = $rows
        ->where('category', 'Loan Interest Refund')
        ->sum('amount_cents');
@endphp
@include('reports.partials.historical-contribution-coverage')
<div class="d-flex justify-content-between
            align-items-end flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">Member Finance</p>

        <h1 class="page-title">
            Member Statement
        </h1>

        <p class="muted mt-2 mb-0">
            {{ $membership->user->name }}
            &middot;
            {{ $society->name }}
        </p>
    </div>

    <a
        href="{{ route('reports.member-statement.export', [
            'membership_id' => $membership->id,
        ]) }}"
        class="btn btn-primary"
    >
        <i class="bi bi-download me-2"></i>
        Download Statement
    </a>
</div>

{{-- Select member for authorised officials --}}
@if($canViewAll)

    <div class="card-soft mb-4">
        <div class="card-body">

            <form method="GET"
                  action="{{ route('reports.member-statement') }}">

                <div class="row g-3 align-items-end">

                    <div class="col-md-8">
                        <label
                            for="membership_id"
                            class="form-label"
                        >
                            Select Society Member
                        </label>

                        <select
                            name="membership_id"
                            id="membership_id"
                            class="form-select"
                        >
                            @foreach($memberships as $item)
                                <option
                                    value="{{ $item->id }}"
                                    @selected(
                                        (int) $item->id
                                        === (int) $membership->id
                                    )
                                >
                                    {{ $item->user->name }}
                                    — {{ $item->member_number ?? 'Member' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <button type="submit"
                                class="btn btn-outline-primary w-100">
                            View Statement
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

@endif

{{-- Member summary --}}
<div class="row g-3 mb-4">

    @foreach([
        [
            'label' => 'Savings Contributions',
            'value' => $savingsPaid,
            'icon' => 'bi-piggy-bank'
        ],
        [
            'label' => 'Loan Funds Received',
            'value' => $loansReceived,
            'icon' => 'bi-wallet2'
        ],
        [
            'label' => 'Loan Repayments Paid',
            'value' => $repaymentsPaid,
            'icon' => 'bi-arrow-left-right'
        ],
        [
            'label' => 'My Refundable Loan Interest',
            'value' => $interestRefunds,
            'icon' => 'bi-graph-up-arrow'
        ]
    ] as $metric)

        <div class="col-sm-6 col-xl-3">
            <div class="mk-analytics-card h-100">

                <span class="mk-analytics-icon tone-purple mb-3">
                    <i class="bi {{ $metric['icon'] }}"></i>
                </span>

                <p class="mk-analytics-label">
                    {{ $metric['label'] }}
                </p>

                <div class="mk-analytics-value">
                    {{ $money($metric['value']) }}
                </div>

            </div>
        </div>

    @endforeach

</div>

{{-- Transaction history --}}
<div class="card-soft">
    <div class="card-body">

        <div class="d-flex justify-content-between
                    align-items-center mb-4">

            <h2 class="section-title mb-0">
                Financial Activity
            </h2>

            <span class="small muted">
                {{ $rows->count() }} transactions
            </span>
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle mk-table">

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Direction</th>
                        <th>Reference</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $row)

                        @php
                            $directionClass = match(
                                $row['direction']
                            ) {
                                'received' =>
                                    'bg-success-subtle text-success',

                                'earned' =>
                                    'bg-primary-subtle text-primary-emphasis',

                                'adjustment' =>
                                    'bg-secondary-subtle text-secondary-emphasis',

                                default =>
                                    'bg-warning-subtle text-warning-emphasis',
                            };
                        @endphp

                        <tr>
                            <td>
                                {{ \Carbon\Carbon::parse(
                                    $row['date']
                                )->format('d M Y') }}
                            </td>

                            <td>
                                {{ $row['category'] }}
                            </td>

                            <td>
                                {{ $row['description'] }}
                            </td>

                            <td>
                                <span class="badge {{ $directionClass }}">
                                    {{ ucfirst($row['direction']) }}
                                </span>
                            </td>

                            <td class="small muted">
                                {{ $row['reference'] ?? '—' }}
                            </td>

                            <td class="text-end fw-semibold">
                                {{ $money(
                                    $row['amount_cents']
                                ) }}
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-receipt fs-2 d-block mb-3"></i>

                                    <strong>
                                        No financial activity
                                    </strong>

                                    <p class="muted mt-2 mb-0">
                                        Transactions will appear here
                                        when recorded.
                                    </p>
                                </div>
                            </td>
                        </tr>

                    @endforelse
                </tbody>

            </table>

        </div>
    </div>
</div>

<div class="small muted mt-3">
    Amounts and directions are shown from the member's perspective.
    Refundable loan interest is an entitlement for year-end payout,
    not a payout already received. Penalty waivers are non-cash adjustments.
</div>

@endsection
