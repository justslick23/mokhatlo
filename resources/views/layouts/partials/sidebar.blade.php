@php
    $user = auth()->user();

    $isChairman  = isset($currentSociety) && $user->isChairmanOf($currentSociety);
    $isTreasurer = isset($currentSociety) && $user->isTreasurerOf($currentSociety);
    $isSecretary = isset($currentSociety) && $user->isSecretaryOf($currentSociety);

    // Chairman always inherits Treasurer powers
    $canManageFinance = $isChairman || $isTreasurer;

    $hasActiveCycle = isset($currentCycle);
@endphp

<!-- Modern Sidebar -->
<aside class="modern-sidebar">
    <div class="sidebar-header">
        <div class="brand">
            <i data-feather="layers"></i>
            <span>Mokhatlo</span>
        </div>
    </div>

    <div class="sidebar-content">
        <!-- Society Switcher -->
        @if(auth()->user()->societies->count() > 1)
        <div class="society-switcher">
            <form method="POST" action="{{ route('societies.switch') }}">
                @csrf
                <div class="custom-select-wrapper">
                    <i data-feather="briefcase" class="select-icon"></i>
                    <select name="society_id" class="custom-select" onchange="this.form.submit()">
                        @foreach(auth()->user()->societies as $society)
                            <option value="{{ $society->id }}"
                                @selected(isset($currentSociety) && $currentSociety->id === $society->id)>
                                {{ $society->name }}
                            </option>
                        @endforeach
                    </select>
                    <i data-feather="chevron-down" class="select-arrow"></i>
                </div>
            </form>
        </div>
        @endif

        <nav class="sidebar-nav">
            <!-- Dashboard -->
            <div class="nav-section">
                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i data-feather="home"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            @if(isset($currentSociety))
            <!-- Society Section -->
            <div class="nav-section">
                <div class="section-header">
                    <span>{{ Str::limit($currentSociety->name, 20) }}</span>
                </div>

                <a href="{{ route('societies.dashboard', $currentSociety) }}" 
                   class="nav-item {{ request()->routeIs('societies.dashboard') ? 'active' : '' }}">
                    <i data-feather="pie-chart"></i>
                    <span>Overview</span>
                </a>

                <!-- Active Cycle Badge -->
                @if($hasActiveCycle)
                <div class="cycle-badge">
                    <i data-feather="zap"></i>
                    <div class="cycle-info">
                        <span class="cycle-label">Active Cycle</span>
                        <span class="cycle-name">{{ $currentCycle->name }}</span>
                    </div>
                </div>
                @endif
            </div>

            <!-- Members Section -->
            <div class="nav-section">
                <div class="section-header">
                    <span>Members</span>
                </div>

                <a href="{{ route('societies.members.index', $currentSociety) }}" 
                   class="nav-item {{ request()->routeIs('societies.members.index') ? 'active' : '' }}">
                    <i data-feather="users"></i>
                    <span>All Members</span>
                </a>

                @if($isChairman || $isSecretary)
                <a href="{{ route('societies.members.create', $currentSociety) }}" 
                   class="nav-item {{ request()->routeIs('societies.members.create') ? 'active' : '' }}">
                    <i data-feather="user-plus"></i>
                    <span>Add Member</span>
                </a>
                @endif
            </div>

            <!-- Finance Section -->
            @if($canManageFinance && $hasActiveCycle)
            <div class="nav-section">
                <div class="section-header">
                    <span>Finance</span>
                    <span class="badge-success">Active</span>
                </div>

                <a href="{{ route('societies.contributions.index', $currentSociety) }}" 
                   class="nav-item {{ request()->routeIs('societies.contributions.*') ? 'active' : '' }}">
                    <i data-feather="dollar-sign"></i>
                    <span>Contributions</span>
                </a>

                <a href="{{ route('societies.loans.index', $currentSociety) }}" 
                   class="nav-item {{ request()->routeIs('societies.loans.*') ? 'active' : '' }}">
                    <i data-feather="credit-card"></i>
                    <span>Loans</span>
                </a>

                <a href="{{ route('societies.repayments.index', $currentSociety) }}" 
                   class="nav-item {{ request()->routeIs('societies.repayments.*') ? 'active' : '' }}">
                    <i data-feather="refresh-cw"></i>
                    <span>Repayments</span>
                </a>
            </div>
            @endif

            <!-- Reports Section -->
            @if(($isChairman || $isTreasurer || $isSecretary) && $hasActiveCycle)
            <div class="nav-section">
                <div class="section-header">
                    <span>Reports & Analytics</span>
                </div>

                <a href="{{ route('societies.reports.summary', $currentSociety) }}" 
                   class="nav-item">
                    <i data-feather="trending-up"></i>
                    <span>Summary</span>
                </a>

                <a href="{{ route('societies.reports.members', $currentSociety) }}" 
                   class="nav-item">
                    <i data-feather="users"></i>
                    <span>Member Reports</span>
                </a>

                <a href="{{ route('societies.reports.transactions', $currentSociety) }}" 
                   class="nav-item">
                    <i data-feather="list"></i>
                    <span>Transactions</span>
                </a>

                <a href="{{ route('societies.reports.loans', $currentSociety) }}" 
                   class="nav-item">
                    <i data-feather="file-text"></i>
                    <span>Loan Reports</span>
                </a>
            </div>
            @endif

            <!-- Management Section -->
            @if($isChairman)
            <div class="nav-section">
                <div class="section-header">
                    <span>Management</span>
                </div>

                @if($hasActiveCycle)
                <a href="{{ route('societies.year-end.preview', $currentSociety) }}" 
                   class="nav-item {{ request()->routeIs('societies.year-end.*') ? 'active' : '' }}">
                    <i data-feather="calendar"></i>
                    <span>Year-End Process</span>
                </a>
                @endif

                <a href="{{ route('societies.cycles.index', $currentSociety) }}" 
                   class="nav-item">
                    <i data-feather="repeat"></i>
                    <span>Manage Cycles</span>
                </a>

                <a href="{{ route('societies.settings', $currentSociety) }}" 
                   class="nav-item {{ request()->routeIs('societies.settings') ? 'active' : '' }}">
                    <i data-feather="settings"></i>
                    <span>Society Settings</span>
                </a>
            </div>
            @endif
            @endif

            <!-- Global Navigation -->
            <div class="nav-section">
                <div class="section-divider"></div>

                <a href="{{ route('societies.index') }}" 
                   class="nav-item {{ request()->routeIs('societies.index') ? 'active' : '' }}">
                    <i data-feather="grid"></i>
                    <span>All Societies</span>
                </a>

                <a href="{{ route('societies.create') }}" 
                   class="nav-item {{ request()->routeIs('societies.create') ? 'active' : '' }}">
                    <i data-feather="plus-circle"></i>
                    <span>Create Society</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <a href="{{ route('profile.edit') }}" class="footer-item">
            <i data-feather="user"></i>
            <span>Profile</span>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="footer-item-form">
            @csrf
            <button type="submit" class="footer-item">
                <i data-feather="log-out"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>

