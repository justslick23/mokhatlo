
@extends('layouts.app')

@section('title', 'Overview')

@section('content')

@if($canViewFinance)

    @include('reports.partials.analytics', [
        'filterRoute' => route('dashboard'),
    ])

@include('reports.partials.historical-contribution-coverage')

@else



    <div class="mb-4">
        <p class="eyebrow mb-2">My Workspace</p>

        <h1 class="page-title">
            Welcome Back
        </h1>

        <p class="muted mt-2">
            Your personal financial position in
            {{ $society->name }}.
        </p>
    </div>

    <div class="row g-3 mb-4">

        <div class="col-md-6">
            <div class="mk-analytics-card h-100">

                <span class="mk-analytics-icon tone-purple mb-3">
                    <i class="bi bi-piggy-bank"></i>
                </span>

                <p class="mk-analytics-label">
                    My Savings Contributions
                </p>

                <div class="mk-analytics-value">
                    M {{ \App\Support\Money::fromCents(
                        $mySavingsCents
                    ) }}
                </div>

                <p class="small muted mt-2 mb-0">
                    Total recorded contributions
                </p>
            </div>
        </div>

        <div class="col-md-6">
            <div class="mk-analytics-card h-100">

                <span class="mk-analytics-icon tone-teal mb-3">
                    <i class="bi bi-graph-up-arrow"></i>
                </span>

                <p class="mk-analytics-label">
                    My Refundable Loan Interest
                </p>

                <div class="mk-analytics-value">
                    M {{ \App\Support\Money::fromCents(
                        $myInterestCents
                    ) }}
                </div>

                <p class="small muted mt-2 mb-0">
                    Interest paid on your loans, held for payout
                </p>
            </div>
        </div>

    </div>

    <div class="mk-chart-card">

        <div class="d-flex justify-content-between
                    align-items-start flex-wrap gap-3">

            <div>
                <h2 class="mk-chart-heading">
                    My Loan Activity
                </h2>

                <p class="muted mb-0">
                    You have {{ $myLoansCount }}
                    recorded
                    {{ $myLoansCount === 1 ? 'loan' : 'loans' }}.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">

                @if(
                    auth()->user()->can('loans.view_own')
                    || auth()->user()->can('loans.view')
                )
                    <a href="{{ route('loans.index') }}"
                       class="btn btn-primary">
                        <i class="bi bi-cash-stack me-2"></i>
                        My Loans
                    </a>
                @endif

                @can('reports.view_own')
                    <a
                        href="{{ route('reports.member-statement') }}"
                        class="btn btn-outline-primary"
                    >
                        My Statement
                    </a>
                @endcan

            </div>
        </div>

    </div>

@endif

@endsection
