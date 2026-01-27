@php
    $user = auth()->user();

    $isChairman  = isset($currentSociety) && $user->isChairmanOf($currentSociety);
    $isTreasurer = isset($currentSociety) && $user->isTreasurerOf($currentSociety);
    $isSecretary = isset($currentSociety) && $user->isSecretaryOf($currentSociety);

    $canManageFinance = $isChairman || $isTreasurer;
    $hasActiveCycle = isset($currentCycle);
@endphp

<!-- Modern Topbar -->
<header class="modern-topbar">
    <div class="topbar-container">
        <!-- Left Section -->
        <div class="topbar-left">
            <!-- Mobile Menu Toggle -->
            <button class="mobile-toggle" id="mobileSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Logo (Mobile Only) -->
            <div class="mobile-logo">
                <i class="fas fa-users-cog"></i>
                <span>Mokhatlo</span>
            </div>

            <!-- Search Bar (Desktop) -->
            <div class="topbar-search d-none d-lg-flex">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search members, transactions..." class="search-input">
                <kbd class="search-shortcut">⌘K</kbd>
            </div>
        </div>

        <!-- Center Section - Current Society -->
        @if(isset($currentSociety))
        <div class="topbar-center d-none d-md-flex">
            <div class="current-society-badge">
                <div class="society-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="society-info">
                    <span class="society-name">{{ Str::limit($currentSociety->name, 25) }}</span>
                    @if(isset($currentCycle))
                    <span class="society-cycle">
                        <i class="fas fa-sync-alt"></i>
                        {{ Str::limit($currentCycle->name, 20) }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Right Section -->
        <div class="topbar-right">
            <!-- Quick Actions (Desktop) -->
           

            <!-- Notifications -->
            <div class="topbar-item">
                <button class="topbar-icon-btn" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    @php
                        $notificationCount = isset($notifications) ? count($notifications) : 0;
                    @endphp
                    @if($notificationCount > 0)
                    <span class="notification-badge">{{ $notificationCount > 9 ? '9+' : $notificationCount }}</span>
                    @endif
                </button>

                <!-- Notification Dropdown -->
                <div class="topbar-dropdown notification-dropdown" id="notificationDropdown">
                    <div class="dropdown-header">
                        <h6>Notifications</h6>
                        @if($notificationCount > 0)
                        <button class="mark-all-read">Mark all read</button>
                        @endif
                    </div>

                    <div class="notification-list">
                        @forelse($notifications ?? [] as $notification)
                        <a href="#" class="notification-item {{ $notification->read_at ? '' : 'unread' }}">
                            <div class="notification-icon {{ $notification->type ?? 'primary' }}">
                                <i class="fas {{ $notification->icon ?? 'fa-bell' }}"></i>
                            </div>
                            <div class="notification-content">
                                <h6>{{ $notification->title }}</h6>
                                <p>{{ Str::limit($notification->message, 60) }}</p>
                                <span class="notification-time">{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                        </a>
                        @empty
                        <div class="notification-empty">
                            <i class="fas fa-check-circle"></i>
                            <p>You're all caught up!</p>
                            <span>No new notifications</span>
                        </div>
                        @endforelse
                    </div>

                    @if($notificationCount > 0)
                    <div class="dropdown-footer">
                        <a href="#">View all notifications</a>
                    </div>
                    @endif
                </div>
            </div>

            <!-- User Profile -->
            <div class="topbar-item">
                <button class="user-profile-btn" id="userProfileBtn">
                    <img src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=667eea&color=fff' }}" 
                         alt="{{ auth()->user()->name }}" 
                         class="user-avatar">
                    <div class="user-info d-none d-lg-flex">
                        <span class="user-name">{{ auth()->user()->name }}</span>
                        <span class="user-role">
                            @if(isset($currentSociety))
                                {{ ucfirst(auth()->user()->members()->where('society_id', $currentSociety->id)->first()?->role ?? 'Member') }}
                            @else
                                Member
                            @endif
                        </span>
                    </div>
                    <i class="fas fa-chevron-down d-none d-lg-block"></i>
                </button>

                <!-- User Dropdown -->
                <div class="topbar-dropdown user-dropdown" id="userDropdown">
                    <div class="dropdown-header user-header">
                        <img src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=667eea&color=fff' }}" 
                             alt="{{ auth()->user()->name }}" 
                             class="user-avatar-large">
                        <div>
                            <h6>{{ auth()->user()->name }}</h6>
                            <p>{{ auth()->user()->email }}</p>
                        </div>
                    </div>

                    <div class="dropdown-divider"></div>

                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>

                    <a href="{{ route('societies.index') }}" class="dropdown-item">
                        <i class="fas fa-th-large"></i>
                        <span>My Societies</span>
                    </a>

                    @if(isset($currentSociety))
                    <a href="{{ route('societies.dashboard', $currentSociety) }}" class="dropdown-item">
                        <i class="fas fa-chart-pie"></i>
                        <span>Current Dashboard</span>
                    </a>
                    @endif

                    <div class="dropdown-divider"></div>

                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>

                    <a href="#" class="dropdown-item">
                        <i class="fas fa-question-circle"></i>
                        <span>Help & Support</span>
                    </a>

                    <div class="dropdown-divider"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item logout-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
/* Modern Topbar */
.modern-topbar {
    position: fixed;
    top: 0;
    left: 260px;
    right: 0;
    height: 70px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    z-index: 999;
    transition: left 0.3s ease;
}

.topbar-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    padding: 0 1.5rem;
    gap: 1rem;
}

/* Left Section */
.topbar-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.mobile-toggle {
    display: none;
    width: 40px;
    height: 40px;
    background: transparent;
    border: none;
    color: #6b7280;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.mobile-toggle:hover {
    background: #f3f4f6;
    color: #111827;
}

.mobile-logo {
    display: none;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 1.125rem;
    color: #111827;
}

.mobile-logo i {
    color: #667eea;
}

/* Search Bar */
.topbar-search {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 1rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    max-width: 400px;
    width: 100%;
    transition: all 0.2s ease;
}

.topbar-search:focus-within {
    background: #ffffff;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.topbar-search i {
    color: #9ca3af;
    font-size: 0.875rem;
}

.search-input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.875rem;
    color: #111827;
}

