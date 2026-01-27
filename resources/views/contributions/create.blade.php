@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="page-header mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="{{ route('societies.dashboard', $society) }}">{{ $society->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('societies.contributions.index', $society) }}">Contributions</a></li>
                <li class="breadcrumb-item active">Record Contribution</li>
            </ol>
        </nav>
        <h2 class="mb-2 fw-bold">Record Contribution</h2>
        <p class="text-muted mb-0">Add a new member contribution to the society</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="fas fa-coins text-primary me-2"></i>Contribution Details
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('societies.contributions.store', $society) }}">
                        @csrf

                        <div class="form-section mb-4">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-user text-muted me-2"></i>Member & Amount
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">
                                        Select Member <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-user-circle text-muted"></i>
                                        </span>
                                        <select name="member_id" class="form-control border-start-0" required>
                                            <option value="">Choose a member...</option>
                                            @foreach($members as $member)
                                                <option value="{{ $member->id }}">
                                                    {{ $member->user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Amount (M) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-money-bill-wave text-success"></i>
                                        </span>
                                        <input 
                                            type="number" 
                                            name="amount" 
                                            class="form-control border-start-0 fw-bold" 
                                            min="1" 
                                            step="0.01"
                                            placeholder="0.00"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Contribution Date <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-calendar-day text-muted"></i>
                                        </span>
                                        <input 
                                            type="date" 
                                            name="date" 
                                            class="form-control border-start-0" 
                                            value="{{ now()->toDateString() }}"
                                            required
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-sticky-note text-muted me-2"></i>Additional Information
                            </h6>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Notes (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 align-items-start pt-2">
                                        <i class="fas fa-pen text-muted"></i>
                                    </span>
                                    <textarea 
                                        name="notes" 
                                        class="form-control border-start-0" 
                                        rows="3"
                                        placeholder="Add any additional notes about this contribution..."
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4 pt-4 border-top">
                            <button type="submit" class="btn btn-success btn-lg px-4">
                                <i class="fas fa-save me-2"></i>Save Contribution
                            </button>
                            <a href="{{ route('societies.contributions.index', $society) }}" class="btn btn-light btn-lg px-4">
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
                        <i class="fas fa-info-circle text-primary me-2"></i>Minimum Contribution
                    </h6>
                    <div class="minimum-display">
                        <div class="minimum-value">M {{ number_format($society->minimum_contribution, 2) }}</div>
                        <div class="minimum-label">Per member per cycle</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-lightbulb text-warning me-2"></i>Quick Tips
                    </h6>
                    <div class="info-list">
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Ensure the date is accurate</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Add notes for special contributions</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Contributions are tracked per cycle</span>
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