
@extends('layouts.app')

@section('title', 'Contribution Penalties')

@section('content')

<div class="d-flex justify-content-between align-items-end
            flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">Financial Management</p>

        <h1 class="page-title">
            {{ $canManage ? 'Contribution Penalties' : 'My Penalties' }}
        </h1>

        <p class="muted mt-2 mb-0">
            Track assessed penalties, collections, waivers
            and outstanding balances.
        </p>
    </div>

</div>

@php
    $assessed = 0;
    $collected = 0;
    $waived = 0;
    $outstanding = 0;

    foreach ($penalties as $item) {
        $assessed += $item->assessedCents();
        $collected += $item->paidCents();
        $waived += $item->waivedCents();
        $outstanding += $item->outstandingCents();
    }
@endphp

<div class="row g-3 mb-4">

    @foreach([
        ['label' => 'Penalties Assessed', 'amount' => $assessed],
        ['label' => 'Payments Collected', 'amount' => $collected],
        ['label' => 'Waivers Granted', 'amount' => $waived],
        ['label' => 'Outstanding Penalties', 'amount' => $outstanding],
    ] as $metric)

        <div class="col-sm-6 col-xl-3">
            <div class="card-soft h-100">
                <div class="card-body">
                    <div class="metric-label">
                        {{ $metric['label'] }}
                    </div>

                    <div class="metric-value fs-4">
                        M {{ number_format($metric['amount'] / 100, 2) }}
                    </div>

                    <p class="small muted mb-0 mt-2">
                        On this page
                    </p>
                </div>
            </div>
        </div>

    @endforeach

</div>

<div class="card-soft">
    <div class="card-body">

        <div class="d-flex justify-content-between
                    align-items-center mb-4">

            <h2 class="section-title mb-0">
                Penalty Register
            </h2>

            <span class="small muted">
                {{ $penalties->total() }} records
            </span>
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle mk-table">

                <thead>
                    <tr>
                        @if($canManage)
                            <th>Member</th>
                        @endif

                        <th>Period</th>
                        <th>Assessed</th>
                        <th>Paid</th>
                        <th>Waived</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($penalties as $penalty)

                        @php
                            $balance = $penalty->outstandingCents();

                            $status = $balance > 0
                                ? 'Outstanding'
                                : (
                                    $penalty->paidCents() > 0
                                        ? 'Settled'
                                        : 'Waived'
                                );
                        @endphp

                        <tr>
                            @if($canManage)
                                <td>
                                    <strong>
                                        {{ $penalty->obligation->membership->user->name }}
                                    </strong>
                                </td>
                            @endif

                            <td>
                                {{ $penalty->obligation->period_start->format('M Y') }}
                            </td>

                            <td>
                                M {{ number_format($penalty->assessedCents() / 100, 2) }}
                            </td>

                            <td>
                                M {{ number_format($penalty->paidCents() / 100, 2) }}
                            </td>

                            <td>
                                M {{ number_format($penalty->waivedCents() / 100, 2) }}
                            </td>

                            <td>
                                <strong>
                                    M {{ number_format($balance / 100, 2) }}
                                </strong>
                            </td>

                            <td>
                                @if($balance > 0)
                                    <span class="badge bg-danger-subtle text-danger-emphasis">
                                        Outstanding
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success">
                                        {{ $status }}
                                    </span>
                                @endif
                            </td>

                            <td class="text-end">
                                <a href="{{ route('penalties.show', $penalty->id) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="{{ $canManage ? 8 : 7 }}">
                                <div class="empty-state">

                                    <i class="bi bi-shield-check fs-2 d-block mb-3"></i>

                                    <h3 class="h6 fw-bold">
                                        No penalties recorded
                                    </h3>

                                    <p class="mb-0">
                                        Assessed contribution penalties
                                        will appear here.
                                    </p>

                                </div>
                            </td>
                        </tr>

                    @endforelse
                </tbody>
            </table>

        </div>

        <div class="mt-4">
            {{ $penalties->links() }}
        </div>

    </div>
</div>

@endsection
