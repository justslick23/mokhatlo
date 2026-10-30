
@extends('layouts.app')

@section('title', 'Payout Draft')

@section('content')

@php
    $money = fn (int $cents) =>
        'M ' . number_format($cents / 100, 2);

    $isApproved = $draft->status === 'approved';

    $isCurrent =
        $draft->as_of_date->toDateString()
        === now('Africa/Maseru')->toDateString();
@endphp

<div class="mb-4">
    <a
        href="{{ route('payouts.index', [
            'cycle_id' => $draft->cycle_id
        ]) }}"
        class="small fw-semibold"
    >
        <i class="bi bi-arrow-left me-2"></i>
        Back to Year-End Payouts
    </a>
</div>

<div class="d-flex justify-content-between
            align-items-end flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">
            {{ $draft->cycle->name }}
        </p>

        <h1 class="page-title">
            Payout Draft #{{ $draft->revision }}
        </h1>

        <p class="muted mt-2 mb-0">
            Prepared by {{ $draft->creator?->name ?? 'System' }}
            on {{ $draft->created_at->format('d M Y H:i') }}
        </p>
    </div>

    @if($isApproved)
        <span class="badge bg-success-subtle
                     text-success px-3 py-2">
            <i class="bi bi-check-circle me-1"></i>
            Approved — Awaiting Payment
        </span>
    @elseif($draft->reconciliation)
        <span class="badge bg-info-subtle
                     text-info-emphasis px-3 py-2">
            Reconciled — Awaiting Approval
        </span>
    @else
        <span class="badge bg-warning-subtle
                     text-warning-emphasis px-3 py-2">
            Draft — Not Reconciled
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
        <strong>Please correct the following:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(!$isCurrent && !$isApproved)
    <div class="alert alert-warning">
        This payout draft was calculated on an earlier date.
        It remains available for audit, but a fresh draft
        must be prepared before reconciliation or approval.
    </div>
@endif

{{-- Payout financial summary --}}
<div class="row g-3 mb-4">
    @foreach([
        ['Savings Capital', $draft->savings_total_cents],
        ['Borrower Interest Refunds', $draft->interest_total_cents],
        ['Shared Penalty Allocation', $draft->penalty_distributed_cents],
        ['Gross Payout', $draft->gross_total_cents],
    ] as [$label, $amount])
        <div class="col-sm-6 col-xl-3">
            <div class="mk-analytics-card h-100">
                <p class="mk-analytics-label">
                    {{ $label }}
                </p>

                <div class="mk-analytics-value">
                    {{ $money($amount) }}
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Payout controls --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-soft h-100">
            <div class="card-body">
                <span class="metric-label d-block mb-2">
                    1. Draft Preparation
                </span>

                <strong class="d-block">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                    Completed
                </strong>

                <div class="small muted mt-2">
                    {{ $draft->creator?->name ?? 'System' }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-soft h-100">
            <div class="card-body">
                <span class="metric-label d-block mb-2">
                    2. Financial Reconciliation
                </span>

                @if($draft->reconciliation)
                    <strong class="d-block">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                        Completed
                    </strong>

                    <div class="small muted mt-2">
                        {{ $draft->reconciliation->confirmer?->name }}
                    </div>
                @else
                    <strong>Pending</strong>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-soft h-100">
            <div class="card-body">
                <span class="metric-label d-block mb-2">
                    3. Independent Approval
                </span>

                @if($draft->approval)
                    <strong class="d-block">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                        Approved
                    </strong>

                    <div class="small muted mt-2">
                        {{ $draft->approval->approver?->name }}
                    </div>
                @else
                    <strong>Pending</strong>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Saved reconciliation findings --}}
