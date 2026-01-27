@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Enhanced Header -->
    <div class="page-header mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="{{ route('societies.dashboard', $society) }}">{{ $society->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('societies.cycles.index', $society) }}">Cycles</a></li>
                <li class="breadcrumb-item active">{{ $cycle->name }}</li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h2 class="mb-2 fw-bold">{{ $cycle->name }}</h2>
                <p class="text-muted mb-0">Detailed cycle information and statistics</p>
            </div>
            <div class="d-flex gap-2">
                @if($cycle->status === 'active')
                    <button class="btn btn-outline-primary">
                        <i class="fas fa-file-export me-2"></i>Export Report
                    </button>
                    <form method="POST" action="{{ route('societies.cycles.close', [$society, $cycle]) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to close this cycle? This action cannot be undone.')">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-stop-circle me-2"></i>Close Cycle
                        </button>
                    </form>
                @else
                    <button class="btn btn-outline-secondary">
                        <i class="fas fa-download me-2"></i>Download Archive
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="cycle-status-banner mb-4 {{ $cycle->status === 'active' ? 'banner-active' : 'banner-closed' }}">
        <div class="banner-icon-large">
            <i class="fas {{ $cycle->status === 'active' ? 'fa-sync-alt' : 'fa-check-circle' }}"></i>
        </div>
        <div class="banner-details">
            <div class="banner-status">
                @if($cycle->status === 'active')
                    <span class="status-badge status-active">
                        <i class="fas fa-circle me-1"></i>Active Cycle
                    </span>
                @else
                    <span class="status-badge status-closed">
                        <i class="fas fa-check-circle me-1"></i>Closed Cycle
                    </span>
                @endif
            </div>
            <div class="banner-dates">
                <span class="date-range">
                    <i class="fas fa-calendar-day me-2"></i>
                    {{ $cycle->start_date->format('d M Y') }}
                    <i class="fas fa-arrow-right mx-2"></i>
                    {{ $cycle->end_date->format('d M Y') }}
                </span>
                <span class="date-duration">
                    <i class="fas fa-clock me-2"></i>
                    {{ $cycle->start_date->diffInDays($cycle->end_date) }} days total
                </span>
            </div>
            @if($cycle->status === 'active')
                @php
                    $totalDays = $cycle->start_date->diffInDays($cycle->end_date);
                    $elapsedDays = $cycle->start_date->diffInDays(now());
                    $progress = $totalDays > 0 ? min(100, ($elapsedDays / $totalDays) * 100) : 0;
                    $remainingDays = max(0, $cycle->end_date->diffInDays(now()));
                @endphp
                <div class="banner-progress-wrapper">
                    <div class="progress">
                        <div class="progress-bar" style="width: {{ $progress }}%"></div>
                    </div>
                    <div class="progress-meta">
                        <span>{{ number_format($progress, 1) }}% complete</span>
                        @if($cycle->end_date->isFuture())
                            <span class="text-success">{{ $remainingDays }} days remaining</span>
                        @else
                            <span class="text-danger">Ended {{ $cycle->end_date->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Financial Overview Stats -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-success">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Total Contributions</span>
                    <h3 class="stat-value">M {{ number_format($cycle->transactions()->where('type', 'contribution')->sum('amount'), 2) }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">{{ $cycle->transactions()->where('type', 'contribution')->count() }} transactions</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-warning">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Loans Issued</span>
                    <h3 class="stat-value">M {{ number_format($cycle->loans()->sum('principal'), 2) }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">{{ $cycle->loans()->count() }} loans</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-info">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-undo-alt"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Repayments</span>
                    <h3 class="stat-value">M {{ number_format($cycle->transactions()->where('type', 'repayment')->sum('amount'), 2) }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">{{ $cycle->transactions()->where('type', 'repayment')->count() }} payments</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-primary">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Outstanding Balance</span>
                    @php
                        $outstanding = $cycle->loans()->sum('outstanding_balance');
                    @endphp
                    <h3 class="stat-value">M {{ number_format($outstanding, 2) }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">Pending repayments</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Summary -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <!-- Recent Transactions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="fas fa-history text-primary me-2"></i>Recent Activity
                    </h5>
                </div>
                <div class="card-body p-0">
                    @php
                        $recentTransactions = $cycle->transactions()->with('member.user')->latest()->take(10)->get();
                    @endphp
                    @if($recentTransactions->isEmpty())
                        <div class="text-center py-5">
                            <i class="fas fa-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                            <p class="text-muted">No transactions recorded yet</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3 border-0">Member</th>
                                        <th class="py-3 border-0">Type</th>
                                        <th class="py-3 border-0">Amount</th>
                                        <th class="py-3 border-0">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentTransactions as $transaction)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="member-avatar-sm">
                                                    {{ strtoupper(substr($transaction->member->user->name, 0, 2)) }}
                                                </div>
                                                <span class="ms-2">{{ $transaction->member->user->name }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            @if($transaction->type === 'contribution')
                                                <span class="badge bg-success-subtle text-success">
                                                    <i class="fas fa-arrow-down me-1"></i>Contribution
                                                </span>
                                            @else
                                                <span class="badge bg-info-subtle text-info">
                                                    <i class="fas fa-arrow-up me-1"></i>Repayment
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <span class="fw-bold">M {{ number_format($transaction->amount, 2) }}</span>
                                        </td>
                                        <td class="py-3">
                                            <small class="text-muted">{{ $transaction->created_at->format('d M Y, h:i A') }}</small>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Stats -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-chart-pie text-primary me-2"></i>Quick Statistics
                    </h6>
                    <div class="quick-stats">
                        <div class="quick-stat-item">
                            <div class="stat-icon-sm bg-success-subtle">
                                <i class="fas fa-users text-success"></i>
                            </div>
                            <div>
                                <div class="stat-label-sm">Active Members</div>
                                <div class="stat-value-sm">{{ $society->members()->where('status', 'active')->count() }}</div>
                            </div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="stat-icon-sm bg-warning-subtle">
                                <i class="fas fa-file-invoice-dollar text-warning"></i>
                            </div>
                            <div>
                                <div class="stat-label-sm">Active Loans</div>
                                <div class="stat-value-sm">{{ $cycle->loans()->where('status', 'active')->count() }}</div>
                            </div>
                        </div>
                        <div class="quick-stat-item">
                            <div class="stat-icon-sm bg-info-subtle">
                                <i class="fas fa-exchange-alt text-info"></i>
                            </div>
                            <div>
                                <div class="stat-label-sm">Total Transactions</div>
                                <div class="stat-value-sm">{{ $cycle->transactions()->count() }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cycle Information -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-info-circle text-info me-2"></i>Cycle Information
                    </h6>
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label">Created On</span>
                            <span class="info-value">{{ $cycle->created_at->format('d M Y') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Start Date</span>
                            <span class="info-value">{{ $cycle->start_date->format('d M Y') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">End Date</span>
                            <span class="info-value">{{ $cycle->end_date->format('d M Y') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Duration</span>
                            <span class="info-value">{{ $cycle->start_date->diffInDays($cycle->end_date) }} days</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status</span>
                            <span class="info-value">
                                @if($cycle->status === 'active')
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Closed</span>
                                @endif
                            </span>
                        </div>
                        @if($cycle->status === 'closed')
                        <div class="info-row">
                            <span class="info-label">Closed On</span>
                            <span class="info-value">{{ $cycle->updated_at->format('d M Y') }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loan Summary -->
    @if($cycle->loans()->count() > 0)
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-semibold">
                <i class="fas fa-hand-holding-usd text-warning me-2"></i>Loans in This Cycle
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3 border-0">Borrower</th>
                            <th class="py-3 border-0">Principal</th>
                            <th class="py-3 border-0">Interest</th>
                            <th class="py-3 border-0">Total Due</th>
                            <th class="py-3 border-0">Outstanding</th>
                            <th class="py-3 border-0">Status</th>
                            <th class="px-4 py-3 border-0 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cycle->loans()->with('member.user')->get() as $loan)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="d-flex align-items-center">
                                    <div class="member-avatar-sm">
                                        {{ strtoupper(substr($loan->member->user->name, 0, 2)) }}
                                    </div>
                                    <span class="ms-2">{{ $loan->member->user->name }}</span>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="fw-semibold">M {{ number_format($loan->principal, 2) }}</span>
                            </td>
                            <td class="py-3">
                                <span class="text-muted">M {{ number_format($loan->interest, 2) }}</span>
                            </td>
                            <td class="py-3">
                                <span class="fw-bold">M {{ number_format($loan->total_amount, 2) }}</span>
                            </td>
                            <td class="py-3">
                                <span class="text-danger fw-semibold">M {{ number_format($loan->outstanding_balance, 2) }}</span>
                            </td>
                            <td class="py-3">
                                @if($loan->status === 'active')
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                @elseif($loan->status === 'paid')
                                    <span class="badge bg-info-subtle text-info">Paid</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">{{ ucfirst($loan->status) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('societies.loans.show', [$society, $loan]) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

<style>
/* Breadcrumb */
.breadcrumb {
    background: transparent;
    padding: 0;
    margin: 0;
}

.breadcrumb-item a {
    color: #667eea;
    text-decoration: none;
}

.breadcrumb-item a:hover {
    text-decoration: underline;
}

/* Status Banner */
.cycle-status-banner {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 2rem;
}

.banner-active {
    border: 2px solid rgba(29, 209, 161, 0.3);
    background: linear-gradient(135deg, rgba(29, 209, 161, 0.05), white);
}

.banner-closed {
    border: 2px solid rgba(108, 117, 125, 0.2);
    background: linear-gradient(135deg, rgba(108, 117, 125, 0.03), white);
}

.banner-icon-large {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    flex-shrink: 0;
}

.banner-active .banner-icon-large {
    background: linear-gradient(135deg, #1dd1a1, #10ac84);
    color: white;
    animation: rotate-slow 4s linear infinite;
}

.banner-closed .banner-icon-large {
    background: linear-gradient(135deg, #6c757d, #5a6268);
    color: white;
}

@keyframes rotate-slow {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.banner-details {
    flex: 1;
}

.banner-status {
    margin-bottom: 0.75rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.status-badge.status-active {
    background: rgba(29, 209, 161, 0.1);
    color: #10b981;
}

.status-badge.status-closed {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}

.banner-dates {
    display: flex;
    gap: 2rem;
    margin-bottom: 1rem;
    font-size: 0.938rem;
    color: #6b7280;
}

.banner-progress-wrapper {
    max-width: 500px;
}

.banner-progress-wrapper .progress {
    height: 12px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.05);
    margin-bottom: 0.5rem;
}

.banner-progress-wrapper .progress-bar {
    background: linear-gradient(90deg, #1dd1a1, #10ac84);
    border-radius: 10px;
}

.progress-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.813rem;
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    display: flex;
    gap: 1rem;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    opacity: 0.1;
    transform: translate(30%, -30%);
}

.stat-card.stat-primary::before { background: #667eea; }
.stat-card.stat-success::before { background: #1dd1a1; }
.stat-card.stat-warning::before { background: #feca57; }
.stat-card.stat-info::before { background: #54a0ff; }

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-primary .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-success .stat-icon { background: linear-gradient(135deg, #1dd1a1, #10ac84); }
.stat-warning .stat-icon { background: linear-gradient(135deg, #feca57, #f8b500); }
.stat-info .stat-icon { background: linear-gradient(135deg, #54a0ff, #667eea); }

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #111827;
    margin: 0.25rem 0;
}

/* Member Avatar Small */
.member-avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.75rem;
    flex-shrink: 0;
}

/* Quick Stats */
.quick-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.quick-stat-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 10px;
}

.stat-icon-sm {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.bg-success-subtle { background: rgba(29, 209, 161, 0.1); }
.bg-warning-subtle { background: rgba(254, 202, 87, 0.1); }
.bg-info-subtle { background: rgba(84, 160, 255, 0.1); }

.stat-label-sm {
    font-size: 0.75rem;
    color: #6b7280;
}

.stat-value-sm {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
}

/* Info List */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 8px;
}

.info-label {
    font-size: 0.875rem;
    color: #6b7280;
}

.info-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
}

/* Cards */
.card {
    border-radius: 16px;
}

.card-header {
    border-radius: 16px 16px 0 0 !important;
}
</style>
@endsection