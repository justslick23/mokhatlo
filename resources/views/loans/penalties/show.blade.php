
@extends('layouts.app')

@section('title', 'Loan Penalty Details')

@section('content')
<div class="mb-4">
    <a href="{{ route('loans.penalties.index') }}" class="small fw-semibold">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Loan Penalties
    </a>
</div>

<div class="mb-4">
    <p class="eyebrow mb-2">Overdue Management</p>
    <h1 class="page-title">
        Loan Penalty #{{ $penalty->id }}
    </h1>
    <p class="muted mt-2">
        {{ $penalty->loan->loan_number }}
        &middot; {{ $penalty->loan->membership->user->name }}
    </p>
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
    @foreach([
        ['Assessed', $penalty->assessedCents()],
        ['Collected', $penalty->paidCents()],
        ['Waived', $penalty->waivedCents()],
        ['Outstanding', $penalty->outstandingCents()],
    ] as [$label, $cents])
        <div class="col-sm-6 col-xl-3">
            <div class="card-soft h-100">
                <div class="card-body">
                    <div class="metric-label">{{ $label }}</div>
                    <div class="metric-value fs-4">
                        M {{ \App\Support\Money::fromCents($cents) }}
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card-soft mb-4">
    <div class="card-body">
        <h2 class="section-title mb-3">Assessment</h2>
        <p class="mb-1">
            Unpaid instalment balance at cutoff:
            <strong>M {{ $penalty->base_amount }}</strong>
        </p>
        <p class="mb-1">
            Penalty rate:
            <strong>{{ $penalty->rate_percent }}%</strong>
        </p>
        <p class="mb-0">
            Due date:
            <strong>{{ $penalty->cutoff_date->format('d M Y') }}</strong>
        </p>
    </div>
</div>

@if($penalty->outstandingCents() > 0)
    <div class="row g-3 mb-4">
        @can('loans.penalties.collect')
            <div class="col-lg-6">
                <div class="card-soft h-100">
                    <div class="card-body">
                        <h2 class="section-title mb-3">
                            Record Penalty Payment
                        </h2>

                        <form method="POST"
                              action="{{ route('loans.penalties.pay', $penalty->id) }}">
                            @csrf

                            <input type="hidden"
                                   name="idempotency_key"
                                   value="{{ old('idempotency_key', $paymentKey) }}">

                            <div class="mb-3">
                                <label class="form-label">
                                    Amount (M)
                                </label>
                                <input type="number"
                                       name="amount"
                                       class="form-control"
                                       step="0.01"
                                       min="0.01"
                                       max="{{ \App\Support\Money::fromCents(
                                           $penalty->outstandingCents()
                                       ) }}"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Payment Date
                                </label>
                                <input type="date"
                                       name="paid_on"
                                       class="form-control"
                                       min="{{ $penalty->assessed_on->toDateString() }}"
                                       max="{{ now('Africa/Maseru')->toDateString() }}"
                                       value="{{ now('Africa/Maseru')->toDateString() }}"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Payment Method
                                </label>
                                <select name="payment_method"
                                        class="form-select" required>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_money">Mobile Money</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Reference
                                </label>
                                <input name="payment_reference"
                                       class="form-control"
                                       maxlength="120">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Notes (Optional)
                                </label>
                                <textarea name="notes"
                                          rows="2"
                                          class="form-control"></textarea>
                            </div>

                            <button type="submit"
                                    class="btn btn-primary"
                                    data-submit-once>
                                Save Payment
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan

        @can('loans.penalties.waive')
            <div class="col-lg-6">
                <div class="card-soft h-100">
                    <div class="card-body">
                        <h2 class="section-title mb-3">
                            Waive Outstanding Penalty
                        </h2>

                        <form method="POST"
                              action="{{ route('loans.penalties.waive', $penalty->id) }}">
                            @csrf

                            <input type="hidden"
                                   name="idempotency_key"
                                   value="{{ old('idempotency_key', $waiverKey) }}">

                            <div class="mb-3">
                                <label class="form-label">
                                    Waiver Amount (M)
                                </label>
                                <input type="number"
                                       name="amount"
                                       class="form-control"
                                       step="0.01"
                                       min="0.01"
                                       max="{{ \App\Support\Money::fromCents(
                                           $penalty->outstandingCents()
                                       ) }}"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Reason
                                </label>
                                <textarea name="reason"
                                          class="form-control"
                                          rows="5"
                                          minlength="10"
                                          maxlength="2000"
                                          required></textarea>
                            </div>

                            <button type="submit"
                                    class="btn btn-outline-primary">
                                Confirm Waiver
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-soft h-100">
            <div class="card-body">
                <h2 class="section-title mb-3">Payment History</h2>
                @forelse($penalty->payments as $payment)
                    <div class="border-bottom py-3">
                        <strong>M {{ $payment->amount }}</strong>
                        <div class="small muted">
                            {{ $payment->paid_on->format('d M Y') }}
                            &middot; {{ $payment->recorder->name }}
                        </div>
                        @if($payment->payment_reference)
                            <div class="small muted">
                                Ref: {{ $payment->payment_reference }}
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="muted mb-0">No payments recorded.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-soft h-100">
            <div class="card-body">
                <h2 class="section-title mb-3">Waiver History</h2>
                @forelse($penalty->waivers as $waiver)
                    <div class="border-bottom py-3">
                        <strong>M {{ $waiver->amount }}</strong>
                        <div class="small muted">
                            {{ $waiver->created_at->format('d M Y') }}
                            &middot; {{ $waiver->approver->name }}
                        </div>
                        <p class="small mt-2 mb-0">
                            {{ $waiver->reason }}
                        </p>
                    </div>
                @empty
                    <p class="muted mb-0">No waivers recorded.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
