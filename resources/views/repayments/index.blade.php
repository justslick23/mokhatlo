@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Enhanced Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('societies.dashboard', $society) }}">{{ $society->name }}</a></li>
                        <li class="breadcrumb-item active">Repayments</li>
                    </ol>
                </nav>
                <h2 class="mb-2 fw-bold">Repayment Management</h2>
                <p class="text-muted mb-0">Track and manage all loan repayments</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary">
                    <i class="fas fa-file-export me-2"></i>Export Report
                </button>
                <a href="{{ route('societies.repayments.create', $society) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Record Repayment
                </a>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary active">
                    <i class="fas fa-list me-1"></i>All
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-calendar-day me-1"></i>Today
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-calendar-week me-1"></i>This Week
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-calendar-alt me-1"></i>This Month
                </button>
            </div>
            <div class="d-flex gap-2">
                <div class="input-group" style="max-width: 300px;">
                    <span class="input-group-text bg-white">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" placeholder="Search repayments...">
                </div>
                <select class="form-select form-select-sm" style="width: auto;">
                    <option>All Methods</option>
                    <option>Cash</option>
                    <option>Bank Transfer</option>
                    <option>Mobile Money</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-primary">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Total Collected</span>
                    <h3 class="stat-value">M {{ number_format($repayments->sum('amount'), 2) }}</h3>
                    <div class="stat-footer">
                        <span class="text-success"><i class="fas fa-arrow-up me-1"></i>8.2% from last month</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-success">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">This Month</span>
                    <h3 class="stat-value">M {{ number_format($repayments->where('created_at', '>=', now()->startOfMonth())->sum('amount'), 2) }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">{{ now()->format('F Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-info">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Total Transactions</span>
                    <h3 class="stat-value">{{ $repayments->count() }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">All time payments</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-warning">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Average Payment</span>
                    <h3 class="stat-value">M {{ $repayments->count() > 0 ? number_format($repayments->avg('amount'), 2) : '0.00' }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">Per transaction</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($repayments->isEmpty())
        <!-- Enhanced Empty State -->
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="empty-illustration mb-4">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <h4 class="mb-3">No Repayments Yet</h4>
                <p class="text-muted mb-4">Start tracking loan repayments by recording your first payment</p>
                <a href="{{ route('societies.repayments.create', $society) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Record First Repayment
                </a>
            </div>
        </div>
    @else
        <!-- Repayments Table Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 py-3">
                <h5 class="mb-0 fw-semibold">Recent Repayments</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3 border-0">Member</th>
                                <th class="py-3 border-0">Loan Reference</th>
                                <th class="py-3 border-0">Amount</th>
                                <th class="py-3 border-0">Payment Method</th>
                                <th class="py-3 border-0">Date & Time</th>
                                <th class="py-3 border-0">Status</th>
                                <th class="px-4 py-3 border-0 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($repayments as $repayment)
                            <tr class="repayment-row">
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="member-avatar">
                                            {{ strtoupper(substr($repayment->loan->member->user->name, 0, 2)) }}
                                        </div>
                                        <div class="ms-3">
                                            <div class="fw-semibold text-dark">{{ $repayment->loan->member->user->name }}</div>
                                            <small class="text-muted">{{ $repayment->loan->member->user->email }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex flex-column">
                                        <span class="badge bg-light text-dark d-inline-flex align-items-center" style="width: fit-content;">
                                            <i class="fas fa-hashtag me-1" style="font-size: 0.7rem;"></i>
                                            {{ $repayment->loan_id }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="amount-badge">M {{ number_format($repayment->amount, 2) }}</span>
                                </td>
                                <td class="py-3">
                                    <div class="payment-method">
                                        @if(($repayment->payment_method ?? 'cash') === 'cash')
                                            <div class="method-icon bg-success-subtle">
                                                <i class="fas fa-money-bill-wave text-success"></i>
                                            </div>
                                        @elseif(($repayment->payment_method ?? 'cash') === 'bank')
                                            <div class="method-icon bg-primary-subtle">
                                                <i class="fas fa-university text-primary"></i>
                                            </div>
                                        @else
                                            <div class="method-icon bg-info-subtle">
                                                <i class="fas fa-mobile-alt text-info"></i>
                                            </div>
                                        @endif
                                        <span class="ms-2">{{ ucfirst($repayment->payment_method ?? 'Cash') }}</span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="date-time-wrapper">
                                        <div class="date-text">
                                            <i class="fas fa-calendar-day text-muted me-2" style="font-size: 0.875rem;"></i>
                                            {{ $repayment->created_at->format('d M Y') }}
                                        </div>
                                        <small class="time-text">{{ $repayment->created_at->format('h:i A') }}</small>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="status-badge status-confirmed">
                                        <i class="fas fa-check-circle me-1"></i>Confirmed
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('societies.repayments.show', [$society, $repayment]) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" 
                                                data-bs-toggle="dropdown">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-download me-2"></i>Download Receipt</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-print me-2"></i>Print</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-times me-2"></i>Void Payment</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            @if(method_exists($repayments, 'links'))
                <div class="card-footer bg-white border-top">
                    {{ $repayments->links() }}
                </div>
            @endif
        </div>
    @endif
</div>

<style>
/* Stat Cards - Matching members index */
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

/* Member Avatar in Table */
.member-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
    flex-shrink: 0;
}

/* Amount Badge */
.amount-badge {
    display: inline-block;
    padding: 0.5rem 0.875rem;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    color: #667eea;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.938rem;
}

/* Payment Method */
.payment-method {
    display: flex;
    align-items: center;
}

.method-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
}

.bg-success-subtle { background-color: rgba(29, 209, 161, 0.1); }
.bg-primary-subtle { background-color: rgba(102, 126, 234, 0.1); }
.bg-info-subtle { background-color: rgba(84, 160, 255, 0.1); }

/* Date Time Wrapper */
.date-time-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.date-text {
    font-size: 0.875rem;
    color: #374151;
}

.time-text {
    font-size: 0.75rem;
    color: #9ca3af;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.813rem;
    font-weight: 600;
}

.status-confirmed {
    background-color: rgba(29, 209, 161, 0.1);
    color: #10ac84;
}

/* Table Styling */
.table-hover tbody tr {
    transition: all 0.2s ease;
}

.repayment-row:hover {
    background-color: rgba(102, 126, 234, 0.03);
    transform: scale(1.01);
}

.card {
    border-radius: 16px;
}

.card-header {
    border-radius: 16px 16px 0 0 !important;
}

/* Empty State */
.empty-illustration {
    font-size: 5rem;
    color: #e5e7eb;
}

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
</style>
@endsection