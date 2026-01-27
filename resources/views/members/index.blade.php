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
                        <li class="breadcrumb-item active">Members</li>
                    </ol>
                </nav>
                <h2 class="mb-2 fw-bold">Member Directory</h2>
                <p class="text-muted mb-0">Manage your society members and their roles</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary">
                    <i class="fas fa-user-plus me-2"></i>Bulk Invite
                </button>
                <a href="{{ route('societies.members.create', $society) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Add Member
                </a>
            </div>
        </div>

        <!-- View Toggle -->
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
                    <input type="text" class="form-control border-start-0" placeholder="Search members...">
                </div>
                <select class="form-select form-select-sm" style="width: auto;">
                    <option>All Roles</option>
                    <option>Admins</option>
                    <option>Members</option>
                </select>
                <select class="form-select form-select-sm" style="width: auto;">
                    <option>All Status</option>
                    <option>Active</option>
                    <option>Inactive</option>
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
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Total Members</span>
                    <h3 class="stat-value">{{ $members->count() }}</h3>
                    <div class="stat-footer">
                        <span class="text-success"><i class="fas fa-arrow-up me-1"></i>12% from last month</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-success">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Active Members</span>
                    <h3 class="stat-value">{{ $members->where('status', 'active')->count() }}</h3>
                    <div class="stat-footer">
                        @php
                            $activeRate = $members->count() > 0 ? ($members->where('status', 'active')->count() / $members->count()) * 100 : 0;
                        @endphp
                        <span class="text-muted">{{ number_format($activeRate, 0) }}% of total</span>
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
                    <span class="stat-label">Administrators</span>
                    <h3 class="stat-value">{{ $members->where('role', 'admin')->count() }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">{{ $members->where('role', 'chairman')->count() }} chairman</span>
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
                    <span class="stat-label">New This Month</span>
                    <h3 class="stat-value">{{ $members->where('created_at', '>=', now()->startOfMonth())->count() }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">{{ now()->format('F Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($members->isEmpty())
        <!-- Enhanced Empty State -->
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="empty-illustration mb-4">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="mb-3">No Members Yet</h4>
                <p class="text-muted mb-4">Start building your society by adding your first member</p>
                <a href="{{ route('societies.members.create', $society) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-user-plus me-2"></i>Add First Member
                </a>
            </div>
        </div>
    @else
        <!-- Members Grid -->
        <div class="row g-4">
            @foreach($members as $member)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="member-card">
                    <!-- Card Header with Actions -->
                    <div class="member-card-header">
                        <div class="member-role-badge">
                            @if($member->role === 'chairman')
                                <i class="fas fa-star"></i>
                            @elseif($member->role === 'treasurer')
                                <i class="fas fa-wallet"></i>
                            @elseif($member->role === 'secretary')
                                <i class="fas fa-clipboard"></i>
                            @else
                                <i class="fas fa-user"></i>
                            @endif
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-eye me-2"></i>View Profile</a></li>
                                <li><a class="dropdown-item" href="{{ route('societies.members.edit', [$society, $member]) }}"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-envelope me-2"></i>Send Message</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('societies.members.destroy', [$society, $member]) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="fas fa-trash me-2"></i>Remove
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Member Avatar -->
                    <div class="member-avatar-wrapper">
                        <div class="member-avatar-large">
                            {{ strtoupper(substr($member->user->name, 0, 2)) }}
                        </div>
                        <div class="member-status-indicator {{ $member->status === 'active' ? 'status-active' : 'status-inactive' }}"></div>
                    </div>

                    <!-- Member Info -->
                    <div class="member-info">
                        <h5 class="member-name">{{ $member->user->name }}</h5>
                        <p class="member-email">{{ $member->user->email }}</p>
                        
                        <div class="member-meta">
                            <span class="meta-item">
                                <i class="fas fa-phone text-muted"></i>
                                {{ $member->user->phone ?? 'N/A' }}
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-calendar text-muted"></i>
                                Joined {{ $member->created_at->format('M Y') }}
                            </span>
                        </div>
                    </div>

                    <!-- Role Badge -->
                    <div class="member-role-tag">
                        @if($member->role === 'chairman')
                            <span class="role-badge role-chairman">
                                <i class="fas fa-crown me-1"></i>Chairman
                            </span>
                        @elseif($member->role === 'treasurer')
                            <span class="role-badge role-treasurer">
                                <i class="fas fa-wallet me-1"></i>Treasurer
                            </span>
                        @elseif($member->role === 'secretary')
                            <span class="role-badge role-secretary">
                                <i class="fas fa-clipboard me-1"></i>Secretary
                            </span>
                        @else
                            <span class="role-badge role-member">
                                <i class="fas fa-user me-1"></i>Member
                            </span>
                        @endif
                    </div>

                    <!-- Financial Summary -->
                    <div class="member-financial">
                        <div class="financial-item">
                            <span class="financial-label">Total Contributions</span>
                            <span class="financial-value text-success">
                                M {{ number_format($member->transactions->where('type','contribution')->sum('amount'), 2) }}
                            </span>
                        </div>
                        <div class="financial-item">
                            <span class="financial-label">Active Loans</span>
                            <span class="financial-value text-warning">
                                {{ $member->loans->where('status', 'active')->count() }}
                            </span>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="member-actions">
                        <button class="btn btn-sm btn-light w-100" onclick="location.href='#'">
                            <i class="fas fa-chart-line me-1"></i>View Activity
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if(method_exists($members, 'links'))
            <div class="mt-4">
                {{ $members->links() }}
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

.stat-card.stat-primary::before {
    background: #667eea;
}

.stat-card.stat-success::before {
    background: #1dd1a1;
}

.stat-card.stat-warning::before {
    background: #feca57;
}

.stat-card.stat-info::before {
    background: #54a0ff;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.stat-icon-wrapper {
    position: relative;
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

.stat-primary .stat-icon {
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.stat-success .stat-icon {
    background: linear-gradient(135deg, #1dd1a1, #10ac84);
}

.stat-warning .stat-icon {
    background: linear-gradient(135deg, #feca57, #f8b500);
}

.stat-info .stat-icon {
    background: linear-gradient(135deg, #54a0ff, #667eea);
}

.stat-details {
    flex: 1;
}

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

.stat-footer {
    font-size: 0.875rem;
}

/* Member Cards */
.member-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
}

.member-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
}

.member-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.member-role-badge {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    color: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
}

.member-avatar-wrapper {
    text-align: center;
    margin-bottom: 1rem;
    position: relative;
}

.member-avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.member-status-indicator {
    position: absolute;
    bottom: 8px;
    left: 50%;
    transform: translateX(20px);
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 3px solid white;
}

.status-active {
    background: #10b981;
}

.status-inactive {
    background: #9ca3af;
}

.member-info {
    text-align: center;
    margin-bottom: 1rem;
}

.member-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.25rem;
}

.member-email {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.75rem;
}

.member-meta {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.meta-item {
    font-size: 0.813rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.member-role-tag {
    text-align: center;
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

.role-chairman {
    background: #fef3c7;
    color: #92400e;
}

.role-treasurer {
    background: #dbeafe;
    color: #1e40af;
}

.role-secretary {
    background: #e0e7ff;
    color: #3730a3;
}

.role-member {
    background: #f3f4f6;
    color: #374151;
}

.member-financial {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.financial-item {
    flex: 1;
    text-align: center;
}

.financial-label {
    display: block;
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.financial-value {
    display: block;
    font-size: 1rem;
    font-weight: 700;
}

.member-actions {
    display: flex;
    gap: 0.5rem;
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