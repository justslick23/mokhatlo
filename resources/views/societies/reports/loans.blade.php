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
                    <li class="breadcrumb-item active">Loan Register</li>
                </ol>
            </nav>
            <h2 class="mb-1">Loan Register</h2>
            <p class="text-muted mb-0">Complete loan ledger with financial details</p>
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

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-primary me-3">
                            <i class="fas fa-list-alt text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Total Loans</h6>
                            <h4 class="mb-0 fw-bold">{{ $loans->count() }}</h4>
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
                            <i class="fas fa-coins text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Total Principal</h6>
                            <h4 class="mb-0 fw-bold">M {{ number_format($loans->sum('principal'), 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-warning me-3">
                            <i class="fas fa-percentage text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Interest (Borrower)</h6>
                            <h4 class="mb-0 fw-bold">M {{ number_format($loans->sum('interest'), 2) }}</h4>
                            <small class="text-muted">
                                M {{ number_format($loans->sum('interest_paid'), 2) }} collected
                            </small>
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
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Penalties (Shared)</h6>
                            <h4 class="mb-0 fw-bold">M {{ number_format($loans->sum('penalty_amount'), 2) }}</h4>
                            <small class="text-muted">For distribution</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="alert alert-info border-0 mb-4" role="alert">
        <div class="d-flex align-items-start">
            <i class="fas fa-info-circle me-3 mt-1"></i>
            <div>
                <strong>Note:</strong> Interest earned on loans belongs to the borrower and is tracked separately.
                Penalty fees collected are shared amongst all members at year-end settlement.
            </div>
        </div>
    </div>

    <!-- Loan Register Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold">Loan Register</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" style="width: auto;">
                        <option>All Status</option>
                        <option>Active</option>
                        <option>Repaid</option>
                        <option>Overdue</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if($loans->isEmpty())
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-file-invoice text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-muted">No Loan Records</h5>
                    <p class="text-muted mb-0">The loan register is currently empty</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3 text-muted small text-uppercase">Loan ID</th>
                                <th class="py-3 text-muted small text-uppercase">Member</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Principal</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Interest (Borrower)</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Interest Paid</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Penalty (Shared)</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Total Due</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Repaid</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Outstanding</th>
                                <th class="py-3 text-muted small text-uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loans as $loan)
                            <tr class="loan-row">
                                <td class="px-4 py-3">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-hashtag me-1"></i>{{ $loan->id }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            {{ strtoupper(substr($loan->member->user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $loan->member->user->name }}</div>
                                            <small class="text-muted">{{ $loan->issue_date->format('d M Y') }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="fw-semibold">M {{ number_format($loan->principal, 2) }}</span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="text-warning fw-semibold" title="Goes to borrower">
                                        M {{ number_format($loan->interest, 2) }}
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    @php $interestRemaining = $loan->interest - $loan->interest_paid; @endphp
                                    <span class="fw-semibold {{ $loan->interest_paid >= $loan->interest ? 'text-success' : 'text-warning' }}">
                                        M {{ number_format($loan->interest_paid, 2) }}
                                    </span>
                                    @if($interestRemaining > 0)
                                        <br><small class="text-muted">M {{ number_format($interestRemaining, 2) }} remaining</small>
                                    @endif
                                </td>
                                <td class="py-3 text-end">
                                    <span class="text-danger fw-semibold" title="Shared among members">
                                        M {{ number_format($loan->penalty_amount, 2) }}
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="fw-bold">
                                        M {{ number_format($loan->principal + $loan->interest + $loan->penalty_amount, 2) }}
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="text-success fw-semibold">M {{ number_format($loan->amount_repaid, 2) }}</span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="text-danger fw-bold">M {{ number_format($loan->outstanding_balance, 2) }}</span>
                                </td>
                                <td class="py-3">
                                    @if($loan->status === 'repaid')
                                        <span class="badge badge-success-custom">
                                            <i class="fas fa-check-circle me-1"></i>Repaid
                                        </span>
                                    @elseif($loan->status === 'active')
                                        <span class="badge badge-info-custom">
                                            <i class="fas fa-clock me-1"></i>Active
                                        </span>
                                    @else
                                        <span class="badge badge-warning-custom">
                                            <i class="fas fa-exclamation-circle me-1"></i>{{ ucfirst($loan->status) }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light">
                            <tr class="fw-bold">
                                <td colspan="2" class="px-4 py-3">TOTALS</td>
                                <td class="py-3 text-end">M {{ number_format($loans->sum('principal'), 2) }}</td>
                                <td class="py-3 text-end">M {{ number_format($loans->sum('interest'), 2) }}</td>
                                <td class="py-3 text-end text-warning">M {{ number_format($loans->sum('interest_paid'), 2) }}</td>
                                <td class="py-3 text-end">M {{ number_format($loans->sum('penalty_amount'), 2) }}</td>
                                <td class="py-3 text-end">M {{ number_format($loans->sum('principal') + $loans->sum('interest') + $loans->sum('penalty_amount'), 2) }}</td>
                                <td class="py-3 text-end">M {{ number_format($loans->sum('amount_repaid'), 2) }}</td>
                                <td class="py-3 text-end text-danger">M {{ number_format($loans->sum('outstanding_balance'), 2) }}</td>
                                <td class="py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
.icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
.bg-gradient-primary  { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success  { background: linear-gradient(135deg, #1dd1a1 0%, #10ac84 100%); }
.bg-gradient-warning  { background: linear-gradient(135deg, #feca57 0%, #f8b500 100%); }
.bg-gradient-danger   { background: linear-gradient(135deg, #f76b8a 0%, #ee5a6f 100%); }
.avatar-circle { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem; }
.badge-success-custom { background-color: rgba(29, 209, 161, 0.1); color: #1dd1a1; font-weight: 600; padding: 0.375rem 0.75rem; }
.badge-info-custom    { background-color: rgba(84, 160, 255, 0.1); color: #54a0ff; font-weight: 600; padding: 0.375rem 0.75rem; }
.badge-warning-custom { background-color: rgba(254, 202, 87, 0.15); color: #f8b500; font-weight: 600; padding: 0.375rem 0.75rem; }
.loan-row { transition: background-color 0.2s ease; }
.loan-row:hover { background-color: rgba(102, 126, 234, 0.03); }
.breadcrumb { background: transparent; padding: 0; margin: 0; }
.breadcrumb-item a { color: #667eea; text-decoration: none; }
.breadcrumb-item a:hover { text-decoration: underline; }
@media print {
    .btn, .breadcrumb, .form-select, .alert { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
}
</style>
@endsection