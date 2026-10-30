@if (!empty($historicalCompliance))
    @php($historic = $historicalCompliance['totals'])
    <section class="card shadow-sm mb-4" aria-label="Historical contribution coverage">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong>Historical contribution coverage — Cycle #{{ $historicalCompliance['cycle_id'] }}</strong>
            <span class="badge text-bg-secondary">Reviewed records</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Obligations covered</div>
                    <div class="fw-semibold">{{ $historic['fully_paid_obligations'] }} / {{ $historic['obligations'] }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Required</div>
                    <div class="fw-semibold">M {{ number_format($historic['required_cents'] / 100, 2) }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Historical + direct coverage</div>
                    <div class="fw-semibold">M {{ number_format($historic['paid_cents'] / 100, 2) }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-muted small">Remaining required</div>
                    <div class="fw-semibold">M {{ number_format($historic['outstanding_cents'] / 100, 2) }}</div>
                </div>
            </div>
            @if ($historic['excess_cents'] > 0)
                <div class="small mt-3">
                    Excess savings attributed to obligations:
                    <strong>M {{ number_format($historic['excess_cents'] / 100, 2) }}</strong>.
                    This remains member savings; it is not a second payment.
                </div>
            @endif
            <p class="text-muted small mt-3 mb-0">
                Verified for the obligations recorded in this historical cycle only.
                No missing-month compliance or late-payment conclusion is implied.
                This panel is separate from any active-cycle financial totals.
            </p>
        </div>
    </section>
@endif
