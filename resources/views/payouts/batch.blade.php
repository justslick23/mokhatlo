
@extends('layouts.app')

@section('title', 'Payout Settlement')

@section('content')

@php
    $money = fn (int $cents) =>
        'M ' . number_format($cents / 100, 2);

    $progress = $batch->gross_total_cents > 0
        ? min(
            100,
            ($paidTotal / $batch->gross_total_cents) * 100
        )
        : 100;
@endphp

<div class="mb-4">
    <a
        href="{{ route(
            'payouts.drafts.show',
            $batch->cycle_payout_draft_id
        ) }}"
        class="small fw-semibold"
    >
        <i class="bi bi-arrow-left me-2"></i>
        Back to Approved Payout
    </a>
</div>

<div class="d-flex justify-content-between
            align-items-end flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">
            Annual Savings Settlement
        </p>

        <h1 class="page-title">
            {{ $batch->cycle->name }} Payouts
        </h1>

        <p class="muted mt-2 mb-0">
            Started by {{ $batch->starter?->name }}
            on {{ $batch->started_at->format('d M Y H:i') }}
        </p>
    </div>

    @if($batch->status === 'completed')
        <span class="badge bg-success-subtle
                     text-success px-3 py-2">
            <i class="bi bi-check-circle me-1"></i>
            Cycle Closed
        </span>
    @else
        <span class="badge bg-info-subtle
                     text-info-emphasis px-3 py-2">
            Payouts In Progress
        </span>
    @endif
</div>

@if(session('status'))
    <div class="alert alert-success">
        {{ session('status') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Unable to complete the action:</strong>

        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Batch summary --}}
<div class="row g-3 mb-4">
    @foreach([
        ['Approved Total', $batch->gross_total_cents],
        ['Payments Recorded', $paidTotal],
        ['Remaining to Pay', $remainingTotal],
    ] as [$label, $value])
        <div class="col-md-4">
            <div class="mk-analytics-card h-100">
                <div class="mk-analytics-label">
                    {{ $label }}
                </div>

                <div class="mk-analytics-value">
                    {{ $money($value) }}
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="mk-chart-card mb-4">
    <div class="d-flex justify-content-between gap-3 mb-3">
        <h2 class="mk-chart-heading mb-0">
            Payment Progress
        </h2>

        <strong>
            {{ number_format($progress, 1) }}%
        </strong>
    </div>

    <div class="progress" style="height: 12px;">
        <div
            class="progress-bar"
            role="progressbar"
            style="width: {{ $progress }}%"
            aria-valuenow="{{ $progress }}"
            aria-valuemin="0"
            aria-valuemax="100"
        ></div>
    </div>

    <div class="small muted mt-3">
        Payments must be verified as transferred before recording
        them here. This screen does not initiate payments.
    </div>
</div>

