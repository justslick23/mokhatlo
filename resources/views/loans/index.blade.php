@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Enhanced Header with Tabs -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('societies.dashboard', $society) }}">{{ $society->name }}</a></li>
                        <li class="breadcrumb-item active">Loans</li>
                    </ol>
                </nav>
                <h2 class="mb-2 fw-bold">Loan Portfolio</h2>
                <p class="text-muted mb-0">Comprehensive loan tracking and management system</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary">
                    <i class="fas fa-file-export me-2"></i>Export Report
                </button>
                <a href="{{ route('societies.loans.create', $society) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Issue Loan
                </a>
            </div>
        </div>

        <!-- Status Filter Tabs -->
        <ul class="nav nav-pills mt-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#all">
                    <i class="fas fa-list me-2"></i>All Loans
                    <span class="badge bg-primary ms-2">{{ $loans->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#active">
                    <i class="fas fa-clock me-2"></i>Active
                    <span class="badge bg-success ms-2">{{ $loans->where('status', 'active')->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#overdue">
                    <i class="fas fa-exclamation-triangle me-2"></i>Overdue
                    <span class="badge bg-danger ms-2">{{ $loans->where('status', 'overdue')->count() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#paid">
                    <i class="fas fa-check-circle me-2"></i>Repaid
                    <span class="badge bg-secondary ms-2">{{ $loans->where('status', 'paid')->count() }}</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Financial Metrics Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="metric-card gradient-orange">
                <div class="metric-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="metric-content">
                    <span class="metric-label">Total Disbursed</span>
                    <h3 class="metric-value">M {{ number_format($loans->sum('principal'), 2) }}</h3>
                    <span class="metric-change positive">
                        <i class="fas fa-arrow-up"></i> {{ $loans->where('status', 'active')->count() }} active
                    </span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="metric-card gradient-success">
                <div class="metric-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="metric-content">
                    <span class="metric-label">Total Repaid</span>
                    <h3 class="metric-value">M {{ number_format($loans->sum('amount_repaid'), 2) }}</h3>
                    @php
                        $repaymentRate = $loans->sum('principal') > 0 
                            ? ($loans->sum('amount_repaid') / $loans->sum('principal')) * 100 
                            : 0;
                    @endphp
                    <span class="metric-change positive">
                        <i class="fas fa-percentage"></i> {{ number_format($repaymentRate, 1) }}% rate
                    </span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="metric-card gradient-danger">
                <div class="metric-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="metric-content">
                    <span class="metric-label">Outstanding</span>
                    <h3 class="metric-value">M {{ number_format($loans->sum('outstanding_balance'), 2) }}</h3>
                    <span class="metric-change">
                        <i class="fas fa-users"></i> {{ $loans->where('outstanding_balance', '>', 0)->count() }} borrowers
                    </span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="metric-card gradient-purple">
                <div class="metric-icon">
                    <i class="fas fa-piggy-bank"></i>
                </div>
                <div class="metric-content">
                    <span class="metric-label">Shared Penalties Pool</span>
                    <h3 class="metric-value">M {{ number_format($loans->sum('penalty_amount'), 2) }}</h3>
                    @php
                        $overdueCount = $loans->where('status', 'overdue')->count();
                        $memberCount = $society->members()->where('status', 'active')->count();
                        $perMemberShare = $memberCount > 0 
                            ? ($loans->sum('penalty_amount') / $memberCount)
                            : 0;
                    @endphp
                    <span class="metric-change">
                        <i class="fas fa-share-alt"></i> M {{ number_format($perMemberShare, 2) }} per member
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Alert: Interest vs Penalties -->
    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Loan Structure:</strong> Interest accrues to individual borrowers. Penalties from overdue loans are collected in a shared pool and distributed equally to all active members at year-end.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Loans Grid/Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom-0 py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="input-group" style="max-width: 300px;">
                    <span class="input-group-text bg-white">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" placeholder="Search loans...">
                </div>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm">
                        <option>All Members</option>
                        <option>With Overdue</option>
                        <option>Near Due Date</option>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-sliders-h me-1"></i>Filters
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            @if($loans->isEmpty())
                <div class="empty-state py-5">
                    <div class="empty-state-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h5>No Loans Issued Yet</h5>
                    <p class="text-muted mb-4">Start by issuing your first loan to a society member</p>
                    <a href="{{ route('societies.loans.create', $society) }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Issue First Loan
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                    </div>
                                </th>
                                <th class="py-3">Borrower</th>
                                <th class="py-3">Loan Details</th>
                                <th class="py-3 text-end">Principal</th>
                                <th class="py-3 text-end">Outstanding</th>
                                <th class="py-3">Progress</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Due Date</th>
                                <th class="py-3 text-end">Penalty</th>
                                <th class="px-4 py-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loans as $loan)
                            <tr class="loan-row">
                                <td class="px-4 py-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox">
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="member-avatar me-3">
                                            {{ strtoupper(substr($loan->member->user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $loan->member->user->name }}</div>
                                            <small class="text-muted">ID: #{{ $loan->id }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div>
                                        <div class="small fw-semibold">{{ $loan->purpose ?? 'General Loan' }}</div>
                                        <small class="text-muted">Issued {{ $loan->issue_date->format('d M Y') }}</small>
                                    </div>
                                </td>
                                <td class="py-3 text-end">
                                    <div class="fw-bold">M {{ number_format($loan->principal, 2) }}</div>
                                    <small class="text-muted">@ {{ number_format($loan->interest_rate ?? $society->interest_rate ?? 0, 1) }}%</small>
                                </td>
                                <td class="py-3 text-end">
                                    <div class="fw-bold text-danger">M {{ number_format($loan->outstanding_balance, 2) }}</div>
                                </td>
                                <td class="py-3">
                                    @php
                                        $progress = $loan->total_amount > 0 
                                            ? (($loan->amount_repaid / $loan->total_amount) * 100) 
                                            : 0;
                                    @endphp
                                    <div class="progress-wrapper">
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ number_format($progress, 0) }}% repaid</small>
                                    </div>
                                </td>
                                <td class="py-3">
                                    @if($loan->status === 'active')
                                        <span class="status-badge status-active">
                                            <i class="fas fa-circle"></i> Active
                                        </span>
                                    @elseif($loan->status === 'paid')
                                        <span class="status-badge status-paid">
                                            <i class="fas fa-check-circle"></i> Paid
                                        </span>
                                    @else
                                        <span class="status-badge status-overdue">
                                            <i class="fas fa-exclamation-circle"></i> {{ ucfirst($loan->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="date-display">
                                        <i class="fas fa-calendar-day me-1"></i>
                                        {{ $loan->due_date->format('d M Y') }}
                                    </div>
                                    @if($loan->due_date->isPast() && $loan->status === 'active')
                                        <small class="text-danger">
                                            <i class="fas fa-clock"></i> {{ $loan->due_date->diffForHumans() }}
                                        </small>
                                    @endif
                                </td>
                                <td class="py-3 text-end">
                                    @if($loan->penalty_amount > 0)
                                        <div class="fw-bold text-warning">M {{ number_format($loan->penalty_amount, 2) }}</div>
                                        <small class="text-muted">Shared pool</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('societies.loans.show', [$society, $loan]) }}" 
                                           class="btn btn-sm btn-light" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-light" title="Record Payment">
                                            <i class="fas fa-dollar-sign"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light dropdown-toggle dropdown-toggle-split" 
                                                data-bs-toggle="dropdown">
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-file-pdf me-2"></i>Statement</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-ban me-2"></i>Write Off</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if(method_exists($loans, 'links'))
                    <div class="card-footer bg-white border-top-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Showing {{ $loans->firstItem() }} to {{ $loans->lastItem() }} of {{ $loans->total() }} loans</span>
                            {{ $loans->links() }}
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

<style>
/* Modern Metric Cards */
.metric-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    gap: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.metric-card.gradient-orange::before {
    background: linear-gradient(90deg, #f8b500, #e67c00);
}

.metric-card.gradient-success::before {
    background: linear-gradient(90deg, #1dd1a1, #10ac84);
}

.metric-card.gradient-danger::before {
    background: linear-gradient(90deg, #f76b8a, #ee5a6f);
}

.metric-card.gradient-purple::before {
    background: linear-gradient(90deg, #a855f7, #7c3aed);
}

.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.metric-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    color: #667eea;
}

.metric-content {
    flex: 1;
}

.metric-label {
    font-size: 0.875rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

.metric-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #111827;
    margin: 0.25rem 0;
}

.metric-change {
    font-size: 0.875rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.metric-change.positive {
    color: #10b981;
}

/* Navigation Pills */
.nav-pills .nav-link {
    border-radius: 8px;
    padding: 0.75rem 1.25rem;
    color: #6b7280;
    font-weight: 500;
    transition: all 0.2s;
}

.nav-pills .nav-link:hover {
    background: #f3f4f6;
    color: #111827;
}

.nav-pills .nav-link.active {
    background: #667eea;
    color: white;
}

.nav-pills .nav-link .badge {
    font-weight: 600;
}

/* Member Avatar */
.member-avatar {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Status Badges */
.status-badge {
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.813rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.status-badge i {
    font-size: 0.625rem;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-paid {
    background: #e5e7eb;
    color: #374151;
}

.status-overdue {
    background: #fee2e2;
    color: #991b1b;
}

/* Progress Wrapper */
.progress-wrapper {
    min-width: 120px;
}

.progress-wrapper .progress {
    margin-bottom: 0.25rem;
}

/* Date Display */
.date-display {
    font-size: 0.875rem;
    color: #374151;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    font-size: 4rem;
    color: #d1d5db;
    margin-bottom: 1.5rem;
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

.loan-row {
    transition: background 0.2s;
}

.loan-row:hover {
    background: #f9fafb;
}

/* Input Group */
.input-group-text {
    border-right: 0;
}

.form-control.border-start-0 {
    border-left: 0;
}

.form-control:focus.border-start-0 {
    box-shadow: none;
}

/* Alert Styling */
.alert-info {
    background-color: #f0f9ff;
    border-color: #bae6fd;
    color: #0c4a6e;
}

.alert-info strong {
    color: #0c4a6e;
}
</style>
@endsection