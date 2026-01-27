@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Enhanced Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h2 class="mb-2 fw-bold">My Societies</h2>
                <p class="text-muted mb-0">Manage and view all your societies in one place</p>
            </div>
            <a href="{{ route('societies.create') }}" class="btn btn-primary btn-lg">
                <i class="fas fa-plus me-2"></i>Create Society
            </a>
        </div>

        <!-- View Toggle & Search -->
        @if(!$societies->isEmpty())
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary active">
                    <i class="fas fa-th-large me-1"></i>Grid
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-list me-1"></i>List
                </button>
            </div>
            <div class="d-flex gap-2">
                <div class="input-group" style="max-width: 300px;">
                    <span class="input-group-text bg-white">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" placeholder="Search societies...">
                </div>
                <select class="form-select form-select-sm" style="width: auto;">
                    <option>Sort by: Recent</option>
                    <option>Sort by: Name</option>
                    <option>Sort by: Members</option>
                </select>
            </div>
        </div>
        @endif
    </div>

    @if($societies->isEmpty())
        <!-- Enhanced Empty State -->
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="empty-illustration mb-4">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="mb-3">No Societies Yet</h4>
                <p class="text-muted mb-4 mx-auto" style="max-width: 500px;">
                    You haven't joined or created any societies yet. Create your first society to get started with managing contributions, loans, and members.
                </p>
                <a href="{{ route('societies.create') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Create Your First Society
                </a>
            </div>
        </div>
    @else
        <!-- Quick Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-primary">
                    <div class="stat-icon-wrapper">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-details">
                        <span class="stat-label">Total Societies</span>
                        <h3 class="stat-value">{{ $societies->count() }}</h3>
                        <div class="stat-footer">
                            <span class="text-muted">Active memberships</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-success">
                    <div class="stat-icon-wrapper">
                        <div class="stat-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                    </div>
                    <div class="stat-details">
                        <span class="stat-label">Total Members</span>
                        <h3 class="stat-value">{{ $societies->sum('members_count') }}</h3>
                        <div class="stat-footer">
                            <span class="text-muted">Across all societies</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-warning">
                    <div class="stat-icon-wrapper">
                        <div class="stat-icon">
                            <i class="fas fa-crown"></i>
                        </div>
                    </div>
                    <div class="stat-details">
                        <span class="stat-label">Admin Roles</span>
                        <h3 class="stat-value">{{ $societies->where('pivot.role', 'admin')->count() }}</h3>
                        <div class="stat-footer">
                            <span class="text-muted">Societies you manage</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card stat-info">
                    <div class="stat-icon-wrapper">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                    </div>
                    <div class="stat-details">
                        <span class="stat-label">Recently Joined</span>
                        <h3 class="stat-value">{{ $societies->where('created_at', '>=', now()->subDays(30))->count() }}</h3>
                        <div class="stat-footer">
                            <span class="text-muted">Last 30 days</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Societies Grid -->
        <div class="row g-4">
            @foreach($societies as $society)
            <div class="col-xl-4 col-lg-6 col-md-6">
                <div class="society-card">
                    <!-- Card Header -->
                    <div class="society-card-header">
                        <div class="society-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('societies.dashboard', $society) }}"><i class="fas fa-chart-line me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-users me-2"></i>Manage Members</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-sign-out-alt me-2"></i>Leave Society</a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Society Name -->
                    <div class="society-info">
                        <h4 class="society-name">{{ $society->name }}</h4>
                        <p class="society-description text-muted">
                            {{ Str::limit($society->description ?? 'A collaborative savings and loans society', 80) }}
                        </p>
                    </div>

                    <!-- Society Stats -->
                    <div class="society-stats">
                        <div class="stat-item">
                            <div class="stat-icon-small bg-primary-subtle">
                                <i class="fas fa-user-friends text-primary"></i>
                            </div>
                            <div>
                                <div class="stat-number">{{ $society->members_count }}</div>
                                <div class="stat-text">{{ Str::plural('Member', $society->members_count) }}</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon-small bg-success-subtle">
                                <i class="fas fa-calendar-check text-success"></i>
                            </div>
                            <div>
                                <div class="stat-number">{{ $society->created_at->diffForHumans(null, true) }}</div>
                                <div class="stat-text">Active</div>
                            </div>
                        </div>
                    </div>

                    <!-- Role Badge -->
                    <div class="society-role">
                        @if(isset($society->pivot) && $society->pivot->role === 'admin')
                            <span class="role-badge role-admin">
                                <i class="fas fa-shield-alt me-1"></i>Administrator
                            </span>
                        @elseif(isset($society->pivot) && $society->pivot->role === 'chairman')
                            <span class="role-badge role-chairman">
                                <i class="fas fa-crown me-1"></i>Chairman
                            </span>
                        @else
                            <span class="role-badge role-member">
                                <i class="fas fa-user me-1"></i>Member
                            </span>
                        @endif
                    </div>

                    <!-- Quick Info -->
                    <div class="society-meta">
                        <div class="meta-row">
                            <span class="meta-label">
                                <i class="fas fa-calendar text-muted me-2"></i>Created
                            </span>
                            <span class="meta-value">{{ $society->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">
                                <i class="fas fa-shield-check text-success me-2"></i>Status
                            </span>
                            <span class="meta-value">
                                <span class="badge bg-success-subtle text-success">Active</span>
                            </span>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="society-actions">
                        <a href="{{ route('societies.dashboard', $society) }}" class="btn btn-primary w-100">
                            <i class="fas fa-chart-line me-2"></i>View Dashboard
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if(method_exists($societies, 'links'))
            <div class="mt-4">
                {{ $societies->links() }}
            </div>
        @endif
    @endif
</div>

<style>
/* Stat Cards */
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

/* Society Cards */
.society-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.society-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
}

.society-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.society-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    color: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.society-info {
    margin-bottom: 1.5rem;
}

.society-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.5rem;
}

.society-description {
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 0;
}

.society-stats {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.stat-item {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.stat-icon-small {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.bg-primary-subtle { background-color: rgba(102, 126, 234, 0.1); }
.bg-success-subtle { background-color: rgba(29, 209, 161, 0.1); }

.stat-number {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    line-height: 1.2;
}

.stat-text {
    font-size: 0.75rem;
    color: #6b7280;
}

.society-role {
    margin-bottom: 1rem;
}

.role-badge {
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

.role-admin {
    background: #dbeafe;
    color: #1e40af;
}

.role-chairman {
    background: #fef3c7;
    color: #92400e;
}

.role-member {
    background: #f3f4f6;
    color: #374151;
}

.society-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
}

.meta-label {
    color: #6b7280;
    display: flex;
    align-items: center;
}

.meta-value {
    color: #111827;
    font-weight: 500;
}

.society-actions {
    margin-top: auto;
}

/* Empty State */
.empty-illustration {
    font-size: 5rem;
    color: #e5e7eb;
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