<div class="card-soft mb-4">
    <div class="card-body">
        <h2 class="section-title mb-3">
            Payout Readiness
        </h2>

        @if(count($draft->blockers ?? []) > 0)
            <div class="alert alert-warning mb-0">
                <strong class="d-block mb-3">
                    {{ count($draft->blockers) }}
                    unresolved issue(s) in this draft
                </strong>

                <ul class="mb-0">
                    @foreach($draft->blockers as $issue)
                        <li class="mb-2">
                            {{ $issue }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="alert alert-success mb-0">
                No automated payout blockers were recorded.
                Independent verification is still required.
            </div>
        @endif
    </div>
</div>

{{-- Saved member amounts --}}
<div class="card-soft mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between
                    align-items-start flex-wrap gap-3 mb-4">
            <div>
                <h2 class="section-title mb-1">
                    Member Payout Breakdown
                </h2>

                <p class="small muted mb-0">
                    Saved snapshot as of
                    {{ $draft->as_of_date->format('d M Y') }}
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mk-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Savings</th>
                        <th>Interest Refund</th>
                        <th>Penalty Share</th>
                        <th class="text-end">Gross Payout</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($draft->items as $item)
                        <tr>
                            <td>
                                <strong>
                                    {{ $item->member_name }}
                                </strong>

                                <div class="small muted">
                                    {{ $item->member_number ?? 'Member' }}
                                    &middot;
                                    {{ ucfirst($item->membership_status) }}
                                </div>
                            </td>

                            <td>
                                {{ $money($item->savings_cents) }}
                            </td>

                            <td>
                                {{ $money($item->interest_refund_cents) }}
                            </td>

                            <td>
                                {{ $money($item->penalty_share_cents) }}
                            </td>

                            <td class="text-end fw-bold">
                                {{ $money($item->gross_payout_cents) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center muted py-4">
                                No member calculations recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr class="fw-bold">
                        <td>Total</td>

                        <td>
                            {{ $money($draft->savings_total_cents) }}
                        </td>

                        <td>
                            {{ $money($draft->interest_total_cents) }}
                        </td>

                        <td>
                            {{ $money($draft->penalty_distributed_cents) }}
                        </td>

                        <td class="text-end">
                            {{ $money($draft->gross_total_cents) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Reconciliation --}}
<div class="card-soft mb-4">
    <div class="card-body p-lg-4">

        <h2 class="section-title mb-2">
            Financial Reconciliation
        </h2>

        <p class="small muted mb-4">
            Independently verify the physical cash count, bank statement
            and mobile-money balance against Mokhatlo's ledger.
        </p>

        @if($draft->reconciliation)
            @php
                $reconciliation = $draft->reconciliation;
            @endphp

            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Confirmed by
                <strong>
                    {{ $reconciliation->confirmer?->name }}
                </strong>
                on
                {{ $reconciliation->created_at->format('d M Y H:i') }}.
            </div>

            <div class="table-responsive">
                <table class="table align-middle mk-table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Ledger</th>
                            <th>Confirmed</th>
                            <th>Difference</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach([
                            [
                                'Cash',
                                $reconciliation->ledger_cash_cents,
                                $reconciliation->confirmed_cash_cents,
                            ],
                            [
                                'Bank',
                                $reconciliation->ledger_bank_cents,
                                $reconciliation->confirmed_bank_cents,
                            ],
                            [
                                'Mobile Money',
                                $reconciliation->ledger_mobile_money_cents,
                                $reconciliation->confirmed_mobile_money_cents,
                            ],
                        ] as [$label, $ledger, $confirmed])
                            <tr>
                                <td>{{ $label }}</td>
                                <td>{{ $money($ledger) }}</td>
                                <td>{{ $money($confirmed) }}</td>
                                <td class="fw-semibold">
                                    {{ $money($confirmed - $ledger) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3 small">
                <p>
                    <strong>Cash count:</strong>
                    {{ $reconciliation->cash_count_notes }}
                </p>

                @if($reconciliation->bank_statement_reference)
                    <p>
                        <strong>Bank reference:</strong>
                        {{ $reconciliation->bank_statement_reference }}
                    </p>
                @endif

                @if($reconciliation->mobile_money_reference)
                    <p>
                        <strong>Mobile-money reference:</strong>
                        {{ $reconciliation->mobile_money_reference }}
                    </p>
                @endif

                @if($reconciliation->notes)
                    <p class="mb-0">
                        <strong>Notes:</strong>
                        {{ $reconciliation->notes }}
                    </p>
                @endif
            </div>

        @elseif($canReconcile)

            <div class="alert alert-info">
                Enter the actual independently verified balances.
                Do not copy the ledger amounts without checking them.
            </div>

            <form
                method="POST"
                action="{{ route(
                    'payouts.drafts.reconcile',
                    $draft->id
                ) }}"
            >
                @csrf

                <input
                    type="hidden"
                    name="request_key"
                    value="{{ old('request_key', $reconciliationKey) }}"
                >

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label" for="cash_amount">
                            Confirmed Cash (M)
                        </label>

                        <div class="small muted mb-2">
                            Ledger:
                            {{ $money($ledgerBalances['cash_cents']) }}
                        </div>

                        <input
                            type="number"
                            name="cash_amount"
                            id="cash_amount"
                            class="form-control"
                            step="0.01"
                            min="0"
                            value="{{ old('cash_amount') }}"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="bank_amount">
                            Confirmed Bank Balance (M)
                        </label>

                        <div class="small muted mb-2">
                            Ledger:
                            {{ $money($ledgerBalances['bank_cents']) }}
                        </div>

                        <input
                            type="number"
                            name="bank_amount"
                            id="bank_amount"
                            class="form-control"
                            step="0.01"
                            min="0"
                            value="{{ old('bank_amount') }}"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label
                            class="form-label"
                            for="mobile_money_amount"
                        >
                            Confirmed Mobile Money (M)
                        </label>

                        <div class="small muted mb-2">
                            Ledger:
                            {{ $money($ledgerBalances['mobile_money_cents']) }}
                        </div>

                        <input
                            type="number"
                            name="mobile_money_amount"
                            id="mobile_money_amount"
                            class="form-control"
                            step="0.01"
                            min="0"
                            value="{{ old('mobile_money_amount') }}"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label
                            class="form-label"
                            for="bank_statement_reference"
                        >
                            Bank Statement Reference
                        </label>

                        <input
                            type="text"
                            name="bank_statement_reference"
                            id="bank_statement_reference"
                            class="form-control"
                            maxlength="160"
                            value="{{ old('bank_statement_reference') }}"
                        >
                    </div>

                    <div class="col-md-6">
                        <label
                            class="form-label"
                            for="mobile_money_reference"
                        >
                            Mobile Money Reference
                        </label>

                        <input
                            type="text"
                            name="mobile_money_reference"
                            id="mobile_money_reference"
                            class="form-control"
                            maxlength="160"
                            value="{{ old('mobile_money_reference') }}"
                        >
                    </div>

                    <div class="col-12">
                        <label
                            for="cash_count_notes"
                            class="form-label"
                        >
                            Cash Count Notes
                        </label>

                        <textarea
                            name="cash_count_notes"
                            id="cash_count_notes"
                            class="form-control"
                            rows="3"
                            minlength="10"
                            maxlength="2000"
                            required
                        >{{ old('cash_count_notes') }}</textarea>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">
                            Additional Reconciliation Notes
                        </label>

                        <textarea
                            name="notes"
                            id="notes"
                            class="form-control"
                            rows="3"
                            maxlength="2000"
                        >{{ old('notes') }}</textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input
                                type="checkbox"
                                name="confirmed"
                                id="confirmed"
                                value="1"
                                class="form-check-input"
                                required
                            >

                            <label
                                for="confirmed"
                                class="form-check-label"
                            >
                                I confirm that I have independently
                                verified these balances against the
                                corresponding cash count and statements.
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            data-submit-once
                        >
                            <i class="bi bi-check2-circle me-2"></i>
                            Confirm Reconciliation
                        </button>
                    </div>
                </div>
            </form>

        @else

            <p class="muted mb-0">
                Reconciliation is unavailable for this draft,
                or you do not have permission to perform it.
            </p>

        @endif
    </div>
</div>

{{-- Independent approval --}}
<div class="card-soft mb-4">
    <div class="card-body p-lg-4">

        <h2 class="section-title mb-2">
            Independent Payout Approval
        </h2>

        @if($draft->approval)
            <div class="alert alert-success mb-0">
                <strong class="d-block mb-2">
                    Payout Approved
                </strong>

                Approved by
                {{ $draft->approval->approver?->name }}
                on
                {{ $draft->approval->approved_at->format('d M Y H:i') }}.

                <p class="mt-3 mb-0">
                    {{ $draft->approval->approval_notes }}
                </p>

                <hr>

                <strong>
                    Authorised gross payout:
                    {{ $money($draft->gross_total_cents) }}
                </strong>

                <p class="small mb-0 mt-2">
                    No cash transfer or payout ledger transaction
                    has been created by this approval.
                </p>
            </div>

        @elseif($canApprove)

            <p class="small muted mb-4">
                Review the member allocations, reconciled account
                balances, eligibility and supporting evidence
                before approving this payout.
            </p>

            <form
                method="POST"
                action="{{ route(
                    'payouts.drafts.approve',
                    $draft->id
                ) }}"
            >
                @csrf

                <input
                    type="hidden"
                    name="request_key"
                    value="{{ old('request_key', $approvalKey) }}"
                >

                <div class="mb-3">
                    <label
                        for="approval_notes"
                        class="form-label"
                    >
                        Approval Notes
                    </label>

                    <textarea
                        name="approval_notes"
                        id="approval_notes"
                        class="form-control"
                        rows="4"
                        minlength="10"
                        maxlength="2000"
                        required
                    >{{ old('approval_notes') }}</textarea>
                </div>

                <div class="form-check mb-4">
                    <input
                        type="checkbox"
                        id="approved_terms"
                        name="approved_terms"
                        value="1"
                        class="form-check-input"
                        required
                    >

                    <label
                        for="approved_terms"
                        class="form-check-label"
                    >
                        I confirm that the payout calculation,
                        member allocations, reconciliation
                        and financial supporting records
                        have been reviewed and approved.
                    </label>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                    data-submit-once
                    onclick="return confirm('Approve this payout batch? This will authorise the amounts, but will not transfer any money.');"
                >
                    <i class="bi bi-shield-check me-2"></i>
                    Approve Payout Batch
                </button>
            </form>

        @else
            <p class="muted mb-0">
                Approval is unavailable until the latest draft is
                reconciled, all blockers are cleared, and an
                independent authorised approver is signed in.
            </p>
        @endif
    </div>
