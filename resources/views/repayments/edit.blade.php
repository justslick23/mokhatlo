@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4">Edit Repayment</h4>

    <div class="card">
        <div class="card-body">
            <form method="POST"
                  action="{{ route('societies.repayments.update', [$society, $transaction]) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Amount Paid</label>
                    <input type="number"
                           name="amount"
                           step="0.01"
                           class="form-control"
                           value="{{ $transaction->amount }}"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Payment Date</label>
                    <input type="date"
                           name="transaction_date"
                           class="form-control"
                           value="{{ $transaction->transaction_date->toDateString() }}"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes"
                              class="form-control"
                              rows="2">{{ $transaction->notes }}</textarea>
                </div>

                <button class="btn btn-success">
                    Update Repayment
                </button>

                <a href="{{ route('societies.repayments.index', $society) }}"
                   class="btn btn-secondary">
                    Cancel
                </a>
            </form>
        </div>
    </div>
</div>
@endsection
