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
                    <li class="breadcrumb-item active">Contributions</li>
                </ol>
            </nav>
            <h2 class="mb-1">Contributions Management</h2>
            <p class="text-muted mb-0">Track and manage member contributions</p>
        </div>
        <a href="{{ route('societies.contributions.create', $society) }}" class="btn btn-primary btn-lg shadow-sm">
            <i class="fas fa-plus-circle me-2"></i>Add Contribution
        </a>
    </div>

    <!-- Stats Overview -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-success me-3">
                            <i class="fas fa-wallet text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Total Contributions</h6>
                            <h4 class="mb-0 fw-bold">M {{ number_format($contributions->sum('amount'), 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-gradient-primary me-3">
                            <i class="fas fa-calendar-month text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">This Month</h6>
                            <h4 class="mb-0 fw-bold">M {{ number_format($contributions->where('created_at', '>=', now()->startOfMonth())->sum('amount'), 2) }}</h4>
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
                            <i class="fas fa-users text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Contributing Members</h6>
                            <h4 class="mb-0 fw-bold">{{ $contributions->unique('member_id')->count() }}</h4>
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
                            <i class="fas fa-chart-line text-white"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1 small">Avg. Contribution</h6>
                            <h4 class="mb-0 fw-bold">M {{ $contributions->count() > 0 ? number_format($contributions->avg('amount'), 2) : '0.00' }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contributions Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold">All Contributions</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <button class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if($contributions->isEmpty())
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-wallet text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-muted">No Contributions Yet</h5>
                    <p class="text-muted mb-3">Start by recording your first contribution</p>
                    <a href="{{ route('societies.contributions.create', $society) }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>Add First Contribution
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3 text-muted small text-uppercase">Member</th>
                                <th class="py-3 text-muted small text-uppercase">Amount</th>
                                <th class="py-3 text-muted small text-uppercase">Type</th>
                                <th class="py-3 text-muted small text-uppercase">Payment Method</th>
                                <th class="py-3 text-muted small text-uppercase">Date</th>
                                <th class="py-3 text-muted small text-uppercase">Status</th>
                                <th class="px-4 py-3 text-muted small text-uppercase text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contributions as $contribution)
                            <tr class="contribution-row">
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3">
                                            {{ strtoupper(substr($contribution->member->user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $contribution->member->user->name }}</div>
                                            <small class="text-muted">{{ $contribution->member->user->email }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="fw-bold text-success">M {{ number_format($contribution->amount, 2) }}</span>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-light text-dark">{{ ucfirst($contribution->type ?? 'Regular') }}</span>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        @if(($contribution->payment_method ?? 'cash') === 'cash')
                                            <i class="fas fa-money-bill-wave text-success me-2"></i>
                                        @elseif(($contribution->payment_method ?? 'cash') === 'bank')
                                            <i class="fas fa-university text-primary me-2"></i>
                                        @else
                                            <i class="fas fa-mobile-alt text-info me-2"></i>
                                        @endif
                                        <span>{{ ucfirst($contribution->payment_method ?? 'Cash') }}</span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-calendar-alt text-muted me-2 small"></i>
                                        <span>{{ $contribution->created_at->format('d M Y') }}</span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge badge-success-custom">
                                        <i class="fas fa-check-circle me-1"></i>Confirmed
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <a href="{{ route('societies.contributions.show', [$society, $contribution]) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if(method_exists($contributions, 'links'))
                    <div class="card-footer bg-white border-top">
                        {{ $contributions->links() }}
                    </div>
                @endif
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

.bg-gradient-warning {
    background: linear-gradient(135deg, #feca57 0%, #f8b500 100%);
}

/* Avatar Circle */
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Custom Badges */
.badge-success-custom {
    background-color: rgba(29, 209, 161, 0.1);
    color: #1dd1a1;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
}

/* Table Hover Effect */
.contribution-row {
    transition: background-color 0.2s ease;
}

.contribution-row:hover {
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
</style>
@endsection