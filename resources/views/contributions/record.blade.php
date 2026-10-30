
@extends('layouts.app')

@section('title', 'Record Contribution')

@section('content')

<div class="mb-4">
    <a href="{{ route('contributions.index', [
        'cycle_id' => $obligation->cycle_id
    ]) }}" class="small fw-semibold">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Contributions
    </a>
</div>

<div class="row g-4">

    <!-- Contribution Form -->
    <div class="col-lg-8">

        <div class="mb-4">
            <p class="eyebrow mb-2">
                Financial Transactions
            </p>

            <h1 class="page-title">
                Record Contribution
            </h1>

            <p class="muted mt-2">
                Record a payment received from a member
                and update the society's financial records.
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

        <div class="card-soft">
            <div class="card-body p-lg-4">

                <form method="POST"
                      action="{{ route('contributions.store', $obligation->id) }}">
                    @csrf

                    <input
                        type="hidden"
                        name="idempotency_key"
                        value="{{ old('idempotency_key', $paymentKey) }}"
                    >

                    <div class="mb-4">
                        <label class="form-label">
                            Member
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="{{ $obligation->membership->user->name }}"
                            disabled
                        >
                    </div>

                    <div class="mb-4">
                        <label class="form-label">
                            Contribution Period
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="{{ $obligation->period_start->format('F Y') }}"
                            disabled
                        >
                    </div>

                    <div class="row g-3 mb-4">

                        <div class="col-md-6">
                            <label for="amount" class="form-label">
                                Amount Received (M)
                            </label>

                            <input
                                type="number"
                                id="amount"
                                name="amount"
                                step="0.01"
                                min="0.01"
                                value="{{ old('amount') }}"
                                class="form-control @error('amount') is-invalid @enderror"
                                placeholder="0.00"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="paid_on" class="form-label">
                                Payment Date
                            </label>

                            <input
                                type="date"
                                id="paid_on"
                                name="paid_on"
                                value="{{ old('paid_on', now('Africa/Maseru')->toDateString()) }}"
                                max="{{ now('Africa/Maseru')->toDateString() }}"
                                class="form-control @error('paid_on') is-invalid @enderror"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="payment_method" class="form-label">
                                Payment Method
                            </label>

                            <select
                                id="payment_method"
                                name="payment_method"
                                class="form-select"
                                required
                            >
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

                        <div class="col-md-6">
                            <label for="payment_reference" class="form-label">
                                Payment Reference
                            </label>

                            <input
                                type="text"
                                id="payment_reference"
                                name="payment_reference"
                                class="form-control"
                                value="{{ old('payment_reference') }}"
                                placeholder="Optional transaction reference"
                                maxlength="120"
                            >
                        </div>

                    </div>

                    <div class="mb-4">
                        <label for="notes" class="form-label">
                            Notes
                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            class="form-control"
                            rows="3"
                            maxlength="1000"
                            placeholder="Optional payment notes"
                        >{{ old('notes') }}</textarea>
                    </div>

                    <div class="mk-info-panel mb-4">

                        <div class="d-flex gap-3">
                            <i class="bi bi-shield-check fs-4"></i>

                            <div>
                                <strong class="d-block mb-1">
                                    Financial Record
                                </strong>

                                <p class="small mb-0">
                                    This payment will create a permanent
                                    contribution record and matching
                                    debit/credit entries in the society ledger.
                                    Confirm the amount before submitting.
                                </p>
                            </div>
                        </div>

                    </div>

                    <div class="d-flex justify-content-end gap-2">

                        <a href="{{ route('contributions.index') }}"
                           class="btn btn-outline-secondary">
                            Cancel
                        </a>

                        <button type="submit"
                                class="btn btn-primary"
                                data-submit-once>
                            <i class="bi bi-check-circle me-2"></i>
                            Save Contribution
                        </button>

                    </div>

                </form>

            </div>
        </div>
    </div>

    <!-- Member Financial Summary -->
    <div class="col-lg-4">

        @php
            $received = \App\Support\Money::toCents(
                \App\Models\ContributionPayment::query()
                    ->where(
                        'contribution_obligation_id',
                        $obligation->id
                    )
                    ->sum('amount') ?: '0.00'
            );

            $required = \App\Support\Money::toCents(
                $obligation->required_amount
            );

            $outstanding = max(0, $required - $received);
        @endphp

        <div class="card-soft">
            <div class="card-body">

                <h2 class="section-title mb-4">
                    Contribution Summary
                </h2>

                <div class="mb-4">
                    <p class="metric-label mb-2">
                        Monthly Requirement
                    </p>

                    <div class="metric-value">
                        M {{ number_format($required / 100, 2) }}
                    </div>
                </div>

                <div class="mb-4">
                    <p class="metric-label mb-2">
                        Already Received
                    </p>

                    <h3 class="h4 fw-bold text-success">
                        M {{ number_format($received / 100, 2) }}
                    </h3>
                </div>

                <div class="mb-4">
                    <p class="metric-label mb-2">
                        Outstanding Minimum
                    </p>

                    <h3 class="h4 fw-bold">
                        M {{ number_format($outstanding / 100, 2) }}
                    </h3>
                </div>

                <hr>

                <p class="small muted mb-0">
                    Contribution deadline:
                    <strong>
                        {{ $obligation->due_date->format('d M Y') }}
                    </strong>
                </p>

            </div>
        </div>

    </div>

</div>

@endsection
