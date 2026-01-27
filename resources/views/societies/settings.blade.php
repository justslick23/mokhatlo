@extends('layouts.app')

@section('content')
<div class="container">
    <h3>Society Settings</h3>

    <form method="POST" action="{{ route('societies.settings.update', $society) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="form-label">Minimum Contribution</label>
            <input type="number" step="0.01"
                   name="minimum_contribution"
                   value="{{ $society->minimum_contribution }}"
                   class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Interest Rate (%)</label>
            <input type="number" step="0.01"
                   name="interest_rate"
                   value="{{ $society->interest_rate }}"
                   class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Penalty Type</label>
            <select name="penalty_type" class="form-select">
                <option value="fixed" {{ $society->penalty_type === 'fixed' ? 'selected' : '' }}>
                    Fixed
                </option>
                <option value="percentage" {{ $society->penalty_type === 'percentage' ? 'selected' : '' }}>
                    Percentage
                </option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Penalty Value</label>
            <input type="number" step="0.01"
                   name="penalty_value"
                   value="{{ $society->penalty_value }}"
                   class="form-control" required>
        </div>

        <button class="btn btn-success">
            Update Settings
        </button>
    </form>
</div>
@endsection
