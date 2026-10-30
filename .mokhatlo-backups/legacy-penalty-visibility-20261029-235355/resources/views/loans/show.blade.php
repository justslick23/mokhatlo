
@extends('layouts.app')

@section('title', 'Loan Details')

@section('content')

@php
    $money = fn ($cents) =>
        'M ' . number_format($cents / 100, 2);

    $statusClass = match($loan->status) {
        'approved' => 'bg-primary-subtle text-primary-emphasis',
        'disbursed' => 'bg-info-subtle text-info-emphasis',
        'settled' => 'bg-success-subtle text-success',
        'rejected' => 'bg-danger-subtle text-danger-emphasis',
        default => 'bg-secondary-subtle text-secondary-emphasis',
    };

    $canDisburse =
        auth()->user()->can('loans.disburse')
        && $loan->status === 'approved'
        && $loan->cycle->status === 'active';

    $canRecordRepayment =
        auth()->user()->can('loans.record_repayment')
        && $loan->status === 'disbursed';

    $interestRefundCents = $loan->interestCredits->sum(
        fn ($credit) => \App\Support\Money::toCents(
            $credit->amount
        )
    );

    $penaltyOutstandingCents = $loan->penalties->sum(
        fn ($penalty) => $penalty->outstandingCents()
    );
@endphp

<div class="mb-4">
    <a href="{{ route('loans.index') }}" class="small fw-semibold">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Loans
    </a>
</div>

<div class="d-flex justify-content-between align-items-end
            flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">Loan Management</p>

        <h1 class="page-title">
            {{ $loan->loan_number }}
        </h1>

        <p class="muted mt-2 mb-0">
            Borrower: {{ $loan->membership->user->name }}
        </p>
    </div>

    <span class="badge {{ $statusClass }} px-3 py-2">
        {{ ucfirst($loan->status) }}
    </span>

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

