
@extends('layouts.app')

@section('title', 'Create Savings Cycle')

@section('content')

<div class="mb-4">
    <a href="{{ route('cycles.index') }}"
       class="small fw-semibold">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Savings Cycles
    </a>
</div>

<div class="mb-4">
    <p class="eyebrow mb-2">Cycle Configuration</p>
    <h1 class="page-title">New Savings Cycle</h1>

    <p class="muted mt-2">
        Configure the savings period, contribution requirements,
        loan policies, and penalties.
    </p>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Please review the following:</strong>

        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('cycles.store') }}">
    @csrf

    <!-- Cycle Information -->
    <div class="card-soft mb-4">
        <div class="card-body p-lg-4">

            <h2 class="section-title mb-1">
                <i class="bi bi-calendar3 me-2"></i>
                Cycle Information
            </h2>

            <p class="muted small mb-4">
                Define the name and duration of this savings cycle.
            </p>

            <div class="row g-3">

                <div class="col-md-8">
                    <label for="name" class="form-label">
                        Cycle Name
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        name="name"
                        id="name"
                        value="{{ old('name') }}"
                        placeholder="e.g. 2027 Savings Cycle"
                        maxlength="160"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label for="code" class="form-label">
                        Cycle Code
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        name="code"
                        id="code"
                        value="{{ old('code') }}"
                        placeholder="2027"
                        maxlength="32"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label for="start_date" class="form-label">
                        Start Date
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        name="start_date"
                        id="start_date"
                        value="{{ old('start_date') }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label for="end_date" class="form-label">
                        End Date
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        name="end_date"
                        id="end_date"
                        value="{{ old('end_date') }}"
                        required
                    >
                </div>

            </div>
        </div>
    </div>

    <!-- Contributions -->
    <div class="card-soft mb-4">
        <div class="card-body p-lg-4">

            <h2 class="section-title mb-1">
                <i class="bi bi-wallet2 me-2"></i>
                Contribution Settings
            </h2>

            <p class="muted small mb-4">
                Define how much members must contribute and when
                payments become overdue.
            </p>

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label"
                           for="minimum_monthly_contribution">
                        Minimum Monthly Contribution (M)
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="minimum_monthly_contribution"
                        id="minimum_monthly_contribution"
                        class="form-control"
                        value="{{ old('minimum_monthly_contribution', '500.00') }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label"
                           for="contribution_due_day">
                        Monthly Contribution Deadline
                    </label>

                    <select
                        name="contribution_due_day"
                        id="contribution_due_day"
                        class="form-select"
                        required
                    >
                        @for($day = 1; $day <= 28; $day++)
                            <option
                                value="{{ $day }}"
                                @selected((int) old('contribution_due_day', 25) === $day)
                            >
                                Day {{ $day }} of each month
                            </option>
                        @endfor
                    </select>

                    <div class="form-text">
                        For now, deadlines are restricted to days
                        1–28 so every month has a valid date.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label"
                           for="contribution_grace_days">
                        Grace Period (Days)
                    </label>

                    <input
                        type="number"
                        min="0"
                        max="30"
                        name="contribution_grace_days"
                        id="contribution_grace_days"
                        class="form-control"
                        value="{{ old('contribution_grace_days', 0) }}"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label"
                           for="contribution_penalty_percent">
                        Late Contribution Penalty (%)
                    </label>

                    <div class="input-group">
                        <input
                            type="number"
                            step="0.00001"
                            min="0"
                            max="100"
                            name="contribution_penalty_percent"
                            id="contribution_penalty_percent"
                            class="form-control"
                            value="{{ old('contribution_penalty_percent', 5) }}"
                            required
                        >
                        <span class="input-group-text">%</span>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Loan Settings -->
    <div class="card-soft mb-4">
        <div class="card-body p-lg-4">

            <h2 class="section-title mb-1">
                <i class="bi bi-cash-stack me-2"></i>
                Loan Settings
            </h2>

            <p class="muted small mb-4">
                Set the loan interest rate, repayment period
                and late repayment penalty.
            </p>

            <div class="row g-3">

                <div class="col-md-6">
                    <label for="loan_interest_percent"
                           class="form-label">
                        Loan Interest Rate (%)
                    </label>

                    <div class="input-group">
                        <input
                            type="number"
                            step="0.00001"
                            min="0"
                            max="100"
                            name="loan_interest_percent"
                            id="loan_interest_percent"
                            class="form-control"
                            value="{{ old('loan_interest_percent', 10) }}"
                            required
                        >
                        <span class="input-group-text">%</span>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="loan_interest_basis"
                           class="form-label">
                        Interest Rate Basis
                    </label>

                    <select
                        name="loan_interest_basis"
                        id="loan_interest_basis"
                        class="form-select"
                        required
                    >
                        <option value="per_term"
                            @selected(old('loan_interest_basis', 'per_term') === 'per_term')>
                            Per Loan Term
                        </option>

                        <option value="per_month"
                            @selected(old('loan_interest_basis') === 'per_month')>
                            Per Month
                        </option>
                    </select>

                    <div class="form-text">
                        The current policy model uses flat interest.
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="loan_max_term_months"
                           class="form-label">
                        Maximum Loan Repayment Period
                    </label>

                    <div class="input-group">
                        <input
                            type="number"
                            min="1"
                            max="60"
                            name="loan_max_term_months"
                            id="loan_max_term_months"
                            class="form-control"
                            value="{{ old('loan_max_term_months', 6) }}"
                            required
                        >
                        <span class="input-group-text">
                            Months
                        </span>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="loan_penalty_percent"
                           class="form-label">
                        Late Loan Repayment Penalty (%)
                    </label>

                    <div class="input-group">
                        <input
                            type="number"
                            step="0.00001"
                            min="0"
                            max="100"
                            name="loan_penalty_percent"
                            id="loan_penalty_percent"
                            class="form-control"
                            value="{{ old('loan_penalty_percent', 5) }}"
                            required
                        >
                        <span class="input-group-text">%</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="mk-info-panel mb-4">
        <div class="d-flex gap-3">
            <i class="bi bi-shield-check fs-4"></i>

            <div>
                <strong class="d-block mb-2">
                    Financial Policy Protection
                </strong>

                <p class="small mb-0">
                    The cycle will be created as a draft.
                    Its configuration should be reviewed before
                    activation. Later financial transactions will
                    retain their original policy values, even if
                    new cycles use different settings.
                </p>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end
                flex-wrap gap-2 mb-5">

        <a href="{{ route('cycles.index') }}"
           class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check2-circle me-2"></i>
            Create Savings Cycle
        </button>

    </div>

</form>

@endsection
