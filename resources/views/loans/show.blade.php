@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h4>Loan Details</h4>

    <table class="table">
        <tr><th>Member</th><td>{{ $loan->member->user->name }}</td></tr>
        <tr><th>Principal</th><td>{{ number_format($loan->principal,2) }}</td></tr>
        <tr><th>Interest</th><td>{{ number_format($loan->interest,2) }}</td></tr>
        <tr><th>Total</th><td>{{ number_format($loan->total_amount,2) }}</td></tr>
        <tr><th>Outstanding</th><td>{{ number_format($loan->outstanding_balance,2) }}</td></tr>
        <tr><th>Status</th><td>{{ ucfirst($loan->status) }}</td></tr>
    </table>

    @if($loan->status === 'active')
        <form method="POST" action="{{ route('societies.loans.write-off', [$society, $loan]) }}">
            @csrf @method('PUT')
            <button class="btn btn-danger">Write Off</button>
        </form>
    @endif
</div>
@endsection
