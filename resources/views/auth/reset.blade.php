
@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')

<h1 class="auth-title mb-2">Reset your password</h1>

<p class="muted mb-4">
    Choose a new secure password for your account.
</p>

<form method="POST" action="{{ route('password.update') }}">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
        <label for="email" class="form-label">Email</label>

        <input
            id="email"
            type="email"
            name="email"
            value="{{ old('email', $email) }}"
            class="form-control @error('email') is-invalid @enderror"
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
            New password
        </label>

        <input
            id="password"
            type="password"
            name="password"
            class="form-control @error('password') is-invalid @enderror"
            autocomplete="new-password"
            required
        >

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
            id="password_confirmation"
            type="password"
            name="password_confirmation"
            class="form-control"
            autocomplete="new-password"
            required
        >
    </div>

    <button class="btn btn-primary w-100" type="submit">
        Reset password
    </button>
</form>

@endsection
