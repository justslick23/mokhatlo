
@extends('layouts.app')

@section('title', 'Loans')

@section('content')

<div class="d-flex justify-content-between align-items-end
            flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">Financial Management</p>

        <h1 class="page-title">
            {{ $canManage ? 'Loan Management' : 'My Loans' }}
        </h1>

        <p class="muted mt-2 mb-0">
            {{ $canManage
                ? 'Review loan applications and track lending activity.'
                : 'Apply for loans and track your applications.'
            }}
        </p>
    </div>

    @can('loans.apply')
        <a href="{{ route('loans.create') }}"
           class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>
            Apply for Loan
        </a>
    @endcan

</div>

<div class="card-soft mb-4">
    <div class="card-body">

        <form action="{{ route('loans.index') }}"
              method="GET">

            <div class="row g-3 align-items-end">

                <div class="col-md-5">
                    <label for="status" class="form-label">
                        Loan Status
                    </label>

                    <select name="status"
                            id="status"
                            class="form-select">

                        <option value="">All Loans</option>

                        @foreach([
                            'pending' => 'Pending Review',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'disbursed' => 'Disbursed',
                            'settled' => 'Settled',
                        ] as $value => $label)

                            <option value="{{ $value }}"
                                @selected(request('status') === $value)>
                                {{ $label }}
                            </option>

                        @endforeach
                    </select>
                </div>

                <div class="col-md-auto">
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-funnel me-2"></i>
                        Apply Filter
                    </button>
                </div>

                <div class="col-md-auto">
                    <a href="{{ route('loans.index') }}"
                       class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>

<div class="card-soft">
    <div class="card-body">

        <div class="d-flex justify-content-between
                    align-items-center mb-4">

            <h2 class="section-title mb-0">
                Loan Applications
            </h2>

            <span class="small muted">
                {{ $loans->total() }} records
            </span>

        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle mk-table">

                <thead>
                    <tr>
                        <th>Loan</th>

                        @if($canManage)
                            <th>Applicant</th>
                        @endif

                        <th>Principal</th>
                        <th>Interest</th>
                        <th>Total Repayment</th>
                        <th>Term</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($loans as $loan)

                        @php
                            $statusClass = match($loan->status) {
                                'pending' =>
                                    'bg-warning-subtle text-warning-emphasis',
                                'approved' =>
                                    'bg-primary-subtle text-primary-emphasis',
                                'rejected' =>
                                    'bg-danger-subtle text-danger-emphasis',
                                'disbursed' =>
                                    'bg-info-subtle text-info-emphasis',
                                'settled' =>
                                    'bg-success-subtle text-success',
                                default =>
                                    'bg-secondary-subtle text-secondary-emphasis',
                            };
                        @endphp

                        <tr>
                            <td>
                                <strong class="d-block">
                                    {{ $loan->loan_number }}
                                </strong>

                                <span class="small muted">
                                    {{ $loan->submitted_at->format('d M Y') }}
                                </span>
                            </td>

                            @if($canManage)
                                <td>
                                    <strong>
                                        {{ $loan->membership->user->name }}
                                    </strong>
                                </td>
                            @endif

                            <td>
                                M {{ number_format((float) $loan->principal_amount, 2) }}
                            </td>

                            <td>
                                M {{ number_format((float) $loan->quoted_interest_amount, 2) }}
                            </td>

                            <td>
                                <strong>
                                    M {{ number_format((float) $loan->quoted_total_amount, 2) }}
                                </strong>
                            </td>

                            <td>
                                {{ $loan->term_months }}
                                months
                            </td>

                            <td>
                                <span class="badge {{ $statusClass }}">
                                    {{ ucfirst($loan->status) }}
                                </span>
                            </td>

                            <td class="text-end">
                                <a
                                    href="{{ route('loans.show', $loan->id) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    View
                                </a>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 8 : 7 }}">
                                <div class="empty-state">

                                    <i class="bi bi-cash-stack fs-2 d-block mb-3"></i>

                                    <h3 class="h6 fw-bold">
                                        No loan applications
                                    </h3>

                                    <p class="mb-3">
                                        Loan applications will appear here.
                                    </p>

                                    @can('loans.apply')
                                        <a href="{{ route('loans.create') }}"
                                           class="btn btn-primary">
                                            Apply for Loan
                                        </a>
                                    @endcan

                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        <div class="mt-4">
            {{ $loans->links() }}
        </div>

    </div>
</div>

@endsection
