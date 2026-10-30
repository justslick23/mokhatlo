@extends('layouts.app')
@section('title', 'Historical contribution period review')
@section('content')
<div class="container-fluid px-0">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
    <div>
      <div class="text-uppercase small text-secondary fw-semibold">Mokhatlo · Historical reconciliation</div>
      <h1 class="h3 fw-bold mb-1">Contribution period review</h1>
      <div class="text-secondary small">{{ $cycle->name }} · {{ $cycle->start_date }} to {{ $cycle->end_date }}</div>
    </div>
    <div class="d-flex gap-2 align-items-end">
      <a class="btn btn-outline-secondary" href="{{ route('legacy.reconciliation.index', ['run' => $run->id]) }}">Back to audit</a>
      <form method="GET" action="{{ route('legacy.contributions.index') }}">
        <label class="form-label small mb-1">Import run</label>
        <select class="form-select" name="run" onchange="this.form.submit()">
          @foreach($runs as $option)
            <option value="{{ $option->id }}" @selected($option->id == $run->id)>Run #{{ $option->id }}</option>
          @endforeach
        </select>
      </form>
    </div>
  </div>

  @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>Review not recorded:</strong>
      <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>@endif

  <div class="alert alert-info">
    <strong>Evidence-based month attribution only.</strong>
    A payment date does not prove which month was paid. A single receipt may be split across months only when the source supports it. This step does not create obligations, penalties, or ledger journals; final monthly compliance and cycle closure remain blocked until obligation reconciliation is complete.
  </div>
  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body"><div class="small text-secondary">Receipts fully attributed</div><div class="fs-3 fw-semibold">{{ $summary['complete'] }} / {{ $summary['count'] }}</div></div></div></div>
    <div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body"><div class="small text-secondary">Original savings</div><div class="fs-3 fw-semibold">{{ $auditor->money($summary['total_cents']) }}</div></div></div></div>
    <div class="col-md-4"><div class="card h-100 shadow-sm"><div class="card-body"><div class="small text-secondary">Attributed to periods</div><div class="fs-3 fw-semibold">{{ $auditor->money($summary['attributed_cents']) }}</div></div></div></div>
  </div>

  @foreach($receiptRows as $receipt)
  <div class="card shadow-sm mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
      <div><strong>{{ $receipt->member_name }}</strong> <span class="text-secondary">· {{ $receipt->member_number }}</span>
        <div class="small text-secondary">Old transaction #{{ $receipt->original_id }} · Receipt #{{ $receipt->id }} · Paid {{ $receipt->paid_on }}</div></div>
      <div class="text-end"><strong>{{ $auditor->money($receipt->amount_cents) }}</strong>
        <div class="small {{ $receipt->remaining_cents === 0 ? 'text-success' : 'text-warning' }}">Unattributed: {{ $auditor->money($receipt->remaining_cents) }}</div></div>
    </div>
    <div class="card-body">
      @if($receipt->allocations->isNotEmpty())
      <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>Attributed month</th><th>Evidence reference</th><th class="text-end">Amount</th><th>Status / Correction</th></tr></thead>
        <tbody>
          @foreach($receipt->allocations as $entry)
          <tr>
            <td>{{ substr($entry->period_start, 0, 7) }}</td>
            <td>{{ $entry->evidence_reference }}</td>
            <td class="text-end">{{ $auditor->money((int) $entry->amount_cents) }}</td>
            <td>
              @if($entry->reversed_at)
                <span class="text-secondary">Reversed {{ $entry->reversed_at }} · {{ $entry->reversal_reason }}</span>
              @elseif($cycle->status === 'draft')
                <form class="d-flex gap-2" method="POST" action="{{ route('legacy.contributions.reverse', ['run' => $run->id, 'payment' => $receipt->id, 'attribution' => $entry->id]) }}">
                  @csrf
                  <input class="form-control form-control-sm" name="reason" minlength="10" maxlength="2000" required placeholder="Correction reason (10+ characters)">
                  <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Reverse this attribution? The original savings receipt will remain untouched.')">Reverse</button>
                </form>
              @else <span class="text-success">Recorded</span> @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table></div>
      @endif
      @if($receipt->remaining_cents > 0 && $cycle->status === 'draft' && $receipt->contribution_obligation_id === null)
      <form method="POST" action="{{ route('legacy.contributions.store', ['run' => $run->id, 'payment' => $receipt->id]) }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-2"><label class="form-label small">Verified month</label><input class="form-control" type="month" name="month" min="{{ substr($cycle->start_date,0,7) }}" max="{{ substr($cycle->end_date,0,7) }}" required></div>
        <div class="col-md-2"><label class="form-label small">Amount (M)</label><input class="form-control" name="amount" type="number" step="0.01" min="0.01" max="{{ number_format($receipt->remaining_cents/100, 2, '.', '') }}" required></div>
        <div class="col-md-3"><label class="form-label small">Proof / statement reference</label><input class="form-control" name="evidence_reference" minlength="5" maxlength="255" required placeholder="e.g. bank statement 2026-05 page 3"></div>
        <div class="col-md-3"><label class="form-label small">Review notes</label><input class="form-control" name="notes" maxlength="2000" placeholder="Why this receipt covers this month"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Record attribution</button></div>
      </form>
      @endif
    </div>
  </div>
  @endforeach
</div>
@endsection
