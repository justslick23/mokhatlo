@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item">
                        <a href="{{ route('societies.dashboard', $society) }}">{{ $society->name }}</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('societies.reports.summary', $society) }}">Reports</a>
                    </li>
                    <li class="breadcrumb-item active">Transaction Ledger</li>
                </ol>
            </nav>
            <h2 class="mb-1">Transaction Ledger</h2>
            <p class="text-muted mb-0">Complete record of all society transactions</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print
            </button>
            <button class="btn btn-primary">
                <i class="fas fa-download me-2"></i>Export Excel
            </button>
        </div>
    </div>

    <!-- Transaction Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-primary me-3">
                            <i class="fas fa-list text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Total Transactions</h6>
                            <h4 class="mb-0 fw-bold">{{ $transactions->total() }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-success me-3">
                            <i class="fas fa-arrow-down text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Money In</h6>
                            <h4 class="mb-0 fw-bold">
                                M {{ number_format($transactions->whereIn('type', ['contribution', 'loan_repayment'])->sum('amount'), 2) }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-danger me-3">
                            <i class="fas fa-arrow-up text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Money Out</h6>
                            <h4 class="mb-0 fw-bold">
                                M {{ number_format($transactions->where('type', 'loan_disbursement')->sum('amount'), 2) }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-info me-3">
                            <i class="fas fa-calendar-day text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">This Month</h6>
                            <h4 class="mb-0 fw-bold">
                                {{ $transactions->where('transaction_date', '>=', now()->startOfMonth())->count() }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Ledger Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold">Transaction History</h5>
                <div class="d-flex gap-2">
                    <input type="date" class="form-control form-control-sm" placeholder="From Date" style="width: 150px;">
                    <input type="date" class="form-control form-control-sm" placeholder="To Date" style="width: 150px;">
                    <select class="form-select form-select-sm" style="width: auto;">
                        <option>All Types</option>
                        <option>Contributions</option>
                        <option>Loan Disbursement</option>
                        <option>Loan Repayment</option>
                        <option>Penalties</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if($transactions->isEmpty())
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-receipt text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-muted">No Transactions Found</h5>
                    <p class="text-muted mb-0">Transaction history will appear here</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3 text-muted small text-uppercase">ID</th>
                                <th class="py-3 text-muted small text-uppercase">Date</th>
                                <th class="py-3 text-muted small text-uppercase">Member</th>
                                <th class="py-3 text-muted small text-uppercase">Type</th>
                                <th class="py-3 text-muted small text-uppercase">Description</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Amount</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $runningBalance = 0;
                            @endphp
                            @foreach($transactions as $tx)
                            @php
                                if (in_array($tx->type, ['contribution', 'loan_repayment'])) {
                                    $runningBalance += $tx->amount;
                                } else {
                                    $runningBalance -= $tx->amount;
                                }
                            @endphp
                            <tr class="transaction-row">
                                <td class="px-4 py-3">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-hashtag me-1"></i>{{ $tx->id }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-calendar-alt text-muted me-2 small"></i>
                                        <div>
                                            <div>{{ $tx->transaction_date->format('d M Y') }}</div>
                                            <small class="text-muted">{{ $tx->transaction_date->format('h:i A') }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    @if($tx->member)
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-2">
                                            {{ strtoupper(substr($tx->member->user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold small">{{ $tx->member->user->name }}</div>
                                        </div>
                                    </div>
                                    @else
                                    <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    @php
                                        $typeConfig = [
                                            'contribution' => ['color' => 'success', 'icon' => 'wallet'],
                                            'loan_disbursement' => ['color' => 'danger', 'icon' => 'hand-holding-usd'],
                                            'loan_repayment' => ['color' => 'info', 'icon' => 'sync-alt'],
                                            'penalty' => ['color' => 'warning', 'icon' => 'exclamation-triangle'],
                                        ];
                                        $config = $typeConfig[$tx->type] ?? ['color' => 'secondary', 'icon' => 'circle'];
                                    @endphp
                                    <span class="badge badge-{{ $config['color'] }}-custom">
                                        <i class="fas fa-{{ $config['icon'] }} me-1"></i>
                                        {{ ucfirst(str_replace('_', ' ', $tx->type)) }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span class="text-muted small">
                                        {{ $tx->notes ?? 'No description' }}
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="fw-semibold {{ in_array($tx->type, ['contribution', 'loan_repayment']) ? 'text-success' : 'text-danger' }}">
                                        {{ in_array($tx->type, ['contribution', 'loan_repayment']) ? '+' : '-' }}
                                        M {{ number_format($tx->amount, 2) }}
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="fw-bold">M {{ number_format($runningBalance, 2) }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="card-footer bg-white border-top">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<style>
/* Icon Boxes */
.icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.bg-gradient-success {
    background: linear-gradient(135deg, #1dd1a1 0%, #10ac84 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #54a0ff 0%, #667eea 100%);
}

.bg-gradient-danger {
    background: linear-gradient(135deg, #f76b8a 0%, #ee5a6f 100%);
}

/* Avatar Circle */
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.7rem;
}

/* Custom Badges */
.badge-success-custom {
    background-color: rgba(29, 209, 161, 0.1);
    color: #1dd1a1;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
}

.badge-info-custom {
    background-color: rgba(84, 160, 255, 0.1);
    color: #54a0ff;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
}

.badge-danger-custom {
    background-color: rgba(247, 107, 138, 0.1);
    color: #f76b8a;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
}

.badge-warning-custom {
    background-color: rgba(254, 202, 87, 0.15);
    color: #f8b500;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
}

/* Table Hover Effect */
.transaction-row {
    transition: background-color 0.2s ease;
}

.transaction-row:hover {
    background-color: rgba(102, 126, 234, 0.03);
}

/* Card Hover */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}

/* Breadcrumb Styling */
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

/* Print Styles */
@media print {
    .btn, .breadcrumb, .form-control, .form-select {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }
}
</style>
@endsection