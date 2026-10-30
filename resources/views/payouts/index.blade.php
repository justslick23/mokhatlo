
@extends('layouts.app')

@section('title', 'Year-End Payouts')

@section('content')

@php
    $money = fn ($cents) =>
        'M ' . number_format($cents / 100, 2);
@endphp

<div class="d-flex justify-content-between
            align-items-end flex-wrap gap-3 mb-4">
    <div>
        <p class="eyebrow mb-2">Financial Management</p>

        <h1 class="page-title">
            Year-End Payouts
        </h1>

        <p class="muted mt-2 mb-0">
            Calculate member entitlements, check outstanding
            obligations and prepare payout drafts.
        </p>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success">
        {{ session('status') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Select savings cycle --}}
<div class="card-soft mb-4">
    <div class="card-body">
        <form
            method="GET"
            action="{{ route('payouts.index') }}"
        >
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="cycle_id" class="form-label">
                        Savings Cycle
                    </label>

                    <select
                        id="cycle_id"
                        name="cycle_id"
                        class="form-select"
                        required
                    >
                        @foreach($cycles as $option)
                            <option
                                value="{{ $option->id }}"
                                @selected(
                                    $cycle
                                    && (int) $cycle->id
                                        === (int) $option->id
                                )
                            >
                                {{ $option->name }}
                                — {{ ucfirst($option->status) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <button
                        type="submit"
                        class="btn btn-outline-primary w-100"
                    >
                        <i class="bi bi-arrow-clockwise me-2"></i>
                        Calculate Preview
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@if($preview)

    {{-- Summary cards --}}
    <div class="row g-3 mb-4">
        @foreach([
            [
                'label' => 'Member Savings',
                'amount' => $preview['totals']['savings_cents'],
                'icon' => 'bi-piggy-bank',
                'tone' => 'purple',
            ],
            [
                'label' => 'Personal Interest Refunds',
                'amount' => $preview['totals']['interest_refund_cents'],
                'icon' => 'bi-graph-up-arrow',
                'tone' => 'blue',
            ],
            [
                'label' => 'Shared Penalties',
                'amount' => $preview['totals']['penalty_share_cents'],
                'icon' => 'bi-people',
                'tone' => 'teal',
            ],
            [
                'label' => 'Gross Payout Estimate',
                'amount' => $preview['totals']['gross_payout_cents'],
                'icon' => 'bi-wallet2',
                'tone' => 'amber',
            ],
        ] as $metric)
            <div class="col-md-6 col-xl-3">
                <div class="mk-analytics-card h-100">
                    <span class="mk-analytics-icon
                                 tone-{{ $metric['tone'] }} mb-3">
                        <i class="bi {{ $metric['icon'] }}"></i>
                    </span>

                    <p class="mk-analytics-label">
                        {{ $metric['label'] }}
                    </p>

                    <div class="mk-analytics-value">
                        {{ $money($metric['amount']) }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Reconciliation --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card-soft h-100">
                <div class="card-body">
                    <h2 class="section-title mb-3">
                        Recorded Funds
                    </h2>

                    <p class="metric-label mb-2">
                        Society Liquid Assets
                    </p>

                    <div class="metric-value">
                        {{ $money(
                            $preview['liquid_assets_cents']
                        ) }}
                    </div>

                    <p class="small muted mt-3 mb-0">
                        Based on recorded cash, bank and mobile-money
                        balances, before bank reconciliation.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card-soft h-100">
                <div class="card-body">
                    <h2 class="section-title mb-3">
                        Penalty Distribution
                    </h2>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="muted">
                            Collected Penalty Pool
                        </span>
                        <strong>
                            {{ $money($preview['penalty_pool_cents']) }}
                        </strong>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="muted">Allocated to Members</span>
                        <strong>
                            {{ $money(
                                $preview['penalty_distributed_cents']
                            ) }}
                        </strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="muted">
                            Reserved / Unallocated
                        </span>
                        <strong>
                            {{ $money(
                                $preview['penalty_reserved_cents']
                            ) }}
                        </strong>
                    </div>

                    <p class="small muted mt-3 mb-0">
                        Policy:
                        {{ ucwords(str_replace(
                            '_',
                            ' ',
                            $preview['distribution_method']
                        )) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Readiness warnings --}}
    <div class="card-soft mb-4">
        <div class="card-body">

            <div class="d-flex align-items-center gap-3 mb-3">
                <i class="bi bi-shield-exclamation fs-4"></i>
                <h2 class="section-title mb-0">
                    Payout Readiness
                </h2>
            </div>

            @if(count($preview['blockers']) > 0)
                <div class="alert alert-warning mb-0">
                    <strong class="d-block mb-3">
                        {{ count($preview['blockers']) }}
                        issue(s) require attention
                    </strong>

                    <ul class="mb-0">
                        @foreach($preview['blockers'] as $issue)
                            <li class="mb-2">
                                {{ $issue }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle me-2"></i>
                    No automated reconciliation blockers detected.

                    <p class="small mb-0 mt-2">
                        Bank reconciliation, member eligibility
                        and payout approval are still required.
                    </p>
                </div>
            @endif

        </div>
    </div>

    {{-- Member allocations --}}
    <div class="card-soft mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between
                        align-items-start gap-3 flex-wrap mb-4">
                <div>
                    <h2 class="section-title mb-1">
                        Member Payout Breakdown
                    </h2>

                    <p class="small muted mb-0">
                        Calculated through
                        {{ $preview['as_of_date'] }}
                    </p>
                </div>

                <span class="badge bg-warning-subtle
                             text-warning-emphasis">
                    Provisional
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mk-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Savings</th>
                            <th>Interest Refund</th>
                            <th>Penalty Share</th>
                            <th class="text-end">Gross Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($preview['rows'] as $row)
                            <tr>
                                <td>
                                    <strong>
                                        {{ $row['member_name'] }}
                                    </strong>

                                    <div class="small muted">
                                        {{ $row['member_number'] ?? 'Member' }}
                                        &middot;
                                        {{ ucfirst(
                                            $row['membership_status']
                                        ) }}
                                    </div>
                                </td>

                                <td>
                                    {{ $money($row['savings_cents']) }}
                                </td>

                                <td>
                                    {{ $money(
                                        $row['interest_refund_cents']
                                    ) }}
                                </td>

                                <td>
                                    {{ $money(
                                        $row['penalty_share_cents']
                                    ) }}
                                </td>

                                <td class="text-end fw-bold">
                                    {{ $money(
                                        $row['gross_payout_cents']
                                    ) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5"
                                    class="text-center muted py-4">
                                    No eligible membership records.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Create a numbered snapshot --}}
    @can('payouts.prepare')
    @if(! $drafts->contains(fn ($draft) => $draft->approval !== null))
        <div class="card-soft mb-4">
            <div class="card-body p-lg-4">
                <h2 class="section-title mb-2">
                    Save Payout Draft
                </h2>

                <p class="small muted mb-4">
                    Save these calculations for review.
                    Creating a draft does not approve or pay anyone.
                </p>

                <form
                    method="POST"
                    action="{{ route('payouts.drafts.store') }}"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="cycle_id"
                        value="{{ $cycle->id }}"
                    >

                    <input
                        type="hidden"
                        name="request_key"
                        value="{{ old('request_key', $requestKey) }}"
                    >

                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label
                                for="as_of_date"
                                class="form-label"
                            >
                                Calculation Cutoff Date
                            </label>

                           
                            <input
                            type="date"
                            id="as_of_date"
                            class="form-control"
                            value="{{ now('Africa/Maseru')->toDateString() }}"
                            disabled
                            >

                            <input
                            type="hidden"
                            name="as_of_date"
                            value="{{ now('Africa/Maseru')->toDateString() }}"
                            >

                            <div class="form-text">
                            Calculations use today's recorded financial position.
                            </div>

                        </div>

                        <div class="col-md-6">
                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                                data-submit-once
                            >
                                <i class="bi bi-file-earmark-plus me-2"></i>
                                Save New Payout Draft
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif
    @endcan

@endif

{{-- Draft history --}}
<div class="card-soft">
    <div class="card-body">
        <h2 class="section-title mb-4">
            Saved Payout Drafts
        </h2>

        <div class="table-responsive">
            <table class="table align-middle mk-table">
                <thead>
                    <tr>
                        <th>Revision</th>
                        <th>Cutoff</th>
                        <th>Created By</th>
                        <th>Created</th>
                        <th>Gross Payout</th>
                        <th>Issues</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($drafts as $draft)
                        <tr>
                            <td class="fw-semibold">
                                #{{ $draft->revision }}
                            </td>

                            <td>
                                {{ $draft->as_of_date->format('d M Y') }}
                            </td>

                            <td>
                                {{ $draft->creator?->name ?? 'System' }}
                            </td>

                            <td>
                                {{ $draft->created_at->format('d M Y H:i') }}
                            </td>

                            <td class="fw-semibold">
                                {{ $money(
                                    $draft->gross_total_cents
                                ) }}
                            </td>

                            
<td>
    @if($draft->approval)
        <span class="badge bg-success-subtle text-success">
            Approved
        </span>
    @elseif($draft->reconciliation)
        <span class="badge bg-info-subtle text-info-emphasis">
            Reconciled
        </span>
    @else
        <span class="badge bg-warning-subtle text-warning-emphasis">
            Draft
        </span>
    @endif

    @if(count($draft->blockers ?? []) > 0)
        <div class="small muted mt-1">
            {{ count($draft->blockers) }}
            unresolved issue(s)
        </div>
    @endif
</td>


                            <td class="text-end">
                                <a
                                    href="{{ route(
                                        'payouts.drafts.show',
                                        $draft->id
                                    ) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"
                                class="text-center muted py-4">
                                No payout drafts have been created.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
