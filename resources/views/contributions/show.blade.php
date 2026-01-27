@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h3 class="mb-4">Contribution Details</h3>

    <div class="card">
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Member</dt>
                <dd class="col-sm-9">{{ $transaction->member->user->name }}</dd>

                <dt class="col-sm-3">Amount</dt>
                <dd class="col-sm-9">M {{ number_format($transaction->amount, 2) }}</dd>

                <dt class="col-sm-3">Date</dt>
                <dd class="col-sm-9">
                    {{ \Carbon\Carbon::parse($transaction->date)->format('d M Y') }}
                </dd>

                <dt class="col-sm-3">Notes</dt>
                <dd class="col-sm-9">{{ $transaction->notes ?? '—' }}</dd>
            </dl>

            <a href="{{ route('societies.contributions.index', $society) }}"
               class="btn btn-light">
                Back
            </a>
        </div>
    </div>
</div>
@endsection
