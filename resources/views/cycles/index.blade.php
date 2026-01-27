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
                        <li class="breadcrumb-item active">Cycles</li>
                    </ol>
                </nav>
                <h2 class="mb-2 fw-bold">Cycle Management</h2>
                <p class="text-muted mb-0">Manage society financial cycles and periods</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary">
                    <i class="fas fa-history me-2"></i>View History
                </button>
                <a href="{{ route('societies.cycles.create', $society) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Create Cycle
                </a>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary active">
                    <i class="fas fa-list me-1"></i>All Cycles
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-play-circle me-1"></i>Active Only
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-archive me-1"></i>Closed
                </button>
            </div>
            <div class="d-flex gap-2">
                <div class="input-group" style="max-width: 300px;">
                    <span class="input-group-text bg-white">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" placeholder="Search cycles...">
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-primary">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-repeat"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Total Cycles</span>
                    <h3 class="stat-value">{{ $cycles->count() }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">All time periods</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-success">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Active Cycle</span>
                    <h3 class="stat-value">{{ $cycles->where('status', 'active')->count() }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">Currently running</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-info">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Completed</span>
                    <h3 class="stat-value">{{ $cycles->where('status', 'closed')->count() }}</h3>
                    <div class="stat-footer">
                        <span class="text-muted">Closed cycles</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-warning">
                <div class="stat-icon-wrapper">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-details">
                    <span class="stat-label">Current Period</span>
                    <h3 class="stat-value" style="font-size: 1.25rem;">
                        {{ Str::limit($cycles->where('status', 'active')->first()?->name ?? 'None', 20) }}
                    </h3>
                    <div class="stat-footer">
                        <span class="text-muted">Active cycle name</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Cycle Highlight -->
    @php
        $activeCycle = $cycles->where('status', 'active')->first();
    @endphp
    @if($activeCycle)
    <div class="active-cycle-banner mb-4">
        <div class="banner-content">
            <div class="banner-icon">
                <i class="fas fa-sync-alt"></i>
            </div>
            <div class="banner-info">
                <h5 class="banner-title">{{ $activeCycle->name }}</h5>
                <div class="banner-meta">
                    <span class="meta-item">
                        <i class="fas fa-calendar-day me-1"></i>
                        {{ $activeCycle->start_date->format('d M Y') }}
                    </span>
                    <span class="meta-divider">→</span>
                    <span class="meta-item">
                        <i class="fas fa-calendar-check me-1"></i>
                        {{ $activeCycle->end_date->format('d M Y') }}
                    </span>
                    <span class="meta-divider">•</span>
                    <span class="meta-item">
                        <i class="fas fa-hourglass-half me-1"></i>
                        {{ $activeCycle->start_date->diffInDays($activeCycle->end_date) }} days total
                    </span>
                </div>
                @php
                    $totalDays = $activeCycle->start_date->diffInDays($activeCycle->end_date);
                    $elapsedDays = $activeCycle->start_date->diffInDays(now());
                    $progress = $totalDays > 0 ? min(100, ($elapsedDays / $totalDays) * 100) : 0;
                    $remainingDays = max(0, $activeCycle->end_date->diffInDays(now()));
                @endphp
                <div class="banner-progress">
                    <div class="progress">
                        <div class="progress-bar" style="width: {{ $progress }}%"></div>
                    </div>
                    <div class="progress-info">
                        <span class="progress-text">{{ number_format($progress, 0) }}% complete</span>
                        @if($activeCycle->end_date->isFuture())
                            <span class="progress-days text-success">{{ $remainingDays }} days remaining</span>
                        @else
                            <span class="progress-days text-danger">Ended {{ $activeCycle->end_date->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="banner-action">
            <a href="{{ route('societies.cycles.show', [$society, $activeCycle]) }}" class="btn btn-light">
                <i class="fas fa-arrow-right me-2"></i>View Details
            </a>
        </div>
    </div>
    @endif

    @if($cycles->isEmpty())
        <!-- Enhanced Empty State -->
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="empty-illustration mb-4">
                    <i class="fas fa-repeat"></i>
                </div>
                <h4 class="mb-3">No Cycles Yet</h4>
                <p class="text-muted mb-4">Create your first cycle to start managing society finances</p>
                <a href="{{ route('societies.cycles.create', $society) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Create First Cycle
                </a>
            </div>
        </div>
    @else
        <!-- Cycles Grid -->
        <div class="row g-4">
            @foreach($cycles as $cycle)
            <div class="col-xl-4 col-lg-6">
                <div class="cycle-card {{ $cycle->status === 'active' ? 'cycle-card-active' : '' }}">
                    <!-- Card Header -->
                    <div class="cycle-card-header">
                        <div class="cycle-status-badge">
                            @if($cycle->status === 'active')
                                <div class="status-indicator status-active">
                                    <i class="fas fa-circle"></i>
                                </div>
                                <span>Active</span>
                            @else
                                <div class="status-indicator status-closed">
                                    <i class="fas fa-check"></i>
                                </div>
                                <span>Closed</span>
                            @endif
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('societies.cycles.show', [$society, $cycle]) }}"><i class="fas fa-eye me-2"></i>View Details</a></li>
                                @if($cycle->status === 'active')
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('societies.cycles.close', [$society, $cycle]) }}" onsubmit="return confirm('Are you sure you want to close this cycle?')">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="fas fa-stop-circle me-2"></i>Close Cycle
                                            </button>
                                        </form>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>

                    <!-- Cycle Name & Icon -->
                    <div class="cycle-icon-wrapper">
                        <div class="cycle-icon {{ $cycle->status === 'active' ? 'cycle-icon-active' : '' }}">
                            <i class="fas {{ $cycle->status === 'active' ? 'fa-sync-alt' : 'fa-check-circle' }}"></i>
                        </div>
                    </div>

                    <div class="cycle-info">
                        <h5 class="cycle-name">{{ $cycle->name }}</h5>
                        <p class="cycle-subtitle">
                            @if($cycle->status === 'active')
                                Currently active cycle
                            @else
                                Closed {{ $cycle->updated_at->diffForHumans() }}
                            @endif
                        </p>
                    </div>

                    <!-- Date Range -->
                    <div class="cycle-dates">
                        <div class="date-item">
                            <div class="date-icon bg-success-subtle">
                                <i class="fas fa-play text-success"></i>
                            </div>
                            <div>
                                <div class="date-label">Start Date</div>
                                <div class="date-value">{{ $cycle->start_date->format('d M Y') }}</div>
                            </div>
                        </div>
                        <div class="date-divider">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                        <div class="date-item">
                            <div class="date-icon bg-danger-subtle">
                                <i class="fas fa-stop text-danger"></i>
                            </div>
                            <div>
                                <div class="date-label">End Date</div>
                                <div class="date-value">{{ $cycle->end_date->format('d M Y') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="cycle-financial">
                        <div class="financial-item">
                            <div class="financial-icon bg-success-subtle">
                                <i class="fas fa-coins text-success"></i>
                            </div>
                            <div>
                                <div class="financial-label">Contributions</div>
                                <div class="financial-value">M {{ number_format($cycle->transactions()->where('type', 'contribution')->sum('amount'), 2) }}</div>
                            </div>
                        </div>
                        <div class="financial-item">
                            <div class="financial-icon bg-warning-subtle">
                                <i class="fas fa-hand-holding-usd text-warning"></i>
                            </div>
                            <div>
                                <div class="financial-label">Loans</div>
                                <div class="financial-value">M {{ number_format($cycle->loans()->sum('principal'), 2) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Duration Badge -->
                    <div class="cycle-duration">
                        <span class="duration-badge">
                            <i class="fas fa-clock me-2"></i>
                            {{ $cycle->start_date->diffInDays($cycle->end_date) }} days duration
                        </span>
                    </div>

                    <!-- Progress Bar (Active Only) -->
                    @if($cycle->status === 'active')
                        @php
                            $totalDays = $cycle->start_date->diffInDays($cycle->end_date);
                            $elapsedDays = $cycle->start_date->diffInDays(now());
                            $progress = $totalDays > 0 ? min(100, ($elapsedDays / $totalDays) * 100) : 0;
                        @endphp
                        <div class="cycle-progress">
                            <div class="progress-header">
                                <span class="progress-label">Cycle Progress</span>
                                <span class="progress-percentage">{{ number_format($progress, 0) }}%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-gradient-success" style="width: {{ $progress }}%"></div>
                            </div>
                            <div class="progress-footer">
                                @if($cycle->end_date->isFuture())
                                    <span class="text-success">
                                        <i class="fas fa-clock me-1"></i>
                                        {{ now()->diffInDays($cycle->end_date) }} days remaining
                                    </span>
                                @else
                                    <span class="text-danger">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        Ended {{ $cycle->end_date->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Action Button -->
                    <div class="cycle-actions">
                        <a href="{{ route('societies.cycles.show', [$society, $cycle]) }}" class="btn btn-primary w-100">
                            <i class="fas fa-arrow-right me-2"></i>View Cycle Details
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if(method_exists($cycles, 'links'))
            <div class="mt-4">
                {{ $cycles->links() }}
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

/* Active Cycle Banner */
.active-cycle-banner {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
    border: 2px solid rgba(102, 126, 234, 0.2);
    border-radius: 16px;
    padding: 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
}

.banner-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex: 1;
}

.banner-icon {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    animation: rotate 3s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.banner-info {
    flex: 1;
}

.banner-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.5rem;
}

.banner-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 1rem;
}

.meta-divider {
    color: #d1d5db;
}

.banner-progress {
    max-width: 400px;
}

.banner-progress .progress {
    height: 10px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.5);
    margin-bottom: 0.5rem;
}

.banner-progress .progress-bar {
    background: linear-gradient(90deg, #1dd1a1, #10ac84);
    border-radius: 10px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.813rem;
}

/* Cycle Cards */
.cycle-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.cycle-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
}

.cycle-card-active {
    border: 2px solid rgba(29, 209, 161, 0.3);
    background: linear-gradient(to bottom, rgba(29, 209, 161, 0.02), white);
}

.cycle-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.cycle-status-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.875rem;
    background: #f9fafb;
    border-radius: 20px;
    font-size: 0.813rem;
    font-weight: 600;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.status-active {
    color: #10b981;
    animation: pulse-dot 2s ease-in-out infinite;
}

.status-closed {
    color: #6b7280;
}

@keyframes pulse-dot {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.8;
    }
}

.cycle-icon-wrapper {
    text-align: center;
    margin-bottom: 1rem;
}

.cycle-icon {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    color: #6b7280;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.cycle-icon-active {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    color: #10b981;
}

.cycle-info {
    text-align: center;
    margin-bottom: 1.5rem;
}

.cycle-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.25rem;
}

.cycle-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0;
}

