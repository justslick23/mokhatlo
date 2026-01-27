@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Enhanced Header -->
    <div class="page-header mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="{{ route('societies.dashboard', $society) }}">{{ $society->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('societies.members.index', $society) }}">Members</a></li>
                <li class="breadcrumb-item active">Add Member</li>
            </ol>
        </nav>
        <h2 class="mb-2 fw-bold">Add New Member</h2>
        <p class="text-muted mb-0">Add a new member to your society</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Main Form Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="fas fa-user-plus text-primary me-2"></i>Member Information
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('societies.members.store', $society) }}">
                        @csrf

                        <!-- Personal Information Section -->
                        <div class="form-section mb-4">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-id-card text-muted me-2"></i>Personal Details
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-user text-muted"></i>
                                        </span>
                                        <input 
                                            name="name" 
                                            type="text"
                                            class="form-control border-start-0" 
                                            placeholder="Enter full name"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Email Address <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-envelope text-muted"></i>
                                        </span>
                                        <input 
                                            name="email" 
                                            type="email" 
                                            class="form-control border-start-0" 
                                            placeholder="member@example.com"
                                            required
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Role & Date Section -->
                        <div class="form-section">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-briefcase text-muted me-2"></i>Role & Membership
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Role <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-user-tag text-muted"></i>
                                        </span>
                                        <select name="role" class="form-control border-start-0" required>
                                            <option value="">Select role...</option>
                                            <option value="member">Member</option>
                                            <option value="chairman">Chairman</option>
                                            <option value="treasurer">Treasurer</option>
                                            <option value="secretary">Secretary</option>
                                        </select>
                                    </div>
                                    <small class="text-muted">Choose the member's role in the society</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Joined Date <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-calendar text-muted"></i>
                                        </span>
                                        <input 
                                            type="date" 
                                            name="joined_date" 
                                            class="form-control border-start-0" 
                                            value="{{ now()->toDateString() }}"
                                            required
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 mt-4 pt-4 border-top">
                            <button type="submit" class="btn btn-primary btn-lg px-4">
                                <i class="fas fa-check me-2"></i>Add Member
                            </button>
                            <a href="{{ route('societies.members.index', $society) }}" class="btn btn-light btn-lg px-4">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
            <!-- Help Card -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-info-circle text-primary me-2"></i>Adding Members
                    </h6>
                    <div class="info-list">
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Ensure email addresses are unique</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Members can have different roles</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Join date helps track membership duration</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Role Info Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-users-cog text-warning me-2"></i>Role Permissions
                    </h6>
                    <div class="role-info">
                        <div class="role-item">
                            <span class="role-badge-small role-chairman">
                                <i class="fas fa-crown"></i>
                            </span>
                            <div>
                                <div class="fw-semibold">Chairman</div>
                                <small class="text-muted">Full society access</small>
                            </div>
                        </div>
                        <div class="role-item">
                            <span class="role-badge-small role-treasurer">
                                <i class="fas fa-wallet"></i>
                            </span>
                            <div>
                                <div class="fw-semibold">Treasurer</div>
                                <small class="text-muted">Manages finances</small>
                            </div>
                        </div>
                        <div class="role-item">
                            <span class="role-badge-small role-secretary">
                                <i class="fas fa-clipboard"></i>
                            </span>
                            <div>
                                <div class="fw-semibold">Secretary</div>
                                <small class="text-muted">Handles records</small>
                            </div>
                        </div>
                        <div class="role-item">
                            <span class="role-badge-small role-member">
                                <i class="fas fa-user"></i>
                            </span>
                            <div>
                                <div class="fw-semibold">Member</div>
                                <small class="text-muted">Standard access</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Card Styling */
.card {
    border-radius: 16px;
}

.card-header {
    border-radius: 16px 16px 0 0 !important;
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

/* Form Sections */
.form-section {
    position: relative;
}

.section-title {
    font-size: 0.938rem;
    color: #374151;
    font-weight: 600;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f3f4f6;
}

/* Input Groups */
.input-group-text {
    background-color: #f9fafb;
    border-right: 0;
}

.form-control.border-start-0 {
    border-left: 0;
}

.form-control.border-start-0:focus {
    box-shadow: none;
    border-color: #dee2e6;
}

.input-group:focus-within .input-group-text {
    border-color: #667eea;
}

.input-group:focus-within .form-control {
    border-color: #667eea;
}

/* Form Labels */
.form-label {
    color: #374151;
    margin-bottom: 0.5rem;
}

/* Info List */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.info-item i {
    margin-top: 0.125rem;
}

/* Role Info */
.role-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.role-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.role-badge-small {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.role-badge-small.role-chairman {
    background: #fef3c7;
    color: #92400e;
}

.role-badge-small.role-treasurer {
    background: #dbeafe;
    color: #1e40af;
}

.role-badge-small.role-secretary {
    background: #e0e7ff;
    color: #3730a3;
}

.role-badge-small.role-member {
    background: #f3f4f6;
    color: #374151;
}

/* Buttons */
.btn {
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-light:hover {
    background-color: #f3f4f6;
}
</style>
@endsection