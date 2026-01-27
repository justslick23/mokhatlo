@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- Enhanced Header -->
    <div class="page-header mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="{{ route('societies.dashboard', $society) }}">{{ $society->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('societies.loans.index', $society) }}">Loans</a></li>
                <li class="breadcrumb-item active">Issue Loan</li>
            </ol>
        </nav>
        <h2 class="mb-2 fw-bold">Issue New Loan</h2>
        <p class="text-muted mb-0">Create a new loan for a society member</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Main Form Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="fas fa-hand-holding-usd text-primary me-2"></i>Loan Details
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('societies.loans.store', $society) }}">
                        @csrf

                        <!-- Member Selection -->
                        <div class="form-section mb-4">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-user text-muted me-2"></i>Borrower Information
                            </h6>
                            <div class="mb-3">
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
                        </div>

                        <!-- Loan Amount Section -->
                        <div class="form-section mb-4">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-calculator text-muted me-2"></i>Loan Calculations
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">
                                        Principal Amount (M) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-money-bill-wave text-success"></i>
                                        </span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            name="principal"
                                            id="principal"
                                            class="form-control border-start-0 fw-bold"
                                            placeholder="0.00"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Interest ({{ $society->interest_rate }}%)</label>
                                    <div class="calculation-display">
                                        <div class="calculation-icon">
                                            <i class="fas fa-percent"></i>
                                        </div>
                                        <div class="calculation-value">
                                            <span class="currency">M</span>
                                            <span id="interest">0.00</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Total Repayable</label>
                                    <div class="calculation-display calculation-total">
                                        <div class="calculation-icon">
                                            <i class="fas fa-coins"></i>
                                        </div>
                                        <div class="calculation-value">
                                            <span class="currency">M</span>
                                            <span id="total">0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="form-section">
                            <h6 class="section-title mb-3">
                                <i class="fas fa-info-circle text-muted me-2"></i>Additional Details
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Due Date <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="fas fa-calendar-alt text-muted"></i>
                                        </span>
                                        <input 
                                            type="date" 
                                            name="due_date" 
                                            class="form-control border-start-0" 
                                            min="{{ now()->toDateString() }}"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Loan Purpose</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 align-items-start pt-2">
                                            <i class="fas fa-sticky-note text-muted"></i>
                                        </span>
                                        <textarea 
                                            name="purpose" 
                                            class="form-control border-start-0" 
                                            rows="3"
                                            placeholder="Enter the reason for this loan (optional)..."
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 mt-4 pt-4 border-top">
                            <button type="submit" class="btn btn-success btn-lg px-4">
                                <i class="fas fa-check-circle me-2"></i>Issue Loan
                            </button>
                            <a href="{{ route('societies.loans.index', $society) }}" class="btn btn-light btn-lg px-4">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Interest Rate Info -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-percent text-warning me-2"></i>Society Interest Rate
                    </h6>
                    <div class="interest-display">
                        <div class="interest-value">{{ $society->interest_rate }}%</div>
                        <div class="interest-label">Applied to all loans</div>
                    </div>
                </div>
            </div>

            <!-- Quick Guide -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-lightbulb text-info me-2"></i>Loan Guidelines
                    </h6>
                    <div class="info-list">
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Principal amount will be automatically calculated</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Set reasonable due dates for repayment</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>Document the loan purpose for records</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Example Calculation -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">
                        <i class="fas fa-calculator text-primary me-2"></i>Example Calculation
                    </h6>
                    <div class="example-calc">
                        <div class="calc-row">
                            <span>Principal:</span>
                            <span class="fw-bold">M 1,000.00</span>
                        </div>
                        <div class="calc-row">
                            <span>Interest ({{ $society->interest_rate }}%):</span>
                            <span class="fw-bold text-warning">M {{ number_format(1000 * $society->interest_rate / 100, 2) }}</span>
                        </div>
                        <div class="calc-row calc-total">
                            <span>Total:</span>
                            <span class="fw-bold text-success">M {{ number_format(1000 + (1000 * $society->interest_rate / 100), 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const rate = {{ $society->interest_rate }};

    document.getElementById('principal').addEventListener('input', function () {
        const principal = parseFloat(this.value) || 0;
        const interest  = (principal * rate / 100);
        const total     = (principal + interest);

        document.getElementById('interest').textContent = interest.toFixed(2);
        document.getElementById('total').textContent = total.toFixed(2);
    });
</script>

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

/* Calculation Display */
.calculation-display {
    background: linear-gradient(135deg, #f9fafb, #f3f4f6);
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.calculation-display.calculation-total {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    border-color: #667eea;
}

.calculation-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #667eea;
    flex-shrink: 0;
}

.calculation-total .calculation-icon {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.calculation-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.currency {
    font-size: 1rem;
    color: #6b7280;
    font-weight: 600;
}

/* Interest Display */
.interest-display {
    background: linear-gradient(135deg, rgba(254, 202, 87, 0.1), rgba(248, 181, 0, 0.1));
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.interest-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #f8b500;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.interest-label {
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

/* Example Calculation */
.example-calc {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.calc-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    font-size: 0.875rem;
}

.calc-row.calc-total {
    border-top: 2px solid #e5e7eb;
    padding-top: 0.75rem;
    margin-top: 0.5rem;
    font-size: 1rem;
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
@endsection