
@extends('layouts.app')

@section('title', 'Capture Loan')

@section('content')

<div class="mb-4">
    <a href="{{ route('loans.index') }}"
       class="small fw-semibold">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Loans
    </a>
</div>

<div class="mb-4">
    <p class="eyebrow mb-2">Loan Management</p>

    <h1 class="page-title">
        Capture Loan
    </h1>

    <p class="muted mt-2">
        Record an agreed loan under the current society policy.
        No separate system approval is required.
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
                    Loan Information
                </h2>

                <form method="POST"
                      action="{{ route('loans.store') }}">
                    @csrf

                    <input
                        type="hidden"
                        name="request_key"
                        value="{{ old('request_key', $applicationKey) }}"
                    >

                    {{-- Borrower selection --}}
                    @if($canCaptureForMembers)

                        <div class="mb-4">
                            <label
                                for="society_membership_id"
                                class="form-label"
                            >
                                Borrower
                            </label>

                            <select
                                name="society_membership_id"
                                id="society_membership_id"
                                class="form-select @error('society_membership_id') is-invalid @enderror"
                            >
                                <option value="">
                                    Myself — {{ auth()->user()->name }}
                                </option>

                                @foreach($members as $membership)
                                    @if(
                                        (int) $membership->user_id
                                        !== (int) auth()->id()
                                    )
                                        <option
                                            value="{{ $membership->id }}"
                                            @selected(
                                                (string) old('society_membership_id')
                                                === (string) $membership->id
                                            )
                                        >
                                            {{ $membership->user->name }}
                                            ({{ $membership->member_number ?? 'Member' }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>

                         

                            <div class="form-text">
                                Choose a member, or leave Myself selected
                                when recording your own loan.
                            </div>
                        </div>

                    @else

                        <div class="mb-4">
                            <label class="form-label">
                                Borrower
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="{{ auth()->user()->name }}"
                                disabled
                            >

                            <div class="form-text">
                                This loan will belong to your membership.
                            </div>
                        </div>

                    @endif

                    {{-- Amount --}}
                    <div class="mb-4">
                        <label
                            for="principal_amount"
                            class="form-label"
                        >
                            Loan Amount (M)
                        </label>

                        <input
                            type="number"
                            name="principal_amount"
                            id="principal_amount"
                            step="0.01"
                            min="0.01"
                            max="9999999999.99"
                            value="{{ old('principal_amount') }}"
                            class="form-control @error('principal_amount') is-invalid @enderror"
                            placeholder="e.g. 5000.00"
                            required
                        >

                        @error('principal_amount')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Loan purpose --}}
                    <div class="mb-4">
                        <label
                            for="purpose"
                            class="form-label"
                        >
                            Loan Purpose
                        </label>

                        <textarea
                            name="purpose"
                            id="purpose"
                            rows="4"
                            minlength="10"
                            maxlength="2000"
                            class="form-control @error('purpose') is-invalid @enderror"
                            placeholder="Describe the purpose of the loan..."
                            required
                        >{{ old('purpose') }}</textarea>

                        @error('purpose')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- Business rules --}}
                    <div class="mk-info-panel mb-4">
                        <div class="d-flex gap-3">
                            <i class="bi bi-info-circle fs-4"></i>

                            <div>
                                <strong class="d-block mb-2">
                                    Loan Terms & Interest Refund
                                </strong>

                                <p class="small mb-2">
                                    This loan has a repayment period of
                                    <strong>
                                        {{ $cycle->policy->loan_maturity_months }}
                                        months
                                    </strong>
                                    after the actual disbursement date.
                                </p>

                                <p class="small mb-2">
                                    The borrower may make one full payment
                                    or several partial payments before maturity.
                                </p>

                                <p class="small mb-0">
                                    Interest actually paid on the loan will
                                    be credited back to the borrower for
                                    their year-end payout. Collected penalties
                                    are distributed under the society's
                                    penalty-sharing policy.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Optional immediate disbursement --}}
                    @if($canConfirmDisbursement)

                        <div class="border rounded-3 p-3 mb-4">

                            <div class="form-check form-switch mb-3">
                                <input
                                    type="checkbox"
                                    name="already_disbursed"
                                    id="already_disbursed"
                                    value="1"
                                    class="form-check-input"
                                    @checked(old('already_disbursed'))
                                >

                                <label
                                    for="already_disbursed"
                                    class="form-check-label fw-semibold"
                                >
                                    The loan funds have already
                                    been disbursed
                                </label>
                            </div>

                            <div
                                id="disbursement-fields"
                                class="d-none"
                            >
                                <input
                                    type="hidden"
                                    name="disbursement_key"
                                    value="{{ old('disbursement_key', $disbursementKey) }}"
                                >

                                <div class="row g-3">

                                    <div class="col-md-6">
                                        <label
                                            for="disbursed_on"
                                            class="form-label"
                                        >
                                            Actual Disbursement Date
                                        </label>

                                        <input
                                            type="date"
                                            name="disbursed_on"
                                            id="disbursed_on"
                                            class="form-control"
                                            max="{{ now('Africa/Maseru')->toDateString() }}"
                                            value="{{ old('disbursed_on', now('Africa/Maseru')->toDateString()) }}"
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label
                                            for="payment_method"
                                            class="form-label"
                                        >
                                            Disbursement Method
                                        </label>

                                        <select
                                            name="payment_method"
                                            id="payment_method"
                                            class="form-select"
                                        >
                                            <option value="">
                                                Choose method
                                            </option>

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

                                    <div class="col-12">
                                        <label
                                            for="payment_reference"
                                            class="form-label"
                                        >
                                            Transaction Reference
                                        </label>

                                        <input
                                            type="text"
                                            name="payment_reference"
                                            id="payment_reference"
                                            class="form-control"
                                            maxlength="120"
                                            value="{{ old('payment_reference') }}"
                                            placeholder="Optional reference"
                                        >
                                    </div>

                                </div>
                            </div>
                        </div>

                    @endif

                    <div class="d-flex justify-content-end
                                flex-wrap gap-2">

                        <a
                            href="{{ route('loans.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary"
                            data-submit-once
                        >
                            <i class="bi bi-check-circle me-2"></i>
                            Capture Loan
                        </button>

                    </div>

                </form>
            </div>
        </div>
    </div>

    {{-- Society policy --}}
    <div class="col-lg-4">
        <div class="card-soft">
            <div class="card-body">

                <h2 class="section-title mb-4">
                    Society Loan Policy
                </h2>

                <div class="mb-4">
                    <div class="metric-label mb-2">
                        Savings Cycle
                    </div>

                    <strong>{{ $cycle->name }}</strong>
                </div>

                <div class="mb-4">
                    <div class="metric-label mb-2">
                        Interest Rate
                    </div>

                    <div class="h3 fw-bold">
                        {{ rtrim(
                            rtrim(
                                (string) $cycle->policy->loan_interest_percent,
                                '0'
                            ),
                            '.'
                        ) }}%
                    </div>

                    <span class="small muted">
                        {{ $cycle->policy->loan_interest_basis === 'per_month'
                            ? 'Per month (flat)'
                            : 'Per loan term (flat)' }}
                    </span>
                </div>

                <div class="mb-4">
                    <div class="metric-label mb-2">
                        Repayment Deadline
                    </div>

                    <strong>
                        {{ $cycle->policy->loan_maturity_months }}
                        months after disbursement
                    </strong>
                </div>

                <div class="mb-4">
                    <div class="metric-label mb-2">
                        Loan Penalty Rate
                    </div>

                    <strong>
                        {{ $cycle->policy->loan_penalty_percent }}%
                    </strong>
                </div>

                <div>
                    <div class="metric-label mb-2">
                        Penalty Sharing
                    </div>

                    <strong>
                        {{ match($cycle->policy->penalty_distribution_method) {
                            'equal_all' => 'Equal Member Shares',
                            'proportional_savings' => 'Based on Savings',
                            'society_reserve' => 'Society Reserve',
                            default => 'Not Configured',
                        } }}
                    </strong>
                </div>

            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('already_disbursed');
    const fields = document.getElementById('disbursement-fields');

    if (!toggle || !fields) {
        return;
    }

    function updateDisbursementFields() {
        fields.classList.toggle('d-none', !toggle.checked);

        const date = document.getElementById('disbursed_on');
        const method = document.getElementById('payment_method');

        if (date) date.required = toggle.checked;
        if (method) method.required = toggle.checked;
    }

    toggle.addEventListener(
        'change',
        updateDisbursementFields
    );

    updateDisbursementFields();
});
</script>

@endsection
