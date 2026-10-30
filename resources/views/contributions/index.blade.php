
@extends('layouts.app')

@section('title', 'Contributions')

@section('content')

<div class="d-flex justify-content-between align-items-end
            flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">Financial Management</p>

        <h1 class="page-title">
            {{ $canManage ? 'Contributions' : 'My Contributions' }}
        </h1>

        <p class="muted mt-2 mb-0">
            {{ $canManage
                ? 'Manage monthly savings and payment records.'
                : 'Track your monthly savings and outstanding contributions.'
            }}
        </p>
    </div>

</div>

<!-- Cycle Selection -->
<div class="card-soft mb-4">
    <div class="card-body">

        <form method="GET"
              action="{{ route('contributions.index') }}">

            <div class="row align-items-end g-3">

                <div class="col-md-8">
                    <label class="form-label" for="cycle_id">
                        Savings Cycle
                    </label>

                    <select
                        name="cycle_id"
                        id="cycle_id"
                        class="form-select"
                    >
                        @foreach($cycles as $item)
                            <option
                                value="{{ $item->id }}"
                                @selected($cycle?->id === $item->id)
                            >
                                {{ $item->name }}
                                ({{ ucfirst($item->status) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <button type="submit"
                            class="btn btn-outline-primary w-100">
                        <i class="bi bi-funnel me-2"></i>
                        View Contributions
                    </button>
                </div>

            </div>

        </form>
    </div>
</div>

@if(!$cycle)

    <div class="card-soft">
        <div class="empty-state">
            <i class="bi bi-calendar-x fs-2 d-block mb-3"></i>

            <h2 class="h5 fw-bold">
                No active savings cycle
            </h2>

            <p class="mb-0">
                Contributions will become available once
                your society activates a savings cycle.
            </p>
        </div>
    </div>

@else

  
@php
$requiredTotal = 0;
$receivedTotal = 0;
$outstandingTotal = 0;

foreach ($obligations as $item) {
    $required = \App\Support\Money::toCents(
        $item->required_amount
    );

    $received = \App\Support\Money::toCents(
        $item->paid_total ?? '0.00'
    );

    $requiredTotal += $required;
    $receivedTotal += $received;

    // Calculate per obligation before aggregating.
    $outstandingTotal += max(0, $required - $received);
}
@endphp


    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card-soft h-100">
                <div class="card-body">
                    <span class="metric-label">
                        Required Contributions
                    </span>

                    <div class="metric-value">
                        M {{ number_format($requiredTotal / 100, 2) }}
                    </div>

                    <p class="small muted mb-0 mt-2">
                        For obligations on this page
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card-soft h-100">
                <div class="card-body">
                    <span class="metric-label">
                        Contributions Received
                    </span>

                    <div class="metric-value">
                        M {{ number_format($receivedTotal / 100, 2) }}
                    </div>

                    <p class="small muted mb-0 mt-2">
                        For obligations on this page
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card-soft h-100">
                <div class="card-body">
                    <span class="metric-label">
                        Outstanding Minimums
                    </span>

                    <div class="metric-value">
                        M {{ number_format($outstandingTotal / 100, 2) }}
                    </div>

                    <p class="small muted mb-0 mt-2">
                        For obligations on this page
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- Contribution Register -->
    <div class="card-soft">
        <div class="card-body">

            <div class="d-flex justify-content-between
                        align-items-center gap-3 mb-4">

                <h2 class="section-title mb-0">
                    Contribution Register
                </h2>

                <span class="small muted">
                    {{ $cycle->name }}
                </span>

            </div>

            <div class="table-responsive">

                <table class="table table-hover
                              align-middle mk-table">

                    <thead>
                        <tr>
                            @if($canManage)
                                <th>Member</th>
                            @endif

                            <th>Period</th>
                            <th>Required</th>
                            <th>Received</th>
                            <th>Outstanding</th>
                            <th>Deadline</th>
                            <th>Status</th>

                            @can('contributions.record')
                                <th class="text-end">Action</th>
                            @endcan
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($obligations as $obligation)

                            @php
                                $required = \App\Support\Money::toCents(
                                    $obligation->required_amount
                                );

                                $received = \App\Support\Money::toCents(
                                    $obligation->paid_total ?? '0.00'
                                );

                                $outstanding = max(
                                    0,
                                    $required - $received
                                );

                                $overdue = \Carbon\CarbonImmutable::today(
                                    $society->timezone
                                )->greaterThan(
                                    \Carbon\CarbonImmutable::parse(
                                        $obligation->due_date->toDateString()
                                    )->addDays(
                                        (int) ($cycle->policy?->contribution_grace_days ?? 0)
                                    )
                                );

                                $status = match(true) {
                                    $outstanding === 0 => 'Paid',
                                    $overdue => 'Overdue',
                                    $received > 0 => 'Partially Paid',
                                    default => 'Pending',
                                };

                                $badge = match($status) {
                                    'Paid' => 'bg-success-subtle text-success',
                                    'Overdue' => 'bg-danger-subtle text-danger-emphasis',
                                    'Partially Paid' => 'bg-warning-subtle text-warning-emphasis',
                                    default => 'bg-secondary-subtle text-secondary-emphasis',
                                };
                            @endphp

                            <tr>

                                @if($canManage)
                                    <td>
                                        <strong>
                                            {{ $obligation->membership->user->name }}
                                        </strong>
                                        <div class="small muted">
                                            {{ $obligation->membership->member_number }}
                                        </div>
                                    </td>
                                @endif

                                <td>
                                    {{ $obligation->period_start->format('M Y') }}
                                </td>

                                <td>
                                    M {{ number_format($required / 100, 2) }}
                                </td>

                                <td>
                                    <span class="text-success fw-semibold">
                                        M {{ number_format($received / 100, 2) }}
                                    </span>
                                </td>

                                <td>
                                    M {{ number_format($outstanding / 100, 2) }}
                                </td>

                                <td>
                                    {{ $obligation->due_date->format('d M Y') }}
                                </td>

                                <td>
                                    <span class="badge {{ $badge }}">
                                        {{ $status }}
                                    </span>
                                </td>

                                @can('contributions.record')
                                    <td class="text-end">
                                        @if($cycle->status === 'active')
                                            <a
                                                href="{{ route('contributions.create', $obligation->id) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Record Payment
                                            </a>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                @endcan

                            </tr>

                        @empty

                            <tr>
                                <td colspan="{{ $canManage ? 8 : 7 }}">
                                    <div class="empty-state">

                                        <i class="bi bi-wallet2 fs-2 d-block mb-3"></i>

                                        <h3 class="h6 fw-bold">
                                            No contribution obligations
                                        </h3>

                                        <p class="mb-0">
                                            No contribution obligations
                                            have been generated for this cycle.
                                        </p>

                                    </div>
                                </td>
                            </tr>

                        @endforelse
                    </tbody>

                </table>
            </div>

            <div class="mt-4">
                {{ $obligations->links() }}
            </div>

        </div>
    </div>

@endif

@endsection