{{-- Contract summary --}}
<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-3">
        <div class="card-soft h-100">
            <div class="card-body">
                <div class="metric-label">Principal</div>
                <div class="metric-value fs-4">
                    M {{ number_format(
                        (float) $loan->principal_amount,
                        2
                    ) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-soft h-100">
            <div class="card-body">
                <div class="metric-label">Contractual Interest</div>
                <div class="metric-value fs-4">
                    M {{ number_format(
                        (float) $loan->quoted_interest_amount,
                        2
                    ) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-soft h-100">
            <div class="card-body">
                <div class="metric-label">Total Repayment</div>
                <div class="metric-value fs-4">
                    M {{ number_format(
                        (float) $loan->quoted_total_amount,
                        2
                    ) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-soft h-100">
            <div class="card-body">
                <div class="metric-label">
                    Interest Credited to Borrower
                </div>
                <div class="metric-value fs-4">
                    {{ $money($interestRefundCents) }}
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Actual outstanding balance --}}
@if($balance)
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card-soft h-100">
                <div class="card-body">
                    <div class="metric-label">
                        Principal Outstanding
                    </div>
                    <div class="metric-value fs-4">
                        {{ $money($balance['principal_outstanding']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card-soft h-100">
                <div class="card-body">
                    <div class="metric-label">
                        Interest Outstanding
                    </div>
                    <div class="metric-value fs-4">
                        {{ $money($balance['interest_outstanding']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card-soft h-100">
                <div class="card-body">
                    <div class="metric-label">
                        Total Contractual Balance
                    </div>
                    <div class="metric-value fs-4">
                        {{ $money($balance['total_outstanding']) }}
                    </div>
                </div>
            </div>
        </div>

    </div>
@endif

{{-- Penalties are separate from loan balance --}}
@if($loan->penalties->isNotEmpty())
    <div class="card-soft mb-4">
        <div class="card-body d-flex justify-content-between
                    align-items-center flex-wrap gap-3">

            <div>
                <div class="metric-label mb-2">
                    Outstanding Loan Penalties
                </div>

                <div class="metric-value fs-4">
                    {{ $money($penaltyOutstandingCents) }}
                </div>

                <p class="small muted mt-2 mb-0">
                    Penalties are shared under the society's policy,
                    not refunded individually like loan interest.
                </p>
            </div>

            @if(
                auth()->user()->can('loans.penalties.view')
                || auth()->user()->can('loans.penalties.view_own')
            )
                <a href="{{ route('loans.penalties.index') }}"
                   class="btn btn-outline-primary">
                    View Loan Penalties
                </a>
            @endif

        </div>
    </div>
@endif

{{-- Confirm actual disbursement --}}
@if($canDisburse)
    <div class="card-soft mb-4">
        <div class="card-body p-lg-4">

            <h2 class="section-title mb-3">
                Record Loan Disbursement
            </h2>

            <div class="mk-info-panel mb-4">
                <p class="small mb-0">
                    The loan has already been approved verbally.
                    Record the actual release of funds to generate
                    its repayment deadline and ledger entries.
                </p>
            </div>

            <form method="POST"
                  action="{{ route('loans.disburse', $loan->id) }}">
                @csrf

                <input type="hidden"
                       name="idempotency_key"
                       value="{{ old('idempotency_key', $disbursementKey) }}">

                <div class="row g-3 mb-4">

                    <div class="col-md-4">
                        <label for="disbursed_on" class="form-label">
                            Actual Disbursement Date
                        </label>

                        <input
                            type="date"
                            name="disbursed_on"
                            id="disbursed_on"
                            class="form-control"
                            min="{{ \Carbon\Carbon::parse($loan->cycle->start_date)->toDateString() }}"
                            max="{{ now('Africa/Maseru')->toDateString() }}"
                            value="{{ old('disbursed_on', now('Africa/Maseru')->toDateString()) }}"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="payment_method" class="form-label">
                            Disbursement Method
                        </label>

                        <select name="payment_method"
                                id="payment_method"
                                class="form-select"
                                required>
                            <option value="cash"
                                @selected(old('payment_method') === 'cash')>
                                Cash
                            </option>
                            <option value="bank_transfer"
                                @selected(old('payment_method') === 'bank_transfer')>
                                Bank Transfer
                            </option>
                            <option value="mobile_money"
                                @selected(old('payment_method') === 'mobile_money')>
                                Mobile Money
                            </option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="payment_reference" class="form-label">
                            Transaction Reference
                        </label>

                        <input
                            type="text"
                            name="payment_reference"
                            id="payment_reference"
                            maxlength="120"
                            class="form-control"
                            value="{{ old('payment_reference') }}"
                        >
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">
                            Notes
                        </label>
                        <textarea name="notes"
                                  id="notes"
                                  class="form-control"
                                  rows="2"
                                  maxlength="1000">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <button type="submit"
                        class="btn btn-primary"
                        data-submit-once
                        onclick="return confirm('Confirm that the funds were actually released?');">
                    <i class="bi bi-check-circle me-2"></i>
                    Record Disbursement
                </button>

            </form>
        </div>
    </div>
@endif

{{-- Repayment action --}}
@if($loan->disbursement)
    <div class="card-soft mb-4">
        <div class="card-body d-flex justify-content-between
                    align-items-center flex-wrap gap-3">

            <div>
                <strong class="d-block">
                    Disbursed:
                    {{ $loan->disbursement->disbursed_on->format('d M Y') }}
                </strong>

                <div class="small muted mt-1">
                    Repayment deadline:
                    <strong>
                        {{ $loan->matures_on
                            ? $loan->matures_on->format('d M Y')
                            : 'Not available' }}
                    </strong>
                </div>

                <div class="small muted mt-1">
                    One or multiple repayments are permitted.
                </div>
            </div>

            @if($canRecordRepayment)
                <a
                    href="{{ route('loans.repayments.create', $loan->id) }}"
                    class="btn btn-primary"
                >
                    <i class="bi bi-plus-circle me-2"></i>
                    Record Repayment
                </a>
            @endif
        </div>
    </div>
@endif

<div class="row g-4">

    <div class="col-lg-8">

        {{-- Loan information --}}
        <div class="card-soft mb-4">
            <div class="card-body">
                <h2 class="section-title mb-4">
                    Loan Information
                </h2>

                <div class="row g-4">

                    <div class="col-md-6">
                        <span class="metric-label d-block mb-2">
                            Borrower
                        </span>
                        <strong>
                            {{ $loan->membership->user->name }}
                        </strong>
                    </div>

                    <div class="col-md-6">
                        <span class="metric-label d-block mb-2">
                            Captured By
                        </span>
                        <strong>
                            {{ $loan->submittedBy?->name ?? 'Historical Record' }}
                        </strong>
                    </div>

                    <div class="col-md-6">
                        <span class="metric-label d-block mb-2">
                            Captured On
                        </span>
                        <strong>
                            {{ $loan->submitted_at->format('d M Y H:i') }}
                        </strong>
                    </div>

                    <div class="col-md-6">
                        <span class="metric-label d-block mb-2">
                            Savings Cycle
                        </span>
                        <strong>{{ $loan->cycle->name }}</strong>
                    </div>

                    <div class="col-md-6">
                        <span class="metric-label d-block mb-2">
                            Interest Rate
                        </span>
                        <strong>
                            {{ $loan->interest_rate_percent }}%
                        </strong>
                    </div>

                    <div class="col-md-6">
                        <span class="metric-label d-block mb-2">
                            Interest Basis
                        </span>
                        <strong>
                            {{ $loan->interest_basis === 'per_month'
                                ? 'Flat, per month'
                                : 'Flat, per term' }}
                        </strong>
                    </div>

                    <div class="col-md-6">
                        <span class="metric-label d-block mb-2">
                            Loan Term
                        </span>
                        <strong>
                            {{ $loan->term_months }} months
                        </strong>
                    </div>

                    <div class="col-md-6">
                        <span class="metric-label d-block mb-2">
                            Maturity Date
                        </span>
                        <strong>
                            {{ $loan->matures_on
                                ? $loan->matures_on->format('d M Y')
                                : 'Awaiting disbursement' }}
                        </strong>
                    </div>

                    <div class="col-12">
                        <span class="metric-label d-block mb-2">
                            Purpose
                        </span>
                        <p class="mb-0">{{ $loan->purpose }}</p>
                    </div>

                </div>
            </div>
        </div>

        {{-- Single maturity obligation --}}
        @if($loan->installments->isNotEmpty())
            <div class="card-soft mb-4">
                <div class="card-body">

                    <h2 class="section-title mb-3">
                        Repayment Obligation
                    </h2>

                    <p class="small muted mb-4">
                        This is the total contractual amount due
                        at maturity, not a compulsory monthly instalment.
                    </p>

                    <div class="table-responsive">
                        <table class="table align-middle mk-table">
                            <thead>
                                <tr>
                                    <th>Due Date</th>
                                    <th>Principal</th>
                                    <th>Interest</th>
                                    <th>Total Due</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($loan->installments as $item)
                                    <tr>
                                        <td>
                                            {{ $item->due_date->format('d M Y') }}
                                        </td>

                                        <td>
                                            M {{ $item->principal_due }}
                                        </td>

                                        <td>
                                            M {{ $item->interest_due }}
                                        </td>

                                        <td>
                                            <strong>
                                                M {{ $item->total_due }}
                                            </strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Actual repayments --}}
        @if($loan->disbursement)
            <div class="card-soft mb-4">
                <div class="card-body">

                    <h2 class="section-title mb-4">
                        Repayment History
                    </h2>

                    <div class="table-responsive">
                        <table class="table align-middle mk-table">

                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Total Paid</th>
                                    <th>Principal</th>
                                    <th>Interest</th>
                                    <th>Method</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($loan->repayments as $repayment)
                                    @php
                                        $principalPaid =
                                            $repayment->allocations->sum(
                                                fn ($row) =>
                                                    \App\Support\Money::toCents(
                                                        $row->principal_amount
                                                    )
                                            );

                                        $interestPaid =
                                            $repayment->allocations->sum(
                                                fn ($row) =>
                                                    \App\Support\Money::toCents(
                                                        $row->interest_amount
                                                    )
                                            );
                                    @endphp

                                    <tr>
                                        <td>
                                            {{ $repayment->paid_on->format('d M Y') }}
                                        </td>

                                        <td class="fw-bold">
                                            M {{ $repayment->amount }}
                                        </td>

                                        <td>
                                            {{ $money($principalPaid) }}
                                        </td>

                                        <td>
                                            {{ $money($interestPaid) }}
                                        </td>

                                        <td>
                                            {{ ucwords(str_replace(
                                                '_',
                                                ' ',
                                                $repayment->payment_method
                                            )) }}
                                        </td>

                                        <td>
                                            {{ $repayment->recorder?->name ?? 'System' }}
                                        </td>
                                    </tr>

                                @empty
                                    <tr>
                                        <td colspan="6"
                                            class="py-4 text-center muted">
                                            No repayments have been recorded.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                        </table>
                    </div>

                </div>
            </div>
        @endif

    </div>

    {{-- Activity history --}}
    <div class="col-lg-4">
        <div class="card-soft">
            <div class="card-body">

                <h2 class="section-title mb-4">
                    Loan Activity
                </h2>

                @forelse($loan->events->sortByDesc('id') as $event)
                    <div class="mk-loan-event">
                        <div class="d-flex gap-3">

                            <span class="mk-loan-event-icon">
                                <i class="bi bi-circle-fill"></i>
                            </span>

                            <div>
                                <strong class="d-block mb-1">
                                    {{ ucwords(str_replace(
                                        '_',
                                        ' ',
                                        $event->event_type
                                    )) }}
                                </strong>

                                <div class="small muted">
                                    {{ $event->actor?->name ?? 'System' }}
                                </div>

                                <div class="small muted mt-1">
                                    {{ $event->created_at->format('d M Y H:i') }}
                                </div>

                                @if($event->notes)
                                    <p class="small mt-2 mb-0">
                                        {{ $event->notes }}
                                    </p>
                                @endif
                            </div>

                        </div>
                    </div>

                @empty
                    <p class="muted mb-0">
                        No activity recorded.
                    </p>
                @endforelse

            </div>
        </div>
    </div>

</div>

@endsection
