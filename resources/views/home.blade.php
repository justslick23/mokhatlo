@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Enhanced Header -->
    <div class="page-header mb-4">
        <div>
            <h2 class="mb-2 fw-bold">Dashboard</h2>
            <p class="text-muted mb-0">Overview of your societies, funds and loans</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary">
                <i class="fas fa-filter me-2"></i>Filter
            </button>
            <button class="btn btn-outline-primary">
                <i class="fas fa-download me-2"></i>Export
            </button>
            <a href="{{ route('societies.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Society
            </a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card stat-primary">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Total Members</span>
                    <h3 class="stat-value">{{ $stats['total_members'] }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">
                            <i class="fas fa-building me-1"></i>
                            {{ $stats['total_societies'] }} {{ Str::plural('society', $stats['total_societies']) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card stat-success">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Total Contributions</span>
                    <h3 class="stat-value">
                        M {{ number_format($stats['total_contributions'], 2) }}
                    </h3>
                    <div class="stat-footer">
                        <span class="{{ $stats['contributions_change'] >= 0 ? 'text-success' : 'text-danger' }}">
                            <i class="fas fa-{{ $stats['contributions_change'] >= 0 ? 'arrow-up' : 'arrow-down' }} me-1"></i>
                            {{ $stats['contributions_change'] >= 0 ? '+' : '' }}{{ number_format($stats['contributions_change'], 1) }}% this month
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card stat-warning">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Active Loans</span>
                    <h3 class="stat-value">{{ $stats['active_loans'] }}</h3>
                    <div class="stat-footer">
                        @if($stats['overdue_loans'] > 0)
                            <span class="text-danger">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                {{ $stats['overdue_loans'] }} overdue
                            </span>
                        @else
                            <span class="text-success">
                                <i class="fas fa-check-circle me-1"></i>
                                All current
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card stat-info">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Available Balance</span>
                    <h3 class="stat-value">
                        M {{ number_format($stats['available_balance'], 2) }}
                    </h3>
                    <div class="stat-footer">
                        <span class="text-muted">
                            <i class="fas fa-sync-alt me-1"></i>
                            {{ $stats['active_cycles'] }} active {{ Str::plural('cycle', $stats['active_cycles']) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-semibold">
                            <i class="fas fa-chart-line text-primary me-2"></i>
                            Monthly Contributions
                        </h5>
                        <select class="form-select form-select-sm" style="width: auto;">
                            <option>Last 6 months</option>
                            <option>Last 12 months</option>
                            <option>This year</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="fas fa-chart-pie text-warning me-2"></i>
                        Loan Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="loan-stats mb-4">
                        <div class="loan-stat-item">
                            <div class="loan-stat-header">
                                <span class="stat-label-sm">Repaid</span>
                                <span class="stat-value-sm text-success">{{ number_format($stats['loan_repaid_percentage'], 1) }}%</span>
                            </div>
                            <div class="progress-modern">
                                <div class="progress-bar bg-gradient-success" style="width: {{ $stats['loan_repaid_percentage'] }}%"></div>
                            </div>
                        </div>

                        <div class="loan-stat-item">
                            <div class="loan-stat-header">
                                <span class="stat-label-sm">Outstanding</span>
                                <span class="stat-value-sm text-warning">{{ number_format($stats['loan_outstanding_percentage'], 1) }}%</span>
                            </div>
                            <div class="progress-modern">
                                <div class="progress-bar bg-gradient-warning" style="width: {{ $stats['loan_outstanding_percentage'] }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="loan-summary">
                        <div class="summary-row">
                            <span>Total Issued</span>
                            <strong>M {{ number_format($stats['total_loans_issued'], 2) }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>Total Repaid</span>
                            <strong class="text-success">M {{ number_format($stats['total_repayments'], 2) }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>Outstanding</span>
                            <strong class="text-warning">M {{ number_format($stats['outstanding_balance'], 2) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-semibold">
                            <i class="fas fa-history text-info me-2"></i>
                            Recent Transactions
                        </h5>
                        <button class="btn btn-sm btn-light">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if($recentTransactions->isEmpty())
                        <div class="text-center py-5">
                            <div class="empty-illustration mb-3">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <p class="text-muted">No transactions yet</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4 py-3 border-0">Date</th>
                                        <th class="py-3 border-0">Member</th>
                                        <th class="py-3 border-0">Type</th>
                                        <th class="py-3 border-0">Society</th>
                                        <th class="py-3 border-0 text-end">Amount</th>
                                        <th class="px-4 py-3 border-0"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentTransactions as $transaction)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <span class="text-muted small">{{ \Carbon\Carbon::parse($transaction['date'])->format('d M Y') }}</span>
                                        </td>
                                        <td class="py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="member-avatar-sm">
                                                    {{ $transaction['member_initials'] }}
                                                </div>
                                                <span class="ms-2 fw-medium">{{ $transaction['member'] }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            @if($transaction['type_key'] === 'contribution')
                                                <span class="badge bg-success-subtle text-success">
                                                    <i class="fas fa-arrow-down me-1"></i>{{ $transaction['type'] }}
                                                </span>
                                            @elseif($transaction['type_key'] === 'loan_repayment')
                                                <span class="badge bg-info-subtle text-info">
                                                    <i class="fas fa-undo me-1"></i>{{ $transaction['type'] }}
                                                </span>
                                            @elseif($transaction['type_key'] === 'loan_interest')
                                                <span class="badge bg-warning-subtle text-warning">
                                                    <i class="fas fa-percentage me-1"></i>{{ $transaction['type'] }}
                                                </span>
                                            @elseif($transaction['type_key'] === 'loan_disbursement')
                                                <span class="badge bg-danger-subtle text-danger">
                                                    <i class="fas fa-hand-holding-usd me-1"></i>{{ $transaction['type'] }}
                                                </span>
                                            @elseif($transaction['type_key'] === 'penalty')
                                                <span class="badge bg-secondary-subtle text-secondary">
                                                    <i class="fas fa-exclamation-circle me-1"></i>{{ $transaction['type'] }}
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">{{ $transaction['type'] }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <span class="text-muted small">{{ $transaction['society_name'] }}</span>
                                        </td>
                                        <td class="py-3 text-end">
                                            <span class="fw-bold {{ $transaction['is_positive'] ? 'text-success' : 'text-danger' }}">
                                                {{ $transaction['is_positive'] ? '+' : '-' }}M {{ number_format($transaction['amount'], 2) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <button class="btn btn-sm btn-light">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="fas fa-users-cog text-primary me-2"></i>
                        My Societies
                    </h5>
                </div>
                <div class="card-body">
                    @if($societies->isEmpty())
                        <div class="text-center py-4">
                            <div class="empty-illustration mb-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <p class="text-muted mb-3">No societies yet</p>
                            <a href="{{ route('societies.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create Society
                            </a>
                        </div>
                    @else
                        <div class="societies-list">
                            @foreach($societies as $society)
                            <a href="{{ route('societies.dashboard', $society['id']) }}" class="society-item">
                                <div class="society-icon {{ ['purple', 'green', 'blue', 'orange'][($loop->index % 4)] }}">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="society-content">
                                    <span class="society-name">{{ $society['name'] }}</span>
                                    <span class="society-meta">
                                        {{ $society['members_count'] }} members • {{ ucfirst($society['role']) }}
                                    </span>
                                </div>
                                <i class="fas fa-chevron-right society-arrow"></i>
                            </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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
    top: 0; right: 0;
    width: 100px; height: 100px;
    border-radius: 50%;
    opacity: 0.1;
    transform: translate(30%, -30%);
}
.stat-card.stat-primary::before { background: #667eea; }
.stat-card.stat-success::before { background: #1dd1a1; }
.stat-card.stat-warning::before { background: #feca57; }
.stat-card.stat-info::before { background: #54a0ff; }
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12); }
.stat-icon {
    width: 60px; height: 60px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: white;
}
.stat-primary .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-success .stat-icon { background: linear-gradient(135deg, #1dd1a1, #10ac84); }
.stat-warning .stat-icon { background: linear-gradient(135deg, #feca57, #f8b500); }
.stat-info .stat-icon { background: linear-gradient(135deg, #54a0ff, #667eea); }
.stat-label { font-size: 0.875rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
.stat-value { font-size: 2rem; font-weight: 700; color: #111827; margin: 0.25rem 0; }
.stat-footer { font-size: 0.875rem; }
.card { border-radius: 16px; }
.card-header { border-radius: 16px 16px 0 0 !important; }
.loan-stats { display: flex; flex-direction: column; gap: 1.25rem; }
.loan-stat-item { display: flex; flex-direction: column; gap: 0.5rem; }
.loan-stat-header { display: flex; justify-content: space-between; align-items: center; }
.stat-label-sm { font-size: 0.875rem; color: #6b7280; font-weight: 600; }
.stat-value-sm { font-size: 1rem; font-weight: 700; }
.progress-modern { height: 10px; background: #f3f4f6; border-radius: 10px; overflow: hidden; }
.progress-bar { height: 100%; border-radius: 10px; transition: width 0.6s ease; }
.bg-gradient-success { background: linear-gradient(90deg, #1dd1a1, #10ac84); }
.bg-gradient-warning { background: linear-gradient(90deg, #feca57, #f8b500); }
.loan-summary { display: flex; flex-direction: column; gap: 0.75rem; padding: 1rem; background: #f9fafb; border-radius: 12px; }
.summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; }
.summary-row span { color: #6b7280; }
.table-hover tbody tr { transition: all 0.2s ease; }
.table-hover tbody tr:hover { background-color: rgba(102, 126, 234, 0.03); }
.member-avatar-sm {
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: 0.75rem; flex-shrink: 0;
}
.bg-success-subtle { background: rgba(29, 209, 161, 0.1); }
.bg-info-subtle { background: rgba(84, 160, 255, 0.1); }
.bg-warning-subtle { background: rgba(254, 202, 87, 0.1); }
.bg-danger-subtle { background: rgba(247, 107, 138, 0.1); }
.bg-secondary-subtle { background: rgba(108, 117, 125, 0.1); }
.societies-list { display: flex; flex-direction: column; gap: 0.75rem; }
.society-item {
    display: flex; align-items: center; gap: 1rem; padding: 1rem;
    background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px;
    text-decoration: none; transition: all 0.2s ease;
}
.society-item:hover { transform: translateX(4px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); background: white; }
.society-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 1rem;
}
.society-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
.society-icon.green  { background: rgba(29, 209, 161, 0.1); color: #10ac84; }
.society-icon.blue   { background: rgba(84, 160, 255, 0.1); color: #3b82f6; }
.society-icon.orange { background: rgba(254, 202, 87, 0.1); color: #f8b500; }
.society-content { flex: 1; display: flex; flex-direction: column; gap: 0.25rem; }
.society-name { font-weight: 600; color: #111827; font-size: 0.938rem; }
.society-meta { font-size: 0.813rem; color: #6b7280; }
.society-arrow { font-size: 0.875rem; color: #9ca3af; }
.empty-illustration { font-size: 3rem; color: #e5e7eb; }
.btn { border-radius: 8px; font-weight: 600; transition: all 0.2s ease; }
.btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); border: none; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
.btn-outline-primary { border-color: #667eea; color: #667eea; }
.btn-outline-primary:hover { background: #667eea; border-color: #667eea; color: white; }
.btn-light { background: white; border: 1px solid #e5e7eb; color: #6b7280; }
.btn-light:hover { background: #f9fafb; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json(array_column($monthlyData, 'month')),
                datasets: [{
                    label: 'Contributions',
                    data: @json(array_column($monthlyData, 'amount')),
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderColor: '#667eea',
                    borderWidth: 3,
                    pointRadius: 5,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        padding: 12,
                        borderRadius: 8,
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 14 },
                        callbacks: {
                            label: function(context) {
                                return 'M ' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [3, 3], color: '#e5e7eb', drawBorder: false },
                        ticks: {
                            font: { size: 12 }, color: '#6b7280',
                            callback: function(value) { return 'M ' + value; }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 12 }, color: '#6b7280' }
                    }
                }
            }
        });
    }
});
</script>
@endsection