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
                    <li class="breadcrumb-item active">Member Statement</li>
                </ol>
            </nav>
            <h2 class="mb-1">{{ $member->user->name }} - Member Statement</h2>
            <p class="text-muted mb-0">{{ $cycle->name }} | Role: <span class="badge bg-primary">{{ ucfirst($member->role) }}</span></p>
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

    <!-- Member Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase small mb-2">Total Contributed</h6>
                            <h3 class="mb-0 fw-bold text-success">
                                M {{ number_format($transactions->where('type', 'contribution')->sum('amount'), 2) }}
                            </h3>
                        </div>
                        <div class="icon-box bg-success bg-opacity-10">
                            <i class="fas fa-wallet text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase small mb-2">Total Borrowed</h6>
                            <h3 class="mb-0 fw-bold text-warning">
                                M {{ number_format($loans->sum('principal'), 2) }}
                            </h3>
                        </div>
                        <div class="icon-box bg-warning bg-opacity-10">
                            <i class="fas fa-hand-holding-usd text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase small mb-2">Interest Paid</h6>
                            <h3 class="mb-0 fw-bold text-warning">
                                M {{ number_format($transactions->where('type', 'loan_interest')->sum('amount'), 2) }}
                            </h3>
                            <small class="text-muted">
                                of M {{ number_format($loans->sum('interest'), 2) }} total
                            </small>
                        </div>
                        <div class="icon-box bg-warning bg-opacity-10">
                            <i class="fas fa-percentage text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase small mb-2">Outstanding Balance</h6>
                            <h3 class="mb-0 fw-bold text-danger">
                                M {{ number_format($loans->where('status', '!=', 'repaid')->sum('outstanding_balance'), 2) }}
                            </h3>
                        </div>
                        <div class="icon-box bg-danger bg-opacity-10">
                            <i class="fas fa-exclamation-triangle text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Loans Section -->
    @if($loans->count() > 0)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-semibold">Loans This Cycle</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Loan ID</th>
                            <th>Principal</th>
                            <th>Interest</th>
                            <th>Interest Paid</th>
                            <th>Total Due</th>
                            <th>Repaid</th>
                            <th>Outstanding</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loans as $loan)
                        <tr>
                            <td class="fw-semibold">#{{ $loan->id }}</td>
                            <td>M {{ number_format($loan->principal, 2) }}</td>
                            <td>M {{ number_format($loan->interest, 2) }}</td>
                            <td class="{{ $loan->interest_paid >= $loan->interest ? 'text-success' : 'text-warning' }} fw-semibold">
                                M {{ number_format($loan->interest_paid, 2) }}
                            </td>
                            <td class="fw-bold">M {{ number_format($loan->total_amount, 2) }}</td>
                            <td class="text-success">M {{ number_format($loan->amount_repaid, 2) }}</td>
                            <td class="fw-bold text-danger">M {{ number_format($loan->outstanding_balance, 2) }}</td>
                            <td>{{ $loan->due_date->format('M d, Y') }}</td>
                            <td>
                                @if($loan->status === 'active')
                                    <span class="badge bg-warning">Active</span>
                                @elseif($loan->status === 'repaid')
                                    <span class="badge bg-success">Repaid</span>
                                @elseif($loan->status === 'overdue')
                                    <span class="badge bg-danger">Overdue</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($loan->status) }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-inbox me-2"></i>No loans this cycle
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Transactions History -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-semibold">Transaction History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                        <tr>
                            <td class="fw-semibold">{{ $transaction->transaction_date->format('M d, Y') }}</td>
                            <td>
                                @if($transaction->type === 'contribution')
                                    <span class="badge bg-success">Contribution</span>
                                @elseif($transaction->type === 'loan_repayment')
                                    <span class="badge bg-info">Loan Repayment</span>
                                @elseif($transaction->type === 'loan_interest')
                                    <span class="badge bg-warning text-dark">Interest Paid</span>
                                @elseif($transaction->type === 'penalty')
                                    <span class="badge bg-danger">Penalty</span>
                                @elseif($transaction->type === 'loan_disbursement')
                                    <span class="badge bg-secondary">Loan Issued</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $transaction->type)) }}</span>
                                @endif
                            </td>
                            <td class="fw-bold">
                                @if($transaction->type === 'contribution')
                                    <span class="text-success">+M {{ number_format($transaction->amount, 2) }}</span>
                                @elseif($transaction->type === 'loan_repayment')
                                    <span class="text-info">+M {{ number_format($transaction->amount, 2) }}</span>
                                @elseif($transaction->type === 'loan_interest')
                                    <span class="text-warning">M {{ number_format($transaction->amount, 2) }}</span>
                                @elseif($transaction->type === 'penalty')
                                    <span class="text-danger">-M {{ number_format($transaction->amount, 2) }}</span>
                                @elseif($transaction->type === 'loan_disbursement')
                                    <span class="text-secondary">M {{ number_format($transaction->amount, 2) }}</span>
                                @else
                                    <span>M {{ number_format($transaction->amount, 2) }}</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $transaction->notes ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="fas fa-inbox me-2"></i>No transactions
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .breadcrumb { background: transparent; padding: 0; margin: 0; }
    .breadcrumb-item a { color: #667eea; text-decoration: none; }
    .breadcrumb-item a:hover { text-decoration: underline; }
    @media print {
        .btn, .breadcrumb { display: none !important; }
        .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; page-break-inside: avoid; }
    }
</style>
@endsection