</div>


@if($draft->status === 'approved')
    <div class="mk-chart-card mb-4">
        <h2 class="mk-chart-heading mb-3">
            Member Payout Settlement
        </h2>

        @if($draft->batch)
            <p class="muted">
                The settlement batch has already been started.
                Continue recording and reviewing member payments.
            </p>

            <a
                href="{{ route(
                    'payouts.batches.show',
                    $draft->batch->id
                ) }}"
                class="btn btn-primary"
            >
                Open Payout Register
            </a>

        @can('payouts.execute')
        @elsecan('payouts.view')
            <p class="small muted mb-0 mt-3">
                You can review the payout register but cannot
                record payment transactions.
            </p>
        @endcan

        @else
            @can('payouts.execute')
                <p class="muted">
                    Begin settlement using the approved member
                    entitlements. No bank transfer is initiated.
                </p>

                <form
                    method="POST"
                    action="{{ route(
                        'payouts.drafts.start',
                        $draft->id
                    ) }}"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="request_key"
                        value="{{ $startKey }}"
                    >

                    <div class="form-check mb-3">
                        <input
                            type="checkbox"
                            name="confirmed"
                            id="start-confirmed"
                            value="1"
                            class="form-check-input"
                            required
                        >

                        <label
                            for="start-confirmed"
                            class="form-check-label"
                        >
                            I have verified the approved payout
                            and understand that starting settlement
                            locks the financial entitlements.
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        data-submit-once
                    >
                        Start Payout Settlement
                    </button>
                </form>
            @else
                <p class="muted mb-0">
                    Awaiting an authorised official to begin settlement.
                </p>
            @endcan
        @endif
    </div>
@endif


<div class="mk-info-panel">
    <div class="d-flex gap-3">
        <i class="bi bi-lock fs-4"></i>

        <p class="small mb-0">
            The member payout breakdown is preserved as a financial
            snapshot. Reconciliation and approval records are
            attached to this snapshot for audit purposes.
            Actual member payments and cycle closure will be
            implemented separately in Step 8C.
        </p>
    </div>
</div>

@endsection
