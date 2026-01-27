@extends('layouts.app')

@section('content')
<div class="container-fluid">

    <h3 class="mb-4">Year-End Settlement History</h3>

    <div class="card">
        <div class="card-header">
            <h5>Member Payouts</h5>
        </div>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Member</th>
                    <th>Amount</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            @foreach($payouts as $payout)
                <tr>
                    <td>{{ $payout->transaction_date->format('d M Y') }}</td>
                    <td>{{ $payout->member->user->name }}</td>
                    <td class="text-success fw-bold">
                        {{ number_format($payout->amount,2) }}
                    </td>
                    <td>{{ $payout->notes }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