.search-input::placeholder {
    color: #9ca3af;
}

.search-shortcut {
    padding: 0.25rem 0.5rem;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 600;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Center Section - Society Badge */
.topbar-center {
    display: flex;
    align-items: center;
    justify-content: center;
}

.current-society-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
    border: 1px solid rgba(102, 126, 234, 0.2);
    border-radius: 12px;
    transition: all 0.2s ease;
}

.current-society-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.society-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
}

.society-info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.society-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
    line-height: 1.2;
}

.society-cycle {
    font-size: 0.75rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.society-cycle i {
    font-size: 0.65rem;
    animation: spin 2s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Right Section */
.topbar-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
    justify-content: flex-end;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-right: 0.75rem;
    margin-right: 0.75rem;
    border-right: 1px solid #e5e7eb;
}

.quick-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.quick-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.quick-btn-contribution:hover {
    background: linear-gradient(135deg, #1dd1a1, #10ac84);
    border-color: #1dd1a1;
    color: white;
}

.quick-btn-loan:hover {
    background: linear-gradient(135deg, #feca57, #f8b500);
    border-color: #feca57;
    color: white;
}

.quick-btn i {
    font-size: 0.875rem;
}

/* Topbar Items */
.topbar-item {
    position: relative;
}

.topbar-icon-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    color: #6b7280;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
}

.topbar-icon-btn:hover {
    background: #f9fafb;
    color: #111827;
    border-color: #667eea;
}

.topbar-icon-btn i {
    font-size: 1rem;
}

.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    border-radius: 9px;
    padding: 0 5px;
    border: 2px solid #ffffff;
}

/* User Profile Button */
.user-profile-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.375rem;
    padding-right: 0.75rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.user-profile-btn:hover {
    background: #f9fafb;
    border-color: #667eea;
}

.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    object-fit: cover;
}

.user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.125rem;
}

.user-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
    line-height: 1.2;
}

.user-role {
    font-size: 0.75rem;
    color: #6b7280;
    line-height: 1;
}

.user-profile-btn i {
    font-size: 0.75rem;
    color: #9ca3af;
    transition: transform 0.2s ease;
}

.user-profile-btn.active i {
    transform: rotate(180deg);
}

/* Dropdowns */
.topbar-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    min-width: 320px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 1000;
}

.topbar-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dropdown-header h6 {
    margin: 0;
    font-size: 0.938rem;
    font-weight: 700;
    color: #111827;
}

.mark-all-read {
    background: none;
    border: none;
    font-size: 0.813rem;
    color: #667eea;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
}

.mark-all-read:hover {
    text-decoration: underline;
}

