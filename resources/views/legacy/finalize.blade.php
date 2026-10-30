@extends('layouts.app')
@section('title', 'Historical finalization')
@section('content')
@php
  $fmt = fn ($c) => 'M '.number_format($c / 100, 2);
  $clearing = $status['clearing'];
  $expected = $clearing?->expected_cents ?? (json_decode($run->posted_totals, true)['contributions_cents'] + json_decode($run->posted_totals, true)['repayments_cents'] - json_decode($run->posted_totals, true)['disbursements_cents']);
@endphp
<div class="container-fluid px-0">
  <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-4">
    <div>
      <div class="text-muted text-uppercase small fw-semibold">Mokhatlo · Historical data</div>
      <h1 class="h3 fw-bold mb-1">Final Reconciliation</h1>
      <p class="text-secondary">Society #{{ $society->id }} · Imported cycle #{{ $status['cycle_id'] }}</p>
    </div>
    <a href="{{ route('legacy.reconciliation.index') }}" class="btn btn-outline-secondary">Open original financial audit</a>
  </div>

  @if(session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
  @if($errors->any()) <div class="alert alert-danger"><strong>Action not completed.</strong>
    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Recorded obligations</div><div class="h4 mb-0">{{ $status['compliance']['totals']['fully_paid_obligations'] }} / {{ $status['compliance']['totals']['obligations'] }}</div></div></div></div>
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Pending penalty decisions</div><div class="h4 mb-0">{{ $status['pending_penalties'] }}</div></div></div></div>
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Legacy clearing</div><div class="h4 mb-0">{{ $fmt($expected) }}</div></div></div></div>
    <div class="col-12 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Clearing status</div><div class="h4 mb-0">{{ $clearing?->status ?? 'Unprepared' }}</div></div></div></div>
  </div>

  <div class="card mb-4"><div class="card-header fw-semibold">Current safety gates</div><div class="card-body">
    @if($status['blockers'] === []) <p class="mb-0 text-success">No blockers detected by this historical review module. Standard payout policies and approvals still apply.</p>
    @else <ul class="mb-0">@foreach($status['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul> @endif
  </div></div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">Penalty evidence review — no automatic financial posting</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Source</th><th>Reason</th><th class="text-end">Amount</th><th>Latest decision</th><th style="min-width:320px">Record evidence</th></tr></thead>
      <tbody>@foreach($reviews as $item)
        <tr>
          <td>{{ $item->source_table }} #{{ $item->source_id_value }}</td>
          <td>{{ str_replace('_',' ', $item->reason) }}</td>
          <td class="text-end">{{ $fmt($item->amount_cents) }}</td>
          <td>{{ $item->decision ?? 'Pending' }}<div class="text-muted small">{{ $item->resolution_status }}</div></td>
          <td><form method="POST" action="{{ route('legacy.finalize.penalty', ['review' => $item->id]) }}" class="d-grid gap-2">
            @csrf
            <select name="decision" class="form-select form-select-sm" required>
              <option value="">Choose supported evidence classification</option>
              @if($item->source_table === 'loans')
                <option value="outstanding_documented">Outstanding charge proven (still requires subledger review)</option>
                <option value="not_due_or_void">Not a valid collectible charge</option>
                <option value="disputed">Needs further review</option>
              @else
                <option value="assessed_only">Charge assessed only, no collection proved</option>
                <option value="collected_documented">Collection proven (financial posting still required)</option>
                <option value="not_collected">Confirmed not collected</option>
                <option value="duplicate_void">Duplicate / void, supported by records</option>
                <option value="disputed">Needs further review</option>
              @endif
            </select>
            <input name="evidence_reference" class="form-control form-control-sm" placeholder="Statement / file reference" required minlength="5" maxlength="255">
            <textarea name="notes" class="form-control form-control-sm" placeholder="What does the evidence prove?" required minlength="10" rows="2"></textarea>
            <button class="btn btn-sm btn-outline-primary" type="submit">Save review decision</button>
          </form></td>
        </tr>
      @endforeach</tbody>
    </table></div>
    <div class="card-footer text-muted small">A 'collected' decision does not create revenue or cash. Unpaid or disputed charges are not waived by this screen. Confirm posting to the correct penalty subledger separately.</div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">Independent legacy cash verification</div>
    <div class="card-body">
      <p class="small text-secondary">Enter balances supported by bank, cash count and mobile-money evidence. Total must equal {{ $fmt($expected) }} before an independent reviewer can approve. This must reflect funds actually held, not simply the amount shown in the ledger.</p>
      @if(!$clearing || $clearing->status === 'prepared')
      <form method="POST" action="{{ route('legacy.finalize.clearing.prepare') }}" class="row g-3">
        @csrf <input type="hidden" name="run_id" value="{{ $run->id }}">
        @foreach(['cash'=>'Cash on hand','bank'=>'Bank account','mobile'=>'Mobile money'] as $field=>$title)
        <div class="col-12 col-md-4"><label class="form-label" for="amount_{{ $field }}">{{ $title }} (M)</label>
          <input class="form-control" id="amount_{{ $field }}" name="{{ $field }}" value="{{ old($field, $clearing ? number_format($clearing->{$field.'_cents'} / 100, 2, '.', '') : '0.00') }}" required pattern="\d+(\.\d{1,2})?"></div>
        @endforeach
        <div class="col-12"><label class="form-label">Evidence reference</label><input class="form-control" name="evidence_reference" value="{{ old('evidence_reference', $clearing?->evidence_reference) }}" required minlength="5" maxlength="255"></div>
        <div class="col-12"><label class="form-label">Reconciliation notes</label><textarea class="form-control" name="notes" required minlength="10" rows="3">{{ old('notes', $clearing?->notes) }}</textarea></div>
        <div class="col-12"><button class="btn btn-primary" type="submit">{{ $clearing ? 'Update evidence' : 'Prepare reconciliation' }}</button></div>
      </form>
      @else
        <div>Prepared amounts: Cash {{ $fmt($clearing->cash_cents) }}, Bank {{ $fmt($clearing->bank_cents) }}, Mobile {{ $fmt($clearing->mobile_cents) }}.</div>
        <div class="text-muted small">Evidence: {{ $clearing->evidence_reference }}</div>
      @endif
      @if($clearing && $clearing->status === 'prepared')
      <form method="POST" action="{{ route('legacy.finalize.clearing.approve') }}" class="mt-3" onsubmit="return confirm('I have independently checked the supporting evidence and the physical / account balances. Approve?')">
        @csrf <input type="hidden" name="run_id" value="{{ $run->id }}"><button type="submit" class="btn btn-outline-primary">Approve as independent reviewer</button>
      </form>
      @endif
      @if($clearing && $clearing->status === 'approved')
      <form method="POST" action="{{ route('legacy.finalize.clearing.post') }}" class="mt-3" onsubmit="return confirm('POST a balanced journal reclassifying legacy clearing to actual Cash/Bank/Mobile Money? This cannot be undone from this page.')">
        @csrf <input type="hidden" name="run_id" value="{{ $run->id }}"><button class="btn btn-danger" type="submit">Post approved clearing journal</button>
      </form>
      @endif
      @if($clearing && $clearing->status === 'posted')
        <p class="mt-3 mb-0 text-success fw-semibold">Posted under ledger transaction #{{ $clearing->ledger_transaction_id }}. Original 65 import journals remain unchanged.</p>
      @endif
    </div>
  </div>
  <div class="alert alert-warning">Outstanding historical loans still require actual repayments through the regular loan workflow. Do not artificially mark them settled, generate retrospective charges or approve payouts before every gate clears.</div>
</div>
@endsection
