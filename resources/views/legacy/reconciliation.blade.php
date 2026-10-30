@extends('layouts.app')
@section('title', 'Historical reconciliation')
@section('content')
@php
    $checks = $result['checks'];
    $members = $result['members'];
    $run = $result['run'];
    $cycle = $result['cycle'];
@endphp
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <div class="text-uppercase small text-secondary fw-semibold">Mokhatlo · Migration audit</div>
            <h1 class="h3 fw-bold mb-1">Historical Reconciliation</h1>
            <div class="text-secondary small">{{ $cycle->name }} · {{ $cycle->start_date }} to {{ $cycle->end_date }} · Batch #{{ $run->batch_id }}</div>
        </div>
        <form method="GET" action="{{ route('legacy.reconciliation.index') }}">
            <label for="historyRun" class="form-label small mb-1">Import run</label>
            <select class="form-select" name="run" id="historyRun" onchange="this.form.submit()">
                @foreach($runs as $option)
                    <option value="{{ $option->id }}" @selected($option->id == $run->id)>{{ $option->cycle_name }} · #{{ $option->id }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <p><a href="{{ route('legacy.finalize.index') }}" class="btn btn-primary">Review penalties, clearing & remaining blockers</a></p>
    <div class="alert {{ $result['passed'] ? 'alert-success' : 'alert-danger' }}">
        <strong>{{ $result['passed'] ? 'Imported ledger checks passed' : 'Imported ledger audit found mismatches' }}</strong>
        <div class="small mt-1">This is a read-only accounting audit, not cycle activation, loan settlement or payout approval. Clearing requires independent verification before it becomes available cash; see the finalization screen for the current state.</div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3"><div class="card h-100 shadow-sm"><div class="card-body">
            <div class="text-secondary small">Unallocated receipts</div>
            <div class="fs-3 fw-bold">{{ $result['pending_allocations'] }}</div>
            <div class="small">Need obligation-month review</div>
        </div></div></div>
        <div class="col-12 col-md-3"><div class="card h-100 shadow-sm"><div class="card-body">
            <div class="text-secondary small">Penalty review items</div>
            <div class="fs-3 fw-bold">{{ $result['pending_penalties'] }}</div>
            <div class="small">Do not distribute yet</div>
        </div></div></div>
        <div class="col-12 col-md-3"><div class="card h-100 shadow-sm"><div class="card-body">
            <div class="text-secondary small">Unverified clearing</div>
            <div class="fs-3 fw-bold">{{ $auditor->money($result['expected_clearing_cents']) }}</div>
            <div class="small">Not available bank cash</div>
        </div></div></div>
        <div class="col-12 col-md-3"><div class="card h-100 shadow-sm"><div class="card-body">
            <div class="text-secondary small">Original outstanding loans</div>
            <div class="fs-3 fw-bold">{{ $auditor->money($result['open_loan_cents']) }}</div>
            <div class="small">Includes unverified penalties</div>
        </div></div></div>
    </div>

    <div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Independent import checks</div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Check</th><th class="text-end">Expected</th><th class="text-end">Actual</th><th class="text-end">Status</th></tr></thead>
            <tbody>@foreach($checks as $check)
                <tr><td>{{ $check['label'] }}</td><td class="text-end">{{ $check['expected'] }}</td>
                    <td class="text-end">{{ $check['actual'] }}</td><td class="text-end">
                        <span class="badge {{ $check['passed'] ? 'text-bg-success' : 'text-bg-danger' }}">{{ $check['passed'] ? 'Pass' : 'Mismatch' }}</span>
                    </td></tr>
            @endforeach</tbody>
        </table></div>
    </div>

    <div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Historical member positions</div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Member</th><th class="text-end">Savings</th><th class="text-end">Borrower interest credit</th><th class="text-end">Outstanding loans</th></tr></thead>
            <tbody>@foreach($members as $member)
                <tr><td>{{ $member['name'] }} <span class="text-secondary small">({{ $member['number'] }})</span></td>
                    <td class="text-end">{{ $auditor->money($member['savings_cents']) }}</td>
                    <td class="text-end">{{ $auditor->money($member['interest_cents']) }}</td>
                    <td class="text-end">{{ $auditor->money($member['outstanding_cents']) }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="card-footer text-secondary small">Amounts are original positions, not authorised payouts. No automatic debt offset is performed.</div>
    </div>

    <div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Penalty evidence queue</div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0">
            <thead><tr><th>Source</th><th>Reason</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
            <tbody>@forelse($result['review_items'] as $item)
                <tr><td>{{ $item['source_table'] }} #{{ $item['source_id'] }}</td><td>{{ str_replace('_',' ',$item['reason']) }}</td>
                    <td class="text-end">{{ $auditor->money($item['amount_cents']) }}</td>
                    <td>{{ $item['status'] }}</td></tr>
            @empty<tr><td colspan="4" class="text-secondary">No review items</td></tr>@endforelse</tbody>
        </table></div>
    </div>

    <div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Historical contribution allocation queue</div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0">
            <thead><tr><th>Old transaction</th><th>New membership</th><th>Paid on</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
            <tbody>@foreach($result['unallocated_items'] as $item)
                <tr><td>#{{ $item['source_id'] }}</td><td>#{{ $item['member_id'] }}</td><td>{{ $item['paid_on'] }}</td>
                    <td class="text-end">{{ $auditor->money($item['amount_cents']) }}</td><td>{{ str_replace('_',' ',$item['status']) }}</td></tr>
            @endforeach</tbody>
        </table></div>
        <div class="card-footer small text-secondary">These receipts are already posted as member savings. Allocating a month later must not post their cash a second time.</div>
    </div>
    <div class="alert alert-warning">Do not activate or close the historical cycle, approve payouts, or transfer clearing without supporting evidence and independent reconciliation. Review the live safety gates in Historical Finalization; original balances here describe the IMPORT BASELINE, not later repayments.</div>
</div>
@endsection
