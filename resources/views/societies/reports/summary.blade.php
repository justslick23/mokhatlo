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
                    <li class="breadcrumb-item">Reports</li>
                    <li class="breadcrumb-item active">Financial Summary</li>
                </ol>
            </nav>
            <h2 class="mb-1">Financial Summary Report</h2>
            <p class="text-muted mb-0">Complete overview of society financial status</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print
            </button>
            <button class="btn btn-primary">
                <i class="fas fa-download me-2"></i>Export PDF
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Report Period</label>
                    <select class="form-select">
                        <option>Current Cycle</option>
                        <option>Last 30 Days</option>
                        <option>Last 90 Days</option>
                        <option>Last Year</option>
                        <option>All Time</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">From Date</label>
                    <input type="date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">To Date</label>
                    <input type="date" class="form-control">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Apply Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Metrics Grid -->
    <div class="row g-4 mb-4">
        @foreach($stats as $key => $value)
        @php
            $iconMap = [
                'total_contributions' => ['icon' => 'wallet', 'gradient' => 'success'],
                'total_loans_issued' => ['icon' => 'hand-holding-usd', 'gradient' => 'orange'],
                'total_repayments' => ['icon' => 'sync-alt', 'gradient' => 'info'],
                'outstanding_balance' => ['icon' => 'exclamation-triangle', 'gradient' => 'danger'],
                'available_fund' => ['icon' => 'piggy-bank', 'gradient' => 'primary'],
                'total_interest' => ['icon' => 'percentage', 'gradient' => 'warning'],
            ];
            $config = $iconMap[$key] ?? ['icon' => 'chart-line', 'gradient' => 'primary'];
        @endphp
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 metric-card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div class="icon-box-large bg-gradient-{{ $config['gradient'] }}">
                            <i class="fas fa-{{ $config['icon'] }} text-white"></i>
                        </div>
                        <span class="badge bg-light text-muted">
                            <i class="fas fa-calendar-alt me-1"></i>Current Cycle
                        </span>
                    </div>
                    <h6 class="text-muted text-uppercase small mb-2">
                        {{ str_replace('_', ' ', ucwords($key)) }}
                    </h6>
                    <h3 class="mb-0 fw-bold">M {{ number_format($value, 2) }}</h3>
                    @if($key === 'available_fund')
                        @php
                            $percentOfTotal = $stats['total_contributions'] > 0 
                                ? ($value / $stats['total_contributions']) * 100 
                                : 0;
                        @endphp
                        <div class="mt-3">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" 
                                     style="width: {{ $percentOfTotal }}%">
                                </div>
                            </div>
                            <small class="text-muted mt-1 d-block">{{ number_format($percentOfTotal, 1) }}% of total contributions</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Financial Breakdown Charts -->
    <div class="row g-4">
        <!-- Income vs Expenses -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">Financial Flow</h5>
                </div>
                <div class="card-body">
                    <div class="financial-flow-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">
                                <i class="fas fa-arrow-down text-success me-2"></i>Money In
                            </span>
                            <span class="fw-bold text-success">
                                {{-- M {{ number_format($stats['total_contributions'] + ($stats['total_repayments'] ?? 0), 2) }} --}}
                            </span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: 100%"></div>
                        </div>
                    </div>

                    <div class="financial-flow-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">
                                <i class="fas fa-arrow-up text-danger me-2"></i>Money Out
                            </span>
                            <span class="fw-bold text-danger">
                                M {{ number_format($stats['total_loans_issued'] ?? 0, 2) }}
                            </span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            @php
                                $totalIn = $stats['total_contributions'] + ($stats['total_repayments'] ?? 0);
                                $outPercent = $totalIn > 0 ? (($stats['total_loans_issued'] ?? 0) / $totalIn) * 100 : 0;
                            @endphp
                            <div class="progress-bar bg-danger" style="width: {{ $outPercent }}%"></div>
                        </div>
                    </div>

                    <div class="financial-flow-item mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Net Position</span>
                            <span class="fw-bold text-primary fs-5">
                                M {{ number_format($stats['available_fund'], 2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Ratios -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">Key Financial Ratios</h5>
                </div>
                <div class="card-body">
                    @php
                        $loanToContribRatio = $stats['total_contributions'] > 0 
                            ? ($stats['total_loans_issued'] / $stats['total_contributions']) * 100 
                            : 0;
                        $repaymentRate = $stats['total_loans_issued'] > 0 
                            ? (($stats['total_repayments'] ?? 0) / $stats['total_loans_issued']) * 100 
                            : 0;
                        $outstandingRatio = $stats['total_loans_issued'] > 0 
                            ? ($stats['outstanding_balance'] / $stats['total_loans_issued']) * 100 
                            : 0;
                    @endphp

                    <div class="ratio-item mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Loan-to-Contribution Ratio</span>
                            <span class="fw-bold">{{ number_format($loanToContribRatio, 1) }}%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: {{ min($loanToContribRatio, 100) }}%"></div>
                        </div>
                        <small class="text-muted mt-1 d-block">Healthy range: 50-70%</small>
                    </div>

                    <div class="ratio-item mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Repayment Rate</span>
                            <span class="fw-bold text-success">{{ number_format($repaymentRate, 1) }}%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: {{ $repaymentRate }}%"></div>
                        </div>
                        <small class="text-muted mt-1 d-block">Target: 90%+</small>
                    </div>

                    <div class="ratio-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Outstanding Ratio</span>
                            <span class="fw-bold text-danger">{{ number_format($outstandingRatio, 1) }}%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-danger" style="width: {{ $outstandingRatio }}%"></div>
                        </div>
                        <small class="text-muted mt-1 d-block">Target: Below 30%</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Icon Boxes */
.icon-box-large {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
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

.bg-gradient-warning {
    background: linear-gradient(135deg, #feca57 0%, #f8b500 100%);
}

.bg-gradient-orange {
    background: linear-gradient(135deg, #f8b500 0%, #e67c00 100%);
}

.bg-gradient-danger {
    background: linear-gradient(135deg, #f76b8a 0%, #ee5a6f 100%);
}

/* Metric Card */
.metric-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.12) !important;
}

/* Progress Bars */
.progress {
    border-radius: 10px;
    background-color: #e5e7eb;
}

.progress-bar {
    border-radius: 10px;
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
        page-break-inside: avoid;
    }
    
    .row {
        page-break-inside: avoid;
    }
}
</style>
@endsection