/* Notification Dropdown */
.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-list::-webkit-scrollbar {
    width: 6px;
}

.notification-list::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.notification-item {
    display: flex;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    text-decoration: none;
    transition: all 0.2s ease;
    border-bottom: 1px solid #f3f4f6;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background: #f9fafb;
}

.notification-item.unread {
    background: linear-gradient(90deg, rgba(102, 126, 234, 0.05), transparent);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-icon.primary {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
}

.notification-icon.success {
    background: rgba(29, 209, 161, 0.1);
    color: #10ac84;
}

.notification-icon.warning {
    background: rgba(254, 202, 87, 0.1);
    color: #f8b500;
}

.notification-icon.danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.notification-content {
    flex: 1;
}

.notification-content h6 {
    margin: 0 0 0.25rem 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
}

.notification-content p {
    margin: 0 0 0.25rem 0;
    font-size: 0.813rem;
    color: #6b7280;
    line-height: 1.4;
}

.notification-time {
    font-size: 0.75rem;
    color: #9ca3af;
}

.notification-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1.5rem;
    text-align: center;
}

.notification-empty i {
    font-size: 3rem;
    color: #10b981;
    margin-bottom: 1rem;
}

.notification-empty p {
    margin: 0 0 0.25rem 0;
    font-size: 0.938rem;
    font-weight: 600;
    color: #111827;
}

.notification-empty span {
    font-size: 0.813rem;
    color: #6b7280;
}

.dropdown-footer {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid #e5e7eb;
    text-align: center;
}

.dropdown-footer a {
    font-size: 0.875rem;
    color: #667eea;
    font-weight: 600;
    text-decoration: none;
}

.dropdown-footer a:hover {
    text-decoration: underline;
}

/* User Dropdown */
.user-dropdown {
    min-width: 280px;
}

.user-header {
    gap: 0.75rem;
}

.user-avatar-large {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    object-fit: cover;
}

.user-header h6 {
    margin: 0 0 0.25rem 0;
    font-size: 0.938rem;
}

.user-header p {
    margin: 0;
    font-size: 0.813rem;
    color: #6b7280;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    color: #6b7280;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    cursor: pointer;
    font-size: 0.875rem;
}

.dropdown-item:hover {
    background: #f9fafb;
    color: #111827;
}

.dropdown-item i {
    font-size: 1rem;
    width: 20px;
}

.dropdown-item.logout-item {
    color: #ef4444;
}

.dropdown-item.logout-item:hover {
    background: rgba(239, 68, 68, 0.05);
}

.dropdown-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 0.5rem 0;
}

/* Responsive */
@media (max-width: 768px) {
    .modern-topbar {
        left: 0;
    }

    .mobile-toggle {
        display: flex;
    }

    .mobile-logo {
        display: flex;
    }

    .topbar-search {
        display: none !important;
    }

    .topbar-center {
        display: none !important;
    }

    .user-info {
        display: none !important;
    }

    .user-profile-btn i.fa-chevron-down {
        display: none !important;
    }
}

@media (max-width: 1280px) {
    .quick-actions {
        display: none !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle dropdowns
    function toggleDropdown(btnId, dropdownId) {
        const btn = document.getElementById(btnId);
        const dropdown = document.getElementById(dropdownId);
        
        if (!btn || !dropdown) return;
        
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Close other dropdowns
            document.querySelectorAll('.topbar-dropdown').forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('show');
            btn.classList.toggle('active');
        });
    }
    
    toggleDropdown('notificationBtn', 'notificationDropdown');
    toggleDropdown('userProfileBtn', 'userDropdown');
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.topbar-item')) {
            document.querySelectorAll('.topbar-dropdown').forEach(d => {
                d.classList.remove('show');
            });
            document.querySelectorAll('.topbar-icon-btn, .user-profile-btn').forEach(btn => {
                btn.classList.remove('active');
            });
        }
    });
    
    // Mobile sidebar toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            const sidebar = document.querySelector('.modern-sidebar');
            if (sidebar) {
                sidebar.classList.toggle('show');
            }
        });
    }
    
    // Search keyboard shortcut (Cmd/Ctrl + K)
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.focus();
            }
        }
    });
    
    // Mark notification as read
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            this.classList.remove('unread');
            // Add AJAX call here to mark as read in backend
        });
    });
    
    // Mark all as read
    const markAllRead = document.querySelector('.mark-all-read');
    if (markAllRead) {
        markAllRead.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            // Add AJAX call here to mark all as read in backend
        });
    }
});
</script>