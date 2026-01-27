@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">{{ $society->name }}</h2>
            <p class="text-muted mb-0">Society Management Dashboard</p>
        </div>
        <a href="{{ route('societies.settings', $society) }}" class="btn btn-outline-primary">
            <i class="fas fa-cog"></i> Settings
        </a>
    </div>

    <!-- Stats Cards Grid -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="icon-box bg-gradient-blue">
                            <i class="fas fa-users text-white"></i>
                        </div>
                        <span class="badge badge-success-soft">Active</span>
                    </div>
                    <h6 class="text-muted text-uppercase small mb-2">Active Members</h6>
                    <h3 class="mb-0 fw-bold text-dark">{{ $stats['members'] }}</h3>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="icon-box bg-gradient-green">
                            <i class="fas fa-wallet text-white"></i>
                        </div>
                        <span class="badge badge-primary-soft">Total</span>
                    </div>
                    <h6 class="text-muted text-uppercase small mb-2">Total Contributions</h6>
                    <h3 class="mb-0 fw-bold text-dark">M {{ number_format($stats['total_contributions'], 2) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="icon-box bg-gradient-orange">
                            <i class="fas fa-hand-holding-usd text-white"></i>
                        </div>
                        <span class="badge badge-warning-soft">Active</span>
                    </div>
                    <h6 class="text-muted text-uppercase small mb-2">Active Loans</h6>
                    <h3 class="mb-0 fw-bold text-dark">{{ $stats['active_loans'] }}</h3>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="icon-box bg-gradient-cyan">
                            <i class="fas fa-sync-alt text-white"></i>
                        </div>
                        <span class="badge badge-info-soft">Received</span>
                    </div>
                    <h6 class="text-muted text-uppercase small mb-2">Total Repayments</h6>
                    <h3 class="mb-0 fw-bold text-dark">M {{ number_format($stats['total_repayments'], 2) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 fw-semibold">Quick Actions</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <a href="{{ route('societies.members.index', $society) }}" 
                       class="action-card action-card-blue">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="action-title">Manage Members</span>
                        <small class="action-subtitle">View & add members</small>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <a href="{{ route('societies.contributions.index', $society) }}" 
                       class="action-card action-card-green">
                        <div class="action-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <span class="action-title">Contributions</span>
                        <small class="action-subtitle">Track contributions</small>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <a href="{{ route('societies.loans.index', $society) }}" 
                       class="action-card action-card-orange">
                        <div class="action-icon">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <span class="action-title">Loans</span>
                        <small class="action-subtitle">Manage loans</small>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <a href="{{ route('societies.repayments.index', $society) }}" 
                       class="action-card action-card-cyan">
                        <div class="action-icon">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <span class="action-title">Repayments</span>
                        <small class="action-subtitle">Track repayments</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Stat Cards */
.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1) !important;
}

.icon-box {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

/* Gradient Backgrounds */
.bg-gradient-blue {
    background: linear-gradient(135deg, #5f76e8 0%, #4c63d2 100%);
}

.bg-gradient-green {
    background: linear-gradient(135deg, #22ca80 0%, #1ab06a 100%);
}

.bg-gradient-orange {
    background: linear-gradient(135deg, #fb8c00 0%, #e67c00 100%);
}

.bg-gradient-cyan {
    background: linear-gradient(135deg, #01caf1 0%, #00b4d8 100%);
}

/* Badges */
.badge-success-soft {
    background-color: rgba(34, 202, 128, 0.1);
    color: #22ca80;
    font-weight: 600;
}

.badge-primary-soft {
    background-color: rgba(95, 118, 232, 0.1);
    color: #5f76e8;
    font-weight: 600;
}

.badge-warning-soft {
    background-color: rgba(253, 193, 106, 0.15);
    color: #fb8c00;
    font-weight: 600;
}

.badge-info-soft {
    background-color: rgba(1, 202, 241, 0.1);
    color: #01caf1;
    font-weight: 600;
}

/* Action Cards */
.action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem 1rem;
    border: 2px solid #e8eaec;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s ease;
    background: white;
    text-align: center;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
    text-decoration: none;
}

.action-icon {
    width: 70px;
    height: 70px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.action-title {
    font-weight: 600;
    font-size: 1rem;
    color: #1c2d41;
    display: block;
    margin-bottom: 0.25rem;
}

.action-subtitle {
    color: #6c757d;
    font-size: 0.875rem;
}

/* Blue Theme */
.action-card-blue:hover {
    border-color: #5f76e8;
}

.action-card-blue .action-icon {
    background: linear-gradient(135deg, rgba(95, 118, 232, 0.1) 0%, rgba(95, 118, 232, 0.15) 100%);
    color: #5f76e8;
}

.action-card-blue:hover .action-icon {
    background: linear-gradient(135deg, #5f76e8 0%, #4c63d2 100%);
    color: white;
}

/* Green Theme */
.action-card-green:hover {
    border-color: #22ca80;
}

.action-card-green .action-icon {
    background: linear-gradient(135deg, rgba(34, 202, 128, 0.1) 0%, rgba(34, 202, 128, 0.15) 100%);
    color: #22ca80;
}

.action-card-green:hover .action-icon {
    background: linear-gradient(135deg, #22ca80 0%, #1ab06a 100%);
    color: white;
}

/* Orange Theme */
.action-card-orange:hover {
    border-color: #fb8c00;
}

.action-card-orange .action-icon {
    background: linear-gradient(135deg, rgba(251, 140, 0, 0.1) 0%, rgba(251, 140, 0, 0.15) 100%);
    color: #fb8c00;
}

.action-card-orange:hover .action-icon {
    background: linear-gradient(135deg, #fb8c00 0%, #e67c00 100%);
    color: white;
}

/* Cyan Theme */
.action-card-cyan:hover {
    border-color: #01caf1;
}

.action-card-cyan .action-icon {
    background: linear-gradient(135deg, rgba(1, 202, 241, 0.1) 0%, rgba(1, 202, 241, 0.15) 100%);
    color: #01caf1;
}

.action-card-cyan:hover .action-icon {
    background: linear-gradient(135deg, #01caf1 0%, #00b4d8 100%);
    color: white;
}
</style>
@endsection