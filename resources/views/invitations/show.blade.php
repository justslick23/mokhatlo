
@extends('layouts.guest')

@section('title', 'Accept Invitation')

@section('content')

<p class="eyebrow mb-2">Society Invitation</p>

<h1 class="auth-title mb-3">
    You're invited!
</h1>

<p class="muted mb-4">
    You've been invited to join a savings society on Mokhatlo.
</p>

<div class="card-soft mb-4">
    <div class="card-body">

        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="society-mark">
                {{ strtoupper(mb_substr($invitation->society->name, 0, 1)) }}
            </span>

            <div>
                <strong class="d-block fs-6">
                    {{ $invitation->society->name }}
                </strong>
                <span class="small muted">
                    Savings Society
                </span>
            </div>
        </div>

        <div class="mb-3">
            <div class="small muted mb-1">
                Your Role
            </div>
            <strong>
                {{ ucfirst($invitation->role_name) }}
            </strong>
        </div>

        <div>
            <div class="small muted mb-1">
                Invitation Expires
            </div>
            <strong>
                {{ $invitation->expires_at->format('d M Y H:i') }}
            </strong>
        </div>

    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

<form method="POST"
      action="{{ route('invitations.accept', $token) }}">
    @csrf

    <button type="submit" class="btn btn-primary w-100">
        Accept Invitation
    </button>
</form>

<a href="{{ route('societies.index') }}"
   class="btn btn-outline-secondary w-100 mt-3">
    Not Now
</a>

@endsection
