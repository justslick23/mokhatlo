
@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')

<p class="eyebrow mb-2">Welcome back</p>

<h1 class="auth-title mb-2">
    Sign in to Mokhatlo
</h1>

<p class="muted mb-4">
    Enter your account details to continue.
</p>

<form method="POST" action="{{ route('login.attempt') }}">
    @csrf

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
            autofocus
        >

        @error('email')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="mb-4">
        <div class="d-flex justify-content-between
                    align-items-center mb-2">
            <label for="password" class="form-label mb-0">
                Password
            </label>

            <a href="{{ route('password.request') }}"
               class="small fw-semibold">
                Forgot password?
            </a>
        </div>

        <input
            type="password"
            id="password"
            name="password"
            class="form-control @error('password') is-invalid @enderror"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
        >

        @error('password')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary w-100">
        Sign in
    </button>

</form>

<p class="text-center muted mt-4">
    Don't have an account?
    <a href="{{ route('register') }}" class="fw-semibold">
        Create one
    </a>
</p>

@endsection
