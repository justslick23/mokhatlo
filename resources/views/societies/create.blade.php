
@extends('layouts.guest')

@section('title', 'Create Society')

@section('content')

<div class="mb-4">
    <a href="{{ route('societies.index') }}"
       class="small fw-semibold">
        <i class="bi bi-arrow-left me-2"></i>
        Back to My Societies
    </a>
</div>

<div class="mb-4">
    <p class="eyebrow mb-2">Get Started</p>

    <h1 class="auth-title mb-2">
        Create a Society
    </h1>

    <p class="muted">
        Set up your savings society and start managing
        members, contributions, loans and savings cycles.
    </p>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('societies.store') }}">
    @csrf

    <div class="mb-4">
        <label for="name" class="form-label">
            Society Name <span class="text-danger">*</span>
        </label>

        <input
            type="text"
            id="name"
            name="name"
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name') }}"
            placeholder="e.g. Bophelo Savings Society"
            maxlength="160"
            required
            autofocus
        >

        @error('name')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="mb-4">
        <label for="description" class="form-label">
            Description
            <span class="muted fw-normal">(Optional)</span>
        </label>

        <textarea
            id="description"
            name="description"
            rows="4"
            maxlength="1000"
            class="form-control @error('description') is-invalid @enderror"
            placeholder="Tell us a little about your society"
        >{{ old('description') }}</textarea>

        @error('description')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="row g-3 mb-4">

        <div class="col-md-6">
            <label class="form-label">
                Currency
            </label>

            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-cash-coin"></i>
                </span>

                <input
                    type="text"
                    class="form-control"
                    value="LSL - Lesotho Loti"
                    disabled
                >
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label">
                Timezone
            </label>

            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-clock"></i>
                </span>

                <input
                    type="text"
                    class="form-control"
                    value="Africa/Maseru"
                    disabled
                >
            </div>
        </div>

    </div>

    <div class="mk-info-panel mb-4">
        <div class="d-flex gap-3">
            <i class="bi bi-info-circle fs-4"></i>

            <div>
                <strong class="d-block mb-1">
                    What happens next?
                </strong>

                <p class="small mb-0">
                    You'll become the owner of this society.
                    After creating it, you can invite members,
                    configure savings cycles, and establish
                    contribution and loan policies.
                </p>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-plus-circle me-2"></i>
        Create Society
    </button>

</form>

@endsection
