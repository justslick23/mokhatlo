
@extends('layouts.app')

@section('title', 'Penalty Details')

@section('content')

<div class="mb-4">
    <a href="{{ route('penalties.index') }}"
       class="small fw-semibold">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Penalties
    </a>
</div>

<div class="d-flex justify-content-between
            align-items-end flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">Penalty Management</p>
        <h1 class="page-title">
            Penalty #{{ $penalty->id }}
        </h1>

        <p class="muted mt-2 mb-0">
            {{ $penalty->obligation->membership->user->name }}
            &middot;
            {{ $penalty->obligation->period_start->format('F Y') }}
        </p>
    </div>

    <span class="badge {{ $penalty->outstandingCents() > 0
        ? 'bg-danger-subtle text-danger-emphasis'
        : 'bg-success-subtle text-success' }} px-3 py-2">

        {{ $penalty->outstandingCents() > 0
            ? 'Outstanding'
            : 'Settled' }}
    </span>

</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3 mb-4">

    <div class="col-sm-6 col-lg-3">
        <div class="card-soft h-100">
            <div class="card-body">
                <div class="metric-label">Penalty Assessed</div>
                <div class="metric-value fs-4">
                    M {{ number_format($penalty->assessedCents() / 100, 2) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card-soft h-100">
            <div class="card-body">
                <div class="metric-label">Collected</div>
                <div class="metric-value fs-4">
                    M {{ number_format($penalty->paidCents() / 100, 2) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card-soft h-100">
            <div class="card-body">
                <div class="metric-label">Waived</div>
                <div class="metric-value fs-4">
                    M {{ number_format($penalty->waivedCents() / 100, 2) }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3">
        <div class="card-soft h-100">
            <div class="card-body">
                <div class="metric-label">Outstanding</div>
                <div class="metric-value fs-4">
                    M {{ number_format($penalty->outstandingCents() / 100, 2) }}
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Assessment Information -->
<div class="card-soft mb-4">
    <div class="card-body">

        <h2 class="section-title mb-4">
            Assessment Details
        </h2>

        <div class="row g-3">

            <div class="col-md-3">
                <div class="small muted mb-1">Penalty Base</div>
                <strong>
                    M {{ number_format((float) $penalty->base_amount, 2) }}
                </strong>
            </div>

            <div class="col-md-3">
                <div class="small muted mb-1">Penalty Rate</div>
                <strong>
                    {{ rtrim(rtrim($penalty->rate_percent, '0'), '.') }}%
                </strong>
            </div>

            <div class="col-md-3">
                <div class="small muted mb-1">Cutoff Date</div>
                <strong>
                    {{ $penalty->cutoff_date->format('d M Y') }}
                </strong>
            </div>

            <div class="col-md-3">
                <div class="small muted mb-1">Assessed On</div>
                <strong>
                    {{ $penalty->assessed_on->format('d M Y') }}
                </strong>
            </div>

        </div>

    </div>
</div>

<!-- Payment / Waiver Forms -->
@if(
    $penalty->outstandingCents() > 0
    && $penalty->cycle->status === 'active'
)
    <div class="row g-3 mb-4">

        @can('penalties.record_payment')
            <div class="col-lg-6">
                <div class="card-soft h-100">
                    <div class="card-body">

                        <h2 class="section-title mb-2">
                            <i class="bi bi-cash-coin me-2"></i>
                            Record Penalty Payment
                        </h2>

                        <p class="small muted mb-4">
                            Record money received toward this penalty.
                        </p>

                        <form method="POST"
                              action="{{ route('penalties.payments.store', $penalty->id) }}">
                            @csrf

                            <input type="hidden"
                                   name="idempotency_key"
                                   value="{{ old('idempotency_key', $paymentKey) }}">

                            <div class="mb-3">
                                <label class="form-label" for="payment_amount">
                                    Amount (M)
                                </label>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max="{{ \App\Support\Money::fromCents($penalty->outstandingCents()) }}"
                                    name="amount"
                                    id="payment_amount"
                                    class="form-control"
                                    value="{{ old('amount', \App\Support\Money::fromCents($penalty->outstandingCents())) }}"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="paid_on">
                                    Payment Date
                                </label>

                                <input
                                    type="date"
                                    id="paid_on"
                                    name="paid_on"
                                    class="form-control"
                                    value="{{ old('paid_on', now('Africa/Maseru')->toDateString()) }}"
                                    min="{{ $penalty->assessed_on->toDateString() }}"
                                    max="{{ now('Africa/Maseru')->toDateString() }}"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="payment_method">
                                    Payment Method
                                </label>

                                <select
                                    name="payment_method"
                                    id="payment_method"
                                    class="form-select"
                                    required
                                >
                                    @foreach([
                                        'cash' => 'Cash',
                                        'bank_transfer' => 'Bank Transfer',
                                        'mobile_money' => 'Mobile Money',
                                    ] as $value => $label)
                                        <option value="{{ $value }}"
                                            @selected(old('payment_method') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="payment_reference">
                                    Payment Reference
                                </label>

                                <input
                                    type="text"
                                    name="payment_reference"
                                    id="payment_reference"
                                    class="form-control"
                                    maxlength="120"
                                    value="{{ old('payment_reference') }}"
                                >
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="payment_notes">
                                    Notes
                                </label>

                                <textarea
                                    name="notes"
                                    id="payment_notes"
                                    rows="2"
                                    maxlength="1000"
                                    class="form-control"
                                >{{ old('notes') }}</textarea>
                            </div>

                            <button type="submit"
                                    class="btn btn-primary w-100">
                                Record Payment
                            </button>
                        </form>

                    </div>
                </div>
            </div>
        @endcan

        @can('penalties.waive')
            <div class="col-lg-6">
                <div class="card-soft h-100">
                    <div class="card-body">

                        <h2 class="section-title mb-2">
                            <i class="bi bi-shield-check me-2"></i>
                            Waive Penalty
                        </h2>

                        <p class="small muted mb-4">
                            Reduce an outstanding penalty with
                            a documented reason.
                        </p>

                        <form method="POST"
                              action="{{ route('penalties.waivers.store', $penalty->id) }}">
                            @csrf

                            <input type="hidden"
                                   name="idempotency_key"
                                   value="{{ $waiverKey }}">

                            <div class="mb-3">
                                <label for="waiver_amount" class="form-label">
                                    Waiver Amount (M)
                                </label>

                                <input
                                    type="number"
                                    id="waiver_amount"
                                    name="amount"
                                    step="0.01"
                                    min="0.01"
                                    max="{{ \App\Support\Money::fromCents($penalty->outstandingCents()) }}"
                                    class="form-control"
                                    placeholder="0.00"
                                    required
                                >
                            </div>

                            <div class="mb-4">
                                <label for="waiver_reason" class="form-label">
                                    Reason for Waiver
                                </label>

                                <textarea
                                    id="waiver_reason"
                                    name="reason"
                                    rows="5"
                                    minlength="10"
                                    maxlength="2000"
                                    class="form-control"
                                    placeholder="Explain why the penalty is being waived..."
                                    required
                                ></textarea>
                            </div>

                            <button type="submit"
                                    class="btn btn-outline-primary w-100"
                                    onclick="return confirm('Confirm this penalty waiver?');">
                                Confirm Waiver
                            </button>

                        </form>

                    </div>
                </div>
            </div>
        @endcan

    </div>
@endif

<!-- Payment and Waiver History -->
<div class="row g-3">

    <div class="col-lg-6">
        <div class="card-soft h-100">
            <div class="card-body">

                <h2 class="section-title mb-4">
                    Payment History
                </h2>

                @forelse($penalty->payments as $payment)
                    <div class="border-bottom py-3">

                        <div class="d-flex justify-content-between gap-3">
                            <strong>
                                M {{ number_format((float) $payment->amount, 2) }}
                            </strong>

                            <span class="small muted">
                                {{ $payment->paid_on->format('d M Y') }}
                            </span>
                        </div>

                        <div class="small muted mt-2">
                            {{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}
                            &middot;
                            {{ $payment->recorder->name }}
                        </div>

                        @if($payment->payment_reference)
                            <div class="small muted mt-1">
                                Ref: {{ $payment->payment_reference }}
                            </div>
                        @endif

                    </div>
                @empty
                    <p class="muted mb-0">
                        No penalty payments recorded.
                    </p>
                @endforelse

            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-soft h-100">
            <div class="card-body">

                <h2 class="section-title mb-4">
                    Waiver History
                </h2>

                @forelse($penalty->waivers as $waiver)
                    <div class="border-bottom py-3">

                        <div class="d-flex justify-content-between gap-3">
                            <strong>
                                M {{ number_format((float) $waiver->amount, 2) }}
                            </strong>

                            <span class="small muted">
                                {{ $waiver->created_at->format('d M Y') }}
                            </span>
                        </div>

                        <div class="small muted mt-2">
                            Approved by {{ $waiver->approver->name }}
                        </div>

                        <p class="small mt-2 mb-0">
                            {{ $waiver->reason }}
                        </p>

                    </div>
                @empty
                    <p class="muted mb-0">
                        No waivers recorded.
                    </p>
                @endforelse

            </div>
        </div>
    </div>

</div>

@endsection
