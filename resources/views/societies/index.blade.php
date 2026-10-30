
@extends('layouts.guest')

@section('title', 'My societies')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="eyebrow mb-2">My workspace</p>
        <h1 class="auth-title mb-0">My societies</h1>
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-secondary">
            Sign out
        </button>
    </form>
</div>

<p class="muted mb-4">
    Choose a society to continue, or create a new one.
</p>

<div class="d-grid gap-3">

    @forelse($memberships as $membership)

        <form method="POST"
              action="{{ route('societies.switch', $membership->society) }}">
            @csrf

            <button type="submit" class="society-option">

                <span class="society-mark">
                    {{ strtoupper(mb_substr($membership->society->name, 0, 1)) }}
                </span>

                <span class="flex-grow-1">
                    <strong class="d-block">
                        {{ $membership->society->name }}
                    </strong>

                    <span class="small muted">
                        {{ $membership->society->currency_code }}
                        &middot; Active member
                    </span>
                </span>

                <span class="fs-5 text-primary">&rarr;</span>
            </button>
        </form>

    @empty

        <div class="card-soft p-4 text-center">

            <div class="society-mark mx-auto mb-3">M</div>

            <h2 class="h5 fw-bold">
                No societies yet
            </h2>

            <p class="muted mb-0">
                Create your first savings society to get started.
            </p>

        </div>

    @endforelse

    <a href="{{ route('societies.create') }}"
       class="btn btn-primary mt-2">
        + Create a society
    </a>

</div>

@endsection
