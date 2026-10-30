
@extends('layouts.guest')

@section('title', 'Create account')

@section('content')

<p class="eyebrow mb-2">Get started</p>

<h1 class="auth-title mb-2">
    Create your account
</h1>

<p class="muted mb-4">
    Join Mokhatlo and manage all your savings societies
    from one account.
</p>

<form method="POST" action="{{ route('register.attempt') }}">
    @csrf

    <div class="mb-3">
        <label for="name" class="form-label">
            Full name
        </label>

        <input
            type="text"
            id="name"
            name="name"
            class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name') }}"
            placeholder="Enter your full name"
            autocomplete="name"
            required
            autofocus
        >

        @error('name')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">
            Email address
        </label>

        <input
            type="email"
            id="email"
            name="email"
            class="form-control @error('email') is-invalid @enderror"
            value="{{ old('email') }}"
            placeholder="you@example.com"
            autocomplete="email"
            required
        >

        @error('email')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">
            Password
        </label>

        <input
            type="password"
            id="password"
            name="password"
            class="form-control @error('password') is-invalid @enderror"
            autocomplete="new-password"
            required
        >

        <div class="form-text">
            Minimum 10 characters, including uppercase,
            lowercase and a number.
        </div>

        @error('password')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label">
            Confirm password
        </label>

        <input
            type="password"
            id="password_confirmation"
            name="password_confirmation"
            class="form-control"
            autocomplete="new-password"
            required
        >
    </div>

    <button type="submit" class="btn btn-primary w-100">
        Create account
    </button>

</form>

<p class="text-center muted mt-4">
    Already have an account?
    <a href="{{ route('login') }}" class="fw-semibold">
        Sign in
    </a>
</p>

@endsection