.cycle-dates {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.date-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
}

.bg-success-subtle { background: rgba(29, 209, 161, 0.1); }
.bg-danger-subtle { background: rgba(239, 68, 68, 0.1); }

.date-label {
    font-size: 0.75rem;
    color: #6b7280;
}

.date-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
}

.date-divider {
    color: #d1d5db;
    font-size: 1rem;
}

/* Financial Summary */
.cycle-financial {
    display: flex;
    gap: 0.75rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.financial-item {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.financial-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.financial-label {
    font-size: 0.75rem;
    color: #6b7280;
}

.financial-value {
    font-size: 0.938rem;
    font-weight: 700;
    color: #111827;
}

.cycle-duration {
    text-align: center;
    margin-bottom: 1.5rem;
}

.duration-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.cycle-progress {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 12px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.813rem;
}

.progress-label {
    color: #6b7280;
    font-weight: 600;
}

.progress-percentage {
    color: #111827;
    font-weight: 700;
}

.cycle-progress .progress {
    height: 8px;
    border-radius: 10px;
    background: #e5e7eb;
    margin-bottom: 0.5rem;
}

.bg-gradient-success {
    background: linear-gradient(90deg, #1dd1a1, #10ac84);
}

.progress-footer {
    font-size: 0.813rem;
    text-align: center;
}

.cycle-actions {
    margin-top: auto;
}

/* Empty State */
.empty-illustration {
    font-size: 5rem;
    color: #e5e7eb;
}

/* Breadcrumb */
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