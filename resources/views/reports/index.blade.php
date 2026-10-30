
@extends('layouts.app')

@section('title', 'Financial Reports')

@section('content')

@include('reports.partials.analytics', [
    'filterRoute' => route('reports.index'),
    
])
@include('reports.partials.historical-contribution-coverage')
<div class="d-flex justify-content-between
            align-items-center flex-wrap gap-2 mb-4">

    <div>
        <h2 class="section-title mb-1">
            Member Payout Planning
        </h2>

        <p class="small muted mb-0">
            Review provisional year-end entitlements.
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2">

        <a href="{{ route('reports.member-statement') }}"
           class="btn btn-outline-primary btn-sm">
            <i class="bi bi-person-lines-fill me-2"></i>
            Member Statements
        </a>

        @can('reports.export')
            <a href="{{ route('reports.export') }}"
               class="btn btn-primary btn-sm">
                <i class="bi bi-download me-2"></i>
                Export Financial Summary
            </a>
        @endcan

    </div>
</div>

@if($payoutPreview)

    @php
        $payoutMembers = $society->memberships()
            ->with('user')
            ->whereIn(
                'id',
                array_column(
                    $payoutPreview['rows'],
                    'membership_id'
                )
            )
            ->get()
            ->keyBy('id');

        $money = fn (int $cents) =>
            'M ' . number_format($cents / 100, 2);
    @endphp

    {{-- Payout summary --}}
    <div class="row g-3 mb-4">

        @foreach([
            [
                'label' => 'Savings Capital',
                'key' => 'savings_cents'
            ],
            [
                'label' => 'Borrower Interest Refunds',
                'key' => 'interest_refund_cents'
            ],
            [
                'label' => 'Shared Penalty Distribution',
                'key' => 'penalty_share_cents'
            ],
            [
                'label' => 'Gross Provisional Payout',
                'key' => 'gross_payout_cents'
            ],
        ] as $metric)

            <div class="col-sm-6 col-xl-3">
                <div class="mk-analytics-card h-100">
                    <div class="mk-analytics-label">
                        {{ $metric['label'] }}
                    </div>

                    <div class="mk-analytics-value">
                        {{ $money(
                            $payoutPreview['totals'][$metric['key']]
                        ) }}
                    </div>
                </div>
            </div>

        @endforeach

    </div>

    {{-- Member-by-member payout preview --}}
    <div class="mk-chart-card mb-4">

        <div class="d-flex justify-content-between
                    align-items-start flex-wrap gap-3 mb-4">

            <div>
                <h2 class="mk-chart-heading">
                    Provisional Member Payouts
                </h2>

                <p class="small muted mb-0">
                    Savings + refundable borrower interest
                    + shared penalty allocation
                </p>
            </div>

            <span class="badge bg-warning-subtle
                         text-warning-emphasis px-3 py-2">
                Preview Only
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mk-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Savings</th>
                        <th>Interest Refund</th>
                        <th>Penalty Share</th>
                        <th class="text-end">
                            Gross Payout
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($payoutPreview['rows'] as $row)

                        @php
                            $member = $payoutMembers->get(
                                $row['membership_id']
                            );
                        @endphp

                        <tr>
                            <td>
                                <strong>
                                    {{ $member?->user?->name ?? 'Unknown Member' }}
                                </strong>

                                <div class="small muted">
                                    {{ $member?->member_number ?? '' }}
                                </div>
                            </td>

                            <td>
                                {{ $money($row['savings_cents']) }}
                            </td>

                            <td>
                                {{ $money(
                                    $row['interest_refund_cents']
                                ) }}
                            </td>

                            <td>
                                {{ $money(
                                    $row['penalty_share_cents']
                                ) }}
                            </td>

                            <td class="text-end">
                                <strong>
                                    {{ $money(
                                        $row['gross_payout_cents']
                                    ) }}
                                </strong>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="5"
                                class="text-center py-4 muted">
                                No members available for this preview.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr class="fw-bold">
                        <td>Total</td>

                        <td>
                            {{ $money(
                                $payoutPreview['totals']['savings_cents']
                            ) }}
                        </td>

                        <td>
                            {{ $money(
                                $payoutPreview['totals']['interest_refund_cents']
                            ) }}
                        </td>

                        <td>
                            {{ $money(
                                $payoutPreview['totals']['penalty_share_cents']
                            ) }}
                        </td>

                        <td class="text-end">
                            {{ $money(
                                $payoutPreview['totals']['gross_payout_cents']
                            ) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mk-info-panel mt-4">
            <div class="d-flex gap-3">

                <i class="bi bi-info-circle fs-4"></i>

                <p class="small mb-0">
                    This is a gross planning estimate, not an approved
                    or final payout. It excludes outstanding debt
                    deductions, unpaid obligations, prior distributions,
                    member eligibility adjustments and final cash
                    reconciliation. The cycle-closure workflow must
                    verify these amounts before any payout is posted.
                </p>
            </div>
        </div>

    </div>

@else

    <div class="mk-chart-card mb-4">
        <p class="muted mb-0">
            Activate a savings cycle to view provisional
            member payouts.
        </p>
    </div>

@endif

@endsection
