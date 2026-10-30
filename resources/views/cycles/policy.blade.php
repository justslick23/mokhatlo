@extends('layouts.app')
@section('title', 'Configure cycle policy')
@section('content')
<div class="mb-4">
    <p class="eyebrow mb-2">Existing savings cycle</p>
    <h1 class="page-title">Configure policy — {{ $cycle->name }}</h1>
    <p class="muted mt-2">{{ $cycle->start_date->format('d M Y') }} — {{ $cycle->end_date->format('d M Y') }}. This changes only the policy for the existing draft; it does not replace imported records.</p>
</div>
<div class="alert alert-warning" role="alert">
    <strong>Confirm the actual society rules before saving.</strong> Historical obligations suggest a M500 monthly minimum for most members, but do not prove the global penalty rates, interest basis or grace period. Your imported obligations mostly use the <strong>last day of each month</strong>; the original application currently permits due days 1–28 only. If your real policy is month-end, do not substitute the 28th merely to activate—month-end support needs to be implemented in the obligation generator first.
</div>
@if ($errors->any())
    <div class="alert alert-danger"><strong>Please correct the policy settings:</strong>
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<form action="{{ route('cycles.policy.update', $cycle) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="card-soft mb-4"><div class="card-body">
        <h2 class="section-title mb-3">Contribution rules</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="minimum_monthly_contribution" class="form-label">Minimum monthly contribution (M)</label>
                <input type="number" name="minimum_monthly_contribution" id="minimum_monthly_contribution" class="form-control" step="0.01" min="0.01" required value="{{ old('minimum_monthly_contribution', $cycle->policy?->minimum_monthly_contribution) }}" placeholder="e.g. 500.00">
            </div>
            <div class="col-md-4">
                <label for="contribution_due_day" class="form-label">Due day (1–28)</label>
                <input type="number" name="contribution_due_day" id="contribution_due_day" class="form-control" min="1" max="28" required value="{{ old('contribution_due_day', $cycle->policy?->contribution_due_day) }}">
            </div>
            <div class="col-md-4">
                <label for="contribution_grace_days" class="form-label">Grace period (days)</label>
                <input type="number" name="contribution_grace_days" id="contribution_grace_days" class="form-control" min="0" max="60" required value="{{ old('contribution_grace_days', $cycle->policy?->contribution_grace_days) }}">
            </div>
            <div class="col-md-4">
                <label for="contribution_penalty_percent" class="form-label">Late contribution penalty (%)</label>
                <input type="number" name="contribution_penalty_percent" id="contribution_penalty_percent" class="form-control" step="0.00001" min="0" max="100" required value="{{ old('contribution_penalty_percent', $cycle->policy?->contribution_penalty_percent) }}">
            </div>
        </div>
    </div></div>
    <div class="card-soft mb-4"><div class="card-body">
        <h2 class="section-title mb-3">Loan rules</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="loan_interest_percent" class="form-label">Loan interest (%)</label>
                <input type="number" name="loan_interest_percent" id="loan_interest_percent" class="form-control" step="0.00001" min="0" max="100" required value="{{ old('loan_interest_percent', $cycle->policy?->loan_interest_percent) }}">
            </div>
            <div class="col-md-4">
                <label for="loan_interest_method" class="form-label">Interest calculation</label>
                <select id="loan_interest_method" name="loan_interest_method" class="form-select" required>
                    <option value="">Choose method</option>
                    @foreach (['flat' => 'Flat', 'reducing_balance' => 'Reducing balance'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('loan_interest_method', $cycle->policy?->loan_interest_method) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="loan_interest_basis" class="form-label">Interest basis</label>
                <select id="loan_interest_basis" name="loan_interest_basis" class="form-select" required>
                    <option value="">Choose basis</option>
                    @foreach (['per_term' => 'Per loan term', 'monthly' => 'Monthly'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('loan_interest_basis', $cycle->policy?->loan_interest_basis) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="loan_max_term_months" class="form-label">Maximum loan term (months)</label>
                <input type="number" name="loan_max_term_months" id="loan_max_term_months" class="form-control" min="1" max="120" required value="{{ old('loan_max_term_months', $cycle->policy?->loan_max_term_months) }}">
            </div>
            <div class="col-md-4">
                <label for="loan_penalty_percent" class="form-label">Late loan penalty (%)</label>
                <input type="number" name="loan_penalty_percent" id="loan_penalty_percent" class="form-control" step="0.00001" min="0" max="100" required value="{{ old('loan_penalty_percent', $cycle->policy?->loan_penalty_percent) }}">
            </div>
        </div>
    </div></div>
    <div class="d-flex justify-content-end align-items-center gap-3">
        <a href="{{ route('cycles.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">Save cycle policy</button>
    </div>
</form>
@endsection
