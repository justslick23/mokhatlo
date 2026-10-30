
@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')

<p class="eyebrow mb-2">Account recovery</p>

<h1 class="auth-title mb-2">Forgot your password?</h1>

<p class="muted mb-4">
    Enter your email address and we'll send you
    instructions to reset your password.
</p>

<form method="POST" action="{{ route('password.email') }}">
    @csrf

    <div class="mb-4">
        <label for="email" class="form-label">
            Email address
        </label>

        <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email') }}"
            class="form-control @error('email') is-invalid @enderror"
            required
            autofocus
        >

        @error('email')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <button class="btn btn-primary w-100" type="submit">
        Send reset link
    </button>
</form>

<p class="text-center mt-4">
    <a href="{{ route('login') }}">Back to sign in</a>
</p>

@endsection
