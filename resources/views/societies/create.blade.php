@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="page-header mb-4">
        <h2 class="mb-2 fw-bold">Create New Society</h2>
        <p class="text-muted mb-0">Set up a new savings and loan society</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="fas fa-users text-primary me-2"></i>Society Configuration
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('societies.store') }}">
                        @csrf

                        <div class="form-section mb-4">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-info-circle text-muted me-2"></i>Basic Information
                            </h6>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    Society Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-users text-muted"></i>
                                    </span>
                                    <input 
                                        type="text" 
                                        name="name" 
                                        class="form-control border-start-0 fw-semibold" 
                                        placeholder="Enter society name..."
                                        required
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="form-section mb-4">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-cog text-muted me-2"></i>Financial Settings
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Minimum Contribution (M) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-coins text-success"></i>
                                        </span>
                                        <input 
                                            type="number" 
                                            step="0.01" 
                                            name="minimum_contribution" 
                                            class="form-control border-start-0" 
                                            placeholder="0.00"
                                            required
                                        >
                                    </div>
                                    <small class="text-muted">Minimum amount per member per cycle</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Interest Rate (%) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-percent text-warning"></i>
                                        </span>
                                        <input 
                                            type="number" 
                                            step="0.01" 
                                            name="interest_rate" 
                                            class="form-control border-start-0" 
                                            placeholder="0.00"
                                            required
                                        >
                                    </div>
                                    <small class="text-muted">Applied to all loans</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-calendar-alt text-muted me-2"></i>First Savings Cycle
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">
                                        Cycle Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-tag text-muted"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="cycle_name"
                                            class="form-control border-start-0"
                                            placeholder="e.g. 2026 Savings Cycle"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Start Date <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-play-circle text-muted"></i>
                                        </span>
                                        <input 
                                            type="date" 
                                            name="cycle_start_date" 
                                            class="form-control border-start-0" 
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        End Date <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-stop-circle text-muted"></i>
                                        </span>
                                        <input 
                                            type="date" 
                                            name="cycle_end_date" 
                                            class="form-control border-start-0" 
                                            required
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4 pt-4 border-top">
                            <button type="submit" class="btn btn-success btn-lg px-4">
                                <i class="fas fa-check-circle me-2"></i>Create Society
                            </button>
                            <a href="{{ route('societies.index') }}" class="btn btn-light btn-lg px-4">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-lightbulb text-warning me-2"></i>Getting Started
                    </h6>
                    <div class="info-list">
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Choose a descriptive society name</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Set reasonable contribution amounts</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Define fair interest rates</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Plan your savings cycles carefully</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-question-circle text-info me-2"></i>What's Next?
                    </h6>
                    <div class="next-steps">
                        <div class="step-item">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <div class="step-title">Add Members</div>
                                <small class="text-muted">Invite people to join</small>
                            </div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <div class="step-title">Record Contributions</div>
                                <small class="text-muted">Track member savings</small>
                            </div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <div class="step-title">Manage Loans</div>
                                <small class="text-muted">Issue and track loans</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
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

/* Minimum/Stat Display */
.minimum-display, .stat-display {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.minimum-value, .stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #667eea;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.minimum-label, .stat-text {
    font-size: 0.875rem;
    color: #6b7280;
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

/* Next Steps */
.next-steps {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.step-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.step-number {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
}

.step-title {
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.125rem;
}

/* Buttons */
.btn {
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-success {
    background: linear-gradient(135deg, #1dd1a1, #10ac84);
    border: none;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(29, 209, 161, 0.4);
}

.btn-light:hover {
    background-color: #f3f4f6;
}
</style>