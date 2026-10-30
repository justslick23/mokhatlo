
@extends('layouts.app')

@section('title', 'Record Loan Repayment')

@section('content')

<div class="mb-4">
    <a href="{{ route('loans.show', $loan->id) }}"
       class="small fw-semibold">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Loan Details
    </a>
</div>

<div class="mb-4">
    <p class="eyebrow mb-2">Loan Management</p>

    <h1 class="page-title">
        Record Loan Repayment
    </h1>

    <p class="muted mt-2 mb-0">
        {{ $loan->loan_number }}
        &middot;
        {{ $loan->membership->user->name }}
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

<div class="row g-4">

    <div class="col-lg-8">
        <div class="card-soft">
            <div class="card-body p-lg-4">

                <h2 class="section-title mb-4">
                    Payment Information
                </h2>

                <form method="POST"
                      action="{{ route('loans.repayments.store', $loan->id) }}">
                    @csrf

                    <input
                        type="hidden"
                        name="idempotency_key"
                        value="{{ old('idempotency_key', $repaymentKey) }}"
                    >

                    <div class="row g-3 mb-4">

                        <div class="col-md-6">
                            <label for="amount" class="form-label">
                                Amount Received (M)
                            </label>

                            <input
                                type="number"
                                name="amount"
                                id="amount"
                                step="0.01"
                                min="0.01"
                                max="{{ \App\Support\Money::fromCents($balance['total_outstanding']) }}"
                                value="{{ old('amount') }}"
                                class="form-control"
                                placeholder="0.00"
                                required
                            >

                            <div class="form-text">
                                You can record a partial or full repayment.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="paid_on" class="form-label">
                                Payment Date
                            </label>

                            <input
                                type="date"
                                name="paid_on"
                                id="paid_on"
                                value="{{ old('paid_on', now('Africa/Maseru')->toDateString()) }}"
                                min="{{ $loan->disbursement->disbursed_on->toDateString() }}"
                                max="{{ now('Africa/Maseru')->toDateString() }}"
                                class="form-control"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="payment_method" class="form-label">
                                Payment Method
                            </label>

                            <select
                                name="payment_method"
                                id="payment_method"
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
                                name="payment_reference"
                                id="payment_reference"
                                maxlength="120"
                                value="{{ old('payment_reference') }}"
                                class="form-control"
                                placeholder="Optional transaction reference"
                            >
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">
                                Notes
                            </label>

                            <textarea
                                name="notes"
                                id="notes"
                                rows="3"
                                maxlength="1000"
                                class="form-control"
                            >{{ old('notes') }}</textarea>
                        </div>

                    </div>

                    <div class="mk-info-panel mb-4">
                        <div class="d-flex gap-3">
                            <i class="bi bi-shield-check fs-4"></i>

                            <div>
                                <strong class="d-block mb-1">
                                    Financial Recording
                                </strong>

                                <p class="small mb-0">
                                    The repayment will automatically
                                    be allocated to outstanding instalments,
                                    split between interest and principal,
                                    and posted to the society ledger.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('loans.show', $loan->id) }}"
                           class="btn btn-outline-secondary">
                            Cancel
                        </a>

                        <button type="submit"
                                class="btn btn-primary"
                                data-submit-once>
                            <i class="bi bi-check-circle me-2"></i>
                            Save Repayment
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft">
            <div class="card-body">

                <h2 class="section-title mb-4">
                    Loan Balance
                </h2>

                <div class="mb-4">
                    <div class="metric-label">
                        Outstanding Principal
                    </div>

                    <div class="h4 fw-bold mt-2">
                        M {{ number_format(
                            $balance['principal_outstanding'] / 100,
                            2
                        ) }}
                    </div>
                </div>

                <div class="mb-4">
                    <div class="metric-label">
                        Outstanding Interest
                    </div>

                    <div class="h4 fw-bold mt-2">
                        M {{ number_format(
                            $balance['interest_outstanding'] / 100,
                            2
                        ) }}
                    </div>
                </div>

                <hr>

                <div class="metric-label mb-2">
                    Total Outstanding
                </div>

                <div class="metric-value">
                    M {{ number_format(
                        $balance['total_outstanding'] / 100,
                        2
                    ) }}
                </div>

            </div>
        </div>
    </div>

</div>

@endsection
