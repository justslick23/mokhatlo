
@extends('layouts.guest')

@section('title', 'Verify email')

@section('content')

<p class="eyebrow mb-2">One more step</p>

<h1 class="auth-title mb-2">
    Verify your email address
</h1>

<p class="muted mb-4">
    We've sent a verification link to
    <strong>{{ auth()->user()->email }}</strong>.
    Open the link in your email to activate your account.
</p>

<form method="POST" action="{{ route('verification.send') }}">
    @csrf

    <button type="submit" class="btn btn-primary w-100">
        Resend verification email
    </button>
</form>

<form method="POST" action="{{ route('logout') }}" class="mt-3">
    @csrf

    <button class="btn btn-outline-secondary w-100">
        Sign out
    </button>
</form>

@endsection
