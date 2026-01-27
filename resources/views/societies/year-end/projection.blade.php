@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="mb-4">
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('societies.dashboard', $society) }}">{{ $society->name }}</a></li>
                <li class="breadcrumb-item active">Year-End Projection</li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-2 fw-bold">Year-End Projection</h2>
                <p class="text-muted mb-0">Live settlement calculation - updated as transactions occur</p>
            </div>
            <span class="badge bg-info p-3">
                <i class="fas fa-calendar me-2"></i>
                {{ $daysRemaining }} days remaining
            </span>
        </div>
    </div>

    <!-- Cycle Progress -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold">Cycle Progress</span>
                <span class="text-muted">{{ number_format($cycleProgress, 0) }}%</span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-primary" role="progressbar" 
                     style="width: {{ $cycleProgress }}%" 
                     aria-valuenow="{{ $cycleProgress }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 small text-muted">
                <span>Started: {{ $cycle->start_date->format('d M Y') }}</span>
                <span>Ends: {{ $cycle->end_date->format('d M Y') }}</span>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="projection-card">
                <div class="card-icon contributions">
                    <i class="fas fa-piggy-bank"></i>
                </div>
                <div class="card-content">
                    <span class="card-label">Total Contributions</span>
                    <h4 class="card-value">M {{ number_format($data['totalContributions'], 2) }}</h4>
                    <span class="card-meta">Pool size</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="projection-card">
                <div class="card-icon interest">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="card-content">
                    <span class="card-label">Total Interest</span>
                    <h4 class="card-value">M {{ number_format($data['totalInterest'], 2) }}</h4>
                    <span class="card-meta">To borrowers</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="projection-card">
                <div class="card-icon penalties">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="card-content">
                    <span class="card-label">Shared Penalties</span>
                    <h4 class="card-value">M {{ number_format($data['totalPenalties'], 2) }}</h4>
                    <span class="card-meta">To distribute</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="projection-card">
                <div class="card-icon payout">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-content">
                    <span class="card-label">Total Payouts</span>
                    @php
                        $totalPayouts = 0;
                        foreach($data['rows'] as $row) {
                            $totalPayouts += $row['payout'];
                        }
                    @endphp
                    <h4 class="card-value">M {{ number_format($totalPayouts, 2) }}</h4>
                    <span class="card-meta">Est. all members</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <strong>How It Works:</strong> 
        <ul class="mb-0 mt-2">
            <li>Members get back their <strong>contributions</strong></li>
            <li><strong>Interest</strong> from loans goes to the individual borrowers (shown for reference)</li>
            <li><strong>Penalties</strong> from overdue loans are shared <strong>equally</strong> among all active members</li>
            <li>Members with <strong>outstanding loans</strong> have those amounts deducted from their payout</li>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Member Breakdown Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Projected Member Settlements</h5>
                <span class="badge bg-secondary">{{ count($data['rows']) }} active members</span>
            </div>
        </div>

        @if(count($data['rows']) > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3">Member</th>
                        <th class="py-3 text-end">Contributions</th>
                        <th class="py-3 text-end">
                            <span title="Interest earned on loans (goes to borrower)">
                                Penalty Share
                                <i class="fas fa-info-circle text-muted" style="font-size: 0.75rem;"></i>
                            </span>
                        </th>
                        <th class="py-3 text-end">Outstanding Loans</th>
                        <th class="py-3 text-end">
                            <strong>Projected Payout</strong>
                        </th>
                    </tr>
                </thead>
                <tbody>
                @foreach($data['rows'] as $row)
                    <tr class="settlement-row">
                        <td class="py-3">
                            <div class="d-flex align-items-center">
                                <div class="member-avatar me-3">
                                    {{ strtoupper(substr($row['member']->user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $row['member']->user->name }}</div>
                                    <small class="text-muted">ID: {{ $row['member']->id }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 text-end">
                            <div class="fw-bold">M {{ number_format($row['contributions'], 2) }}</div>
                        </td>
                        <td class="py-3 text-end">
                            <div class="fw-semibold text-success">
                                M {{ number_format($row['penaltyShare'], 2) }}
                            </div>
                            <small class="text-muted">÷{{ count($data['rows']) }} members</small>
                        </td>
                        <td class="py-3 text-end">
                            @if($row['outstanding'] > 0)
                                <div class="fw-bold text-danger">
                                    M {{ number_format($row['outstanding'], 2) }}
                                </div>
                                <small class="text-muted">Active loans</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="py-3 text-end">
                            <div class="settlement-payout">
                                <div class="fw-bold text-success" style="font-size: 1.1rem;">
                                    M {{ number_format($row['payout'], 2) }}
                                </div>
                                @if($row['payout'] == 0)
                                    <small class="text-muted">No balance</small>
                                @else
                                    <small class="text-success">Available</small>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="card-body text-center py-5">
            <p class="text-muted">No active members found</p>
        </div>
        @endif

        <div class="card-footer bg-white border-top">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        <strong>Note:</strong> This projection updates in real-time as members contribute and borrow.
                        Final amounts may change based on loan repayments and additional penalties before cycle end.
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <a href="{{ route('societies.dashboard', $society) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
/* Projection Card Styling */
.projection-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    gap: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    height: 100%;
}

.projection-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.card-icon {
    width: 56px;
    height: 56px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.card-icon.contributions {
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.15), rgba(234, 179, 8, 0.15));
    color: #f97316;
}

.card-icon.interest {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(16, 185, 129, 0.15));
    color: #22c55e;
}

.card-icon.penalties {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(244, 63, 94, 0.15));
    color: #ef4444;
}

.card-icon.payout {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.15), rgba(139, 92, 246, 0.15));
    color: #a855f7;
}

.card-content {
    flex: 1;
}

.card-label {
    font-size: 0.875rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
    display: block;
    margin-bottom: 0.5rem;
}

.card-value {
    font-size: 1.625rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
    margin-bottom: 0.25rem;
}

.card-meta {
    font-size: 0.8125rem;
    color: #9ca3af;
    display: block;
}

/* Member Avatar */
.member-avatar {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Settlement Table */
.settlement-row {
    transition: background 0.2s;
}

.settlement-row:hover {
    background: #f9fafb;
}

.settlement-payout {
    padding: 0.5rem;
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(16, 185, 129, 0.1));
}

/* Table Enhancements */
.table thead th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    color: #6b7280;
    border-bottom: 2px solid #e5e7eb;
}

/* Alert */
.alert-info {
    background-color: #f0f9ff;
    border-color: #bae6fd;
    color: #0c4a6e;
}

.alert-info ul {
    padding-left: 1.5rem;
    margin-top: 0.5rem;
}

.alert-info li {
    margin-bottom: 0.5rem;
}

/* Progress Bar */
.progress {
    background-color: #e5e7eb;
}

.progress-bar {
    transition: width 0.6s ease;
}
</style>
@endsection