@extends('layouts.app')

@section('content')
<div class="container">
    <h3>Edit Society</h3>

    <form method="POST" action="{{ route('societies.update', $society) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="form-label">Society Name</label>
            <input type="text" name="name" value="{{ $society->name }}" class="form-control" required>
        </div>

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

        <button class="btn btn-primary">
            Save Changes
        </button>
    </form>
</div>
@endsection
