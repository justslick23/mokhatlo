@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4">Repayment Details</h4>

    <div class="card">
        <div class="card-body">

            <table class="table table-bordered">
                <tr>
                    <th>Member</th>
                    <td>{{ $transaction->member->user->name }}</td>
                </tr>
                <tr>
                    <th>Loan ID</th>
                    <td>#{{ $transaction->loan->id }}</td>
                </tr>
                <tr>
                    <th>Amount Paid</th>
                    <td>M{{ number_format($transaction->amount, 2) }}</td>
                </tr>
                <tr>
                    <th>Date</th>
                    <td>{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                </tr>
                <tr>
                    <th>Notes</th>
                    <td>{{ $transaction->notes ?? '—' }}</td>
                </tr>
            </table>

            <a href="{{ route('societies.repayments.edit', [$society, $transaction]) }}"
               class="btn btn-warning">
                Edit
            </a>

            <a href="{{ route('societies.repayments.index', $society) }}"
               class="btn btn-secondary">
                Back
            </a>

        </div>
    </div>
</div>
@endsection
