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
                    <li class="breadcrumb-item active">Member Financial Overview</li>
                </ol>
            </nav>
            <h2 class="mb-1">Member Financial Overview</h2>
            <p class="text-muted mb-0">Comprehensive view of member contributions, loans and interest</p>
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

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-primary me-3">
                            <i class="fas fa-users text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Total Members</h6>
                            <h4 class="mb-0 fw-bold">{{ $members->count() }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-success me-3">
                            <i class="fas fa-wallet text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Total Contributions</h6>
                            <h4 class="mb-0 fw-bold">
                                M {{ number_format($members->sum(function($member) use ($society) {
                                    return $society->transactions()
                                        ->where('member_id', $member->id)
                                        ->where('type', 'contribution')
                                        ->sum('amount');
                                }), 2) }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-danger me-3">
                            <i class="fas fa-hand-holding-usd text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Total Outstanding</h6>
                            <h4 class="mb-0 fw-bold">
                                M {{ number_format($members->sum(function($member) use ($society) {
                                    return $society->loans()
                                        ->where('member_id', $member->id)
                                        ->where('status', '!=', 'repaid')
                                        ->sum('outstanding_balance');
                                }), 2) }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Financial Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold">Member Financial Details</h5>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" placeholder="Search members..." style="width: 200px;">
                    <select class="form-select form-select-sm" style="width: auto;">
                        <option>All Status</option>
                        <option>Active</option>
                        <option>Inactive</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if($members->isEmpty())
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-users text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-muted">No Members Found</h5>
                    <p class="text-muted mb-0">Add members to see their financial overview</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3 text-muted small text-uppercase">Member</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Contributions</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Interest Paid</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Active Loans</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Outstanding</th>
                                <th class="py-3 text-muted small text-uppercase text-end">Net Position</th>
                                <th class="py-3 text-muted small text-uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($members as $member)
                            @php
                                $totalContributions = $society->transactions()
                                    ->where('member_id', $member->id)
                                    ->where('type', 'contribution')
                                    ->sum('amount');

                                $totalInterestPaid = $society->transactions()
                                    ->where('member_id', $member->id)
                                    ->where('type', 'loan_interest')
                                    ->sum('amount');

                                $activeLoans = $society->loans()
                                    ->where('member_id', $member->id)
                                    ->where('status', '!=', 'repaid')
                                    ->count();

                                $outstandingBalance = $society->loans()
                                    ->where('member_id', $member->id)
                                    ->where('status', '!=', 'repaid')
                                    ->sum('outstanding_balance');

                                $netPosition = $totalContributions - $outstandingBalance;
                            @endphp
                            <tr class="member-row">
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            {{ strtoupper(substr($member->user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $member->user->name }}</div>
                                            <small class="text-muted">{{ $member->user->email }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="fw-semibold text-success">M {{ number_format($totalContributions, 2) }}</span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="fw-semibold {{ $totalInterestPaid > 0 ? 'text-warning' : 'text-muted' }}">
                                        M {{ number_format($totalInterestPaid, 2) }}
                                    </span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="badge bg-light text-dark">{{ $activeLoans }}</span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="fw-semibold text-danger">M {{ number_format($outstandingBalance, 2) }}</span>
                                </td>
                                <td class="py-3 text-end">
                                    <span class="fw-bold {{ $netPosition >= 0 ? 'text-success' : 'text-danger' }}">
                                        M {{ number_format($netPosition, 2) }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    @if($member->status === 'active')
                                        <span class="badge badge-success-custom">
                                            <i class="fas fa-check-circle me-1"></i>Active
                                        </span>
                                    @else
                                        <span class="badge badge-secondary-custom">
                                            <i class="fas fa-pause-circle me-1"></i>{{ ucfirst($member->status) }}
                                        </span>
                                    @endif
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

<style>
.icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
.bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #1dd1a1 0%, #10ac84 100%); }
.bg-gradient-danger  { background: linear-gradient(135deg, #f76b8a 0%, #ee5a6f 100%); }
.avatar-circle { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; }
.badge-success-custom  { background-color: rgba(29, 209, 161, 0.1); color: #1dd1a1; font-weight: 600; padding: 0.375rem 0.75rem; }
.badge-secondary-custom { background-color: rgba(108, 117, 125, 0.1); color: #6c757d; font-weight: 600; padding: 0.375rem 0.75rem; }
.member-row { transition: background-color 0.2s ease; }
.member-row:hover { background-color: rgba(102, 126, 234, 0.03); }
.breadcrumb { background: transparent; padding: 0; margin: 0; }
.breadcrumb-item a { color: #667eea; text-decoration: none; }
.breadcrumb-item a:hover { text-decoration: underline; }
@media print {
    .btn, .breadcrumb, .form-control, .form-select { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
}
</style>
@endsection