{{-- Member payout register --}}
<div class="mk-chart-card mb-4">
    <h2 class="mk-chart-heading mb-2">
        Member Payout Register
    </h2>

    <p class="small muted mb-4">
        Record full or partial payouts against approved entitlements.
    </p>

    <div class="table-responsive">
        <table class="table table-hover align-middle mk-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Approved</th>
                    <th>Paid</th>
                    <th>Outstanding</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @foreach($batch->draft->items as $item)
                    @php
                        $payments = $item->payments
                            ->where(
                                'cycle_payout_batch_id',
                                $batch->id
                            );

                        $memberPaid = $payments->sum('amount_cents');

                        $memberRemaining = max(
                            0,
                            $item->gross_payout_cents - $memberPaid
                        );

                        $isSelf = (int) $item->membership?->user_id
                            === (int) auth()->id();
                    @endphp

                    <tr>
                        <td>
                            <strong>
                                {{ $item->member_name }}
                            </strong>

                            <div class="small muted">
                                {{ $item->member_number ?? 'Member' }}
                            </div>
                        </td>

                        <td>
                            {{ $money($item->gross_payout_cents) }}
                        </td>

                        <td>
                            {{ $money($memberPaid) }}
                        </td>

                        <td class="fw-bold">
                            {{ $money($memberRemaining) }}
                        </td>

                        <td>
                            @if($memberRemaining === 0)
                                <span class="badge bg-success-subtle text-success">
                                    Paid
                                </span>
                            @elseif($memberPaid > 0)
                                <span class="badge bg-warning-subtle text-warning-emphasis">
                                    Partially Paid
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                    Unpaid
                                </span>
                            @endif
                        </td>

                        <td class="text-end">
                            @if(
                                $canExecute
                                && $memberRemaining > 0
                                && ! $isSelf
                            )
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#payment-form-{{ $item->id }}"
                                    aria-expanded="false"
                                >
                                    Record Payment
                                </button>
                            @endif
                        </td>
                    </tr>

                    @if(
                        $canExecute
                        && $memberRemaining > 0
                        && ! $isSelf
                    )
                        <tr class="collapse"
                            id="payment-form-{{ $item->id }}">
                            <td colspan="6">
                                <div class="p-3 border rounded-3">
                                    <h3 class="h6 fw-bold mb-3">
                                        Record payment to
                                        {{ $item->member_name }}
                                    </h3>

                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'payouts.batches.items.pay',
                                            [$batch->id, $item->id]
                                        ) }}"
                                    >
                                        @csrf

                                        <input
                                            type="hidden"
                                            name="idempotency_key"
                                            value="{{ $paymentKeys[$item->id] }}"
                                        >

                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">
                                                    Amount (M)
                                                </label>

                                                <input
                                                    type="number"
                                                    name="amount"
                                                    step="0.01"
                                                    min="0.01"
                                                    max="{{ \App\Support\Money::fromCents($memberRemaining) }}"
                                                    value="{{ \App\Support\Money::fromCents($memberRemaining) }}"
                                                    class="form-control"
                                                    required
                                                >
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">
                                                    Payment Date
                                                </label>

                                                <input
                                                    type="date"
                                                    name="paid_on"
                                                    min="{{ $batch->started_at->toDateString() }}"
                                                    max="{{ now('Africa/Maseru')->toDateString() }}"
                                                    value="{{ now('Africa/Maseru')->toDateString() }}"
                                                    class="form-control"
                                                    required
                                                >
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">
                                                    Payment Method
                                                </label>

                                                <select
                                                    name="payment_method"
                                                    class="form-select"
                                                    required
                                                >
                                                    <option value="">
                                                        Choose method
                                                    </option>
                                                    <option value="cash">
                                                        Cash
                                                    </option>
                                                    <option value="bank_transfer">
                                                        Bank Transfer
                                                    </option>
                                                    <option value="mobile_money">
                                                        Mobile Money
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">
                                                    Payment Reference
                                                </label>

                                                <input
                                                    name="payment_reference"
                                                    maxlength="120"
                                                    class="form-control"
                                                    placeholder="Bank / mobile money reference"
                                                >
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">
                                                    Notes
                                                </label>

                                                <input
                                                    name="notes"
                                                    maxlength="2000"
                                                    class="form-control"
                                                >
                                            </div>

                                            <div class="col-12">
                                                <div class="form-check mb-3">
                                                    <input
                                                        type="checkbox"
                                                        name="confirmed"
                                                        id="confirmed-{{ $item->id }}"
                                                        class="form-check-input"
                                                        value="1"
                                                        required
                                                    >

                                                    <label
                                                        class="form-check-label"
                                                        for="confirmed-{{ $item->id }}"
                                                    >
                                                        I confirm that this
                                                        payment has actually
                                                        been made.
                                                    </label>
                                                </div>

                                                <button
                                                    type="submit"
                                                    class="btn btn-primary"
                                                    data-submit-once
                                                >
                                                    Record Verified Payment
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Payout audit trail --}}
<div class="mk-chart-card mb-4">
    <h2 class="mk-chart-heading mb-4">
        Recorded Payout Transactions
    </h2>

    <div class="table-responsive">
        <table class="table align-middle mk-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Member</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Recorded By</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>

            <tbody>
                @forelse(
                    $batch->payments->sortByDesc('id')
                    as $payment
                )
                    <tr>
                        <td>
                            {{ $payment->paid_on->format('d M Y') }}
                        </td>

                        <td>
                            {{ $payment->item?->member_name }}
                        </td>

                        <td>
                            {{ ucwords(str_replace(
                                '_',
                                ' ',
                                $payment->payment_method
                            )) }}
                        </td>

                        <td>
                            {{ $payment->payment_reference ?? '—' }}
                        </td>

                        <td>
                            {{ $payment->recorder?->name }}
                        </td>

                        <td class="text-end fw-bold">
                            {{ $money($payment->amount_cents) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center muted py-4">
                            No member payments have been recorded.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Cycle closure --}}
<div class="mk-chart-card mb-4">
    <h2 class="mk-chart-heading mb-3">
        Savings Cycle Closure
    </h2>

    @if($batch->status === 'completed')
        <div class="alert alert-success mb-0">
            <i class="bi bi-check-circle me-2"></i>

            Cycle successfully closed by
            <strong>{{ $batch->completer?->name }}</strong>
            on
            {{ $batch->completed_at->format('d M Y H:i') }}.
        </div>
    @elseif($canClose)
        <form
            method="POST"
            action="{{ route(
                'payouts.batches.complete',
                $batch->id
            ) }}"
        >
            @csrf

            <p class="muted">
                All approved member payouts have been recorded.
                The system will recheck the ledger before closure.
            </p>

            <div class="form-check mb-3">
                <input
                    type="checkbox"
                    name="confirmed"
                    id="close-confirmed"
                    class="form-check-input"
                    value="1"
                    required
                >

                <label
                    for="close-confirmed"
                    class="form-check-label"
                >
                    I confirm the payout register and financial
                    records have been reviewed.
                </label>
            </div>

            <button
                type="submit"
                class="btn btn-primary"
                data-submit-once
                onclick="return confirm('Close this savings cycle? This will prevent further normal transactions for the cycle.');"
            >
                <i class="bi bi-lock me-2"></i>
                Complete & Close Cycle
            </button>
        </form>
    @else
        <p class="muted mb-0">
            The cycle can be closed only after every member's
            approved payout has been recorded and an independent
            authorised official performs the closure.
        </p>
    @endif
</div>

@endsection
