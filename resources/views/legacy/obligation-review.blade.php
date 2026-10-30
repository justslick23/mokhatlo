@extends('layouts.app')
@section('title', 'Historical obligation reconciliation')
@section('content')
@php
    $money = fn ($cents) => 'M ' . number_format($cents / 100, 2);
@endphp
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <div class="text-uppercase small fw-semibold text-secondary">Mokhatlo · Step 9E.2</div>
            <h1 class="h3 fw-bold mb-1">Historical obligation reconciliation</h1>
            <div class="small text-secondary">{{ $cycle->name }} · {{ $cycle->start_date }} to {{ $cycle->end_date }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-end">
            <a class="btn btn-outline-secondary" href="{{ route('legacy.contributions.index', ['run' => $run->id]) }}">Period evidence</a>
            <a class="btn btn-outline-secondary" href="{{ route('legacy.reconciliation.index', ['run' => $run->id]) }}">Financial audit</a>
            <form method="GET" action="{{ route('legacy.obligations.index') }}">
                <label for="run" class="form-label small">Import run</label>
                <select class="form-select" id="run" name="run" onchange="this.form.submit()">
                    @foreach($runs as $available)
                        <option value="{{ $available->id }}" @selected($available->id == $run->id)>Run #{{ $available->id }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><strong>Review failed:</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="alert alert-info">
        <strong>No new money is being received.</strong> This links evidence-backed month attributions to formal
        contribution obligations. A required monthly amount and due date must come from historical policy or
        supporting records, <strong>not</strong> the receipt's value or payment date. Extra savings remains savings;
        this screen does not assess retrospective penalties or change the ledger.
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Reviewed month allocations linked</div><div class="fs-3 fw-semibold">{{ $summary['linked'] }} / {{ $summary['attributions'] }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Historical savings attributed</div><div class="fs-3 fw-semibold">{{ $money($summary['total_cents']) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Already linked to obligations</div><div class="fs-3 fw-semibold">{{ $money($summary['linked_cents']) }}</div></div></div></div>
    </div>
    @foreach($rows as $row)
        <div class="card mb-3 shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <strong>{{ $row->member_name }}</strong> · {{ $row->member_number }}
                    <div class="small text-secondary">Legacy transaction #{{ $row->source_tx }} · Receipt #{{ $row->contribution_payment_id }} · Paid {{ $row->paid_on }}</div>
                </div>
                <div class="text-end"><strong>{{ $money((int) $row->amount_cents) }}</strong>
                    <div class="small text-secondary">{{ substr($row->period_start, 0, 7) }} · {{ $row->evidence_reference }}</div>
                </div>
            </div>
            <div class="card-body">
                @if($row->link)
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                        <span class="badge text-bg-success">Linked</span>
                        <span>Obligation #{{ $row->link->contribution_obligation_id }}</span>
                        <span class="text-secondary small">{{ $row->link->evidence_reference }}</span>
                    </div>
                    @if($cycle->status === 'draft')
                        <form method="POST" action="{{ route('legacy.obligations.reverse', ['run' => $run->id, 'link' => $row->link->id]) }}" class="row g-2">
                            @csrf
                            <div class="col-lg-9"><input name="reason" class="form-control form-control-sm" required minlength="10" maxlength="2000" placeholder="Correction reason (10+ characters)"></div>
                            <div class="col-lg-3"><button class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Reverse obligation link? No money will be changed.')">Reverse link</button></div>
                        </form>
                    @endif
                @elseif($cycle->status !== 'draft')
                    <span class="text-secondary">This historical cycle is no longer in draft review.</span>
                @elseif($row->possible_obligations->count() === 1)
                    @php $obligation = $row->possible_obligations->first(); @endphp
                    <div class="small text-secondary mb-2">Existing due obligation #{{ $obligation->id }}:
                        {{ $money(\App\Support\Money::toCents((string)$obligation->required_amount)) }},
                        due {{ $obligation->due_date }}. The attribution amount can exceed the required minimum.
                    </div>
                    <form method="POST" action="{{ route('legacy.obligations.link', ['run' => $run->id, 'attribution' => $row->id]) }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="obligation_id" value="{{ $obligation->id }}">
                        <div class="col-md-9"><label class="form-label small">Evidence confirming this obligation</label><input name="evidence_reference" class="form-control" minlength="5" maxlength="255" required placeholder="Historical policy / meeting minutes / statement reference"></div>
                        <div class="col-md-3"><button class="btn btn-primary w-100">Link reviewed month</button></div>
                    </form>
                @elseif($row->possible_obligations->count() > 1)
                    <div class="alert alert-warning mb-0">Multiple obligations exist for this member/month. Resolve the duplicate in the source database before linking.</div>
                @else
                    <p class="small text-secondary">No obligation exists for this verified month. Create one only with documented evidence of the <strong>monthly minimum</strong> and <strong>due date</strong>.</p>
                    <form method="POST" action="{{ route('legacy.obligations.create-link', ['run' => $run->id, 'attribution' => $row->id]) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-2"><label class="form-label small">Required minimum (M)</label><input class="form-control" type="number" step="0.01" min="0.01" name="required_amount" required></div>
                        <div class="col-md-2"><label class="form-label small">Verified due date</label><input class="form-control" type="date" name="due_date" min="{{ substr($row->period_start, 0, 7) }}-01" max="{{ date('Y-m-t', strtotime($row->period_start)) }}" required></div>
                        <div class="col-md-5"><label class="form-label small">Evidence of the required amount and date</label><input class="form-control" name="evidence_reference" minlength="5" maxlength="255" required placeholder="Society policy / records reference"></div>
                        <div class="col-md-3"><button class="btn btn-primary w-100">Create obligation & link</button></div>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
    @if($rows->isEmpty())<div class="alert alert-warning">No active, evidence-backed month attributions were found in this historical import run.</div>@endif
    <div class="alert alert-warning">The ordinary contribution-compliance screens may still read the single <code>contribution_obligation_id</code> field and therefore need to be adapted to include these multi-month links before penalty computation or payout closure is enabled. Historical loan and clearing review gates also remain open.</div>
</div>
@endsection
