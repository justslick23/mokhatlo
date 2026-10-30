
@extends('layouts.app')

@section('title', 'Savings Cycles')

@section('content')

<div class="d-flex align-items-center justify-content-between
            flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">Financial Management</p>
        <h1 class="page-title">Savings Cycles</h1>

        <p class="muted mt-2 mb-0">
            Manage contribution periods and financial policies
            for {{ $society->name }}.
        </p>
    </div>

    @can('cycles.create')
        <a href="{{ route('cycles.create') }}"
           class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>
            New Savings Cycle
        </a>
    @endcan
</div>

@if($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

@if($activeCycle)
    <div class="hero-panel mb-4">

        <div class="d-flex justify-content-between
                    align-items-start flex-wrap gap-3">

            <div>
                <p class="eyebrow text-warning mb-2">
                    Currently Active
                </p>

                <h2 class="h3 fw-bold mb-2">
                    {{ $activeCycle->name }}
                </h2>

                <p class="mb-0">
                    <i class="bi bi-calendar-event me-2"></i>

                    {{ $activeCycle->start_date->format('d M Y') }}
                    —
                    {{ $activeCycle->end_date->format('d M Y') }}
                </p>
            </div>

            <span class="badge bg-success px-3 py-2">
                Active
            </span>
        </div>

    </div>
@else
    <div class="mk-info-panel mb-4">

        <div class="d-flex gap-3 align-items-start">
            <i class="bi bi-info-circle fs-4"></i>

            <div>
                <strong class="d-block mb-1">
                    No active savings cycle
                </strong>

                <p class="mb-0 small">
                    Create or activate a cycle to establish
                    contribution and lending rules.
                </p>
            </div>
        </div>

    </div>
@endif

<div class="card-soft">
    <div class="card-body">

        <h2 class="section-title mb-4">
            All Savings Cycles
        </h2>

        <div class="table-responsive">

            <table class="table table-hover align-middle mk-table">
                <thead>
                    <tr>
                        <th>Cycle</th>
                        <th>Period</th>
                        <th>Monthly Minimum</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($cycles as $cycle)
                        <tr>
                            <td>
                                <strong class="d-block">
                                    {{ $cycle->name }}
                                </strong>

                                <span class="small muted">
                                    {{ $cycle->code }}
                                </span>
                            </td>

                            <td>
                                <div>
                                    {{ $cycle->start_date->format('d M Y') }}
                                </div>

                                <span class="small muted">
                                    to {{ $cycle->end_date->format('d M Y') }}
                                </span>
                            </td>

                            <td>
                                {{ $society->currency_code }}
                                {{ number_format(
                                    $cycle->policy?->minimum_monthly_contribution ?? 0,
                                    2
                                ) }}
                            </td>

                            <td>
                                @php
                                    $badge = match($cycle->status) {
                                        'active' => 'bg-success-subtle text-success',
                                        'draft' => 'bg-secondary-subtle text-secondary-emphasis',
                                        'closing' => 'bg-warning-subtle text-warning-emphasis',
                                        'closed', 'completed' => 'bg-primary-subtle text-primary-emphasis',
                                        default => 'bg-secondary-subtle text-secondary-emphasis'
                                    };
                                @endphp

                                <span class="badge {{ $badge }}">
                                    {{ ucfirst($cycle->status) }}
                                </span>
                            </td>

                            <td class="text-end">

                                @if(
                                    $cycle->status === 'draft'
                                    && !$activeCycle
                                    && auth()->user()->can('cycles.activate')
                                )

                                    <form
                                        method="POST"
                                        action="{{ route('cycles.activate', $cycle->id) }}"
                                        class="d-inline"
                                        onsubmit="return confirm('Activate this savings cycle? Financial rules will become operational.');"
                                    >
                                        @csrf

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-primary">
                                            Activate
                                        </button>
                                    </form>

                                @else
                                    <span class="small muted">—</span>
                                @endif

                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">

                                    <i class="bi bi-calendar3 fs-2 d-block mb-3"></i>

                                    <h3 class="h6 fw-bold">
                                        No savings cycles yet
                                    </h3>

                                    <p class="mb-3">
                                        Create your first savings cycle
                                        to configure contributions and loans.
                                    </p>

                                    @can('cycles.create')
                                        <a href="{{ route('cycles.create') }}"
                                           class="btn btn-primary">
                                            Create Savings Cycle
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        </div>

        <div class="mt-3">
            {{ $cycles->links() }}
        </div>

    </div>
</div>

@endsection