<style>
/* Modern Sidebar Styles */
.modern-sidebar {
    width: 260px;
    height: 100vh;
    background: #ffffff;
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    overflow: hidden;
}

/* Sidebar Header */
.sidebar-header {
    padding: 1.5rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
}

.brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
}

.brand i {
    width: 28px;
    height: 28px;
    color: #6366f1;
}

/* Sidebar Content */
.sidebar-content {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 1rem 0;
}

.sidebar-content::-webkit-scrollbar {
    width: 6px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Society Switcher */
.society-switcher {
    padding: 0 1rem 1rem 1rem;
}

.custom-select-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 0 0.75rem;
    transition: all 0.2s ease;
}

.custom-select-wrapper:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.select-icon {
    width: 18px;
    height: 18px;
    color: #6b7280;
    flex-shrink: 0;
}

.custom-select {
    flex: 1;
    border: none;
    background: transparent;
    padding: 0.625rem 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #111827;
    cursor: pointer;
    outline: none;
    appearance: none;
}

.select-arrow {
    width: 16px;
    height: 16px;
    color: #6b7280;
    pointer-events: none;
}

/* Navigation Sections */
.nav-section {
    margin-bottom: 1.5rem;
    padding: 0 1rem;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
}

.section-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 1rem 0;
}

/* Cycle Badge */
.cycle-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    margin: 0.5rem 0;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid #a7f3d0;
    border-radius: 8px;
}

.cycle-badge i {
    width: 20px;
    height: 20px;
    color: #10b981;
    flex-shrink: 0;
}

.cycle-info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.cycle-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #059669;
}

.cycle-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #047857;
}

/* Badge */
.badge-success {
    padding: 0.125rem 0.5rem;
    background: #d1fae5;
    color: #065f46;
    font-size: 0.65rem;
    font-weight: 600;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Navigation Items */
.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    margin-bottom: 0.25rem;
    color: #4b5563;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    position: relative;
}

.nav-item i {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

.nav-item:hover {
    background: #f3f4f6;
    color: #111827;
}

.nav-item.active {
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    color: #4f46e5;
    font-weight: 600;
}

.nav-item.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 60%;
    background: #6366f1;
    border-radius: 0 2px 2px 0;
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 1rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.footer-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    background: transparent;
    border: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
}

.footer-item i {
    width: 18px;
    height: 18px;
}

.footer-item:hover {
    background: #f3f4f6;
    color: #111827;
}

.footer-item-form {
    margin: 0;
    padding: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .modern-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }

    .modern-sidebar.show {
        transform: translateX(0);
    }
}

/* Mobile Toggle Button (add this to your layout) */
.sidebar-toggle {
    display: none;
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 56px;
    height: 56px;
    background: #6366f1;
    color: white;
    border: none;
    border-radius: 50%;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    cursor: pointer;
    z-index: 999;
}

@media (max-width: 768px) {
    .sidebar-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }
}
</style>

<script>
// Initialize Feather Icons
if (typeof feather !== 'undefined') {
    feather.replace();
}

// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.modern-sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    }
});
</script>