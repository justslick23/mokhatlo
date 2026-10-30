{{-- Reviewed historical loan penalties. Native amounts are NOT added again to any separate native penalty card. --}}
@isset($loan)
    @inject('legacyPenaltyVisibility', 'App\Services\Legacy\LegacyLoanPenaltyVisibility')
    @php($legacyPenalty = $legacyPenaltyVisibility->forLoan($loan))
    @if($legacyPenalty['documented_historical_penalty_cents'] > 0 || $legacyPenalty['native_historical_penalty_cents'] > 0)
        <div class="card border-warning my-3">
            <div class="card-header fw-semibold">Historical loan penalty balance</div>
            <div class="card-body">
                <dl class="row mb-2">
                    <dt class="col-sm-7">Outstanding principal + contractual interest</dt>
                    <dd class="col-sm-5 text-sm-end">M {{ number_format($legacyPenalty['contractual_cents'] / 100, 2) }}</dd>
                    @if($legacyPenalty['documented_historical_penalty_cents'] > 0)
                        <dt class="col-sm-7">Documented penalty (not yet in native subledger)</dt>
                        <dd class="col-sm-5 text-sm-end">M {{ number_format($legacyPenalty['documented_historical_penalty_cents'] / 100, 2) }}</dd>
                    @endif
                    @if($legacyPenalty['native_historical_penalty_cents'] > 0)
                        <dt class="col-sm-7">Native historical penalty outstanding</dt>
                        <dd class="col-sm-5 text-sm-end">M {{ number_format($legacyPenalty['native_historical_penalty_cents'] / 100, 2) }}</dd>
                    @endif
                    <dt class="col-sm-7">Total including historical penalty</dt>
                    <dd class="col-sm-5 text-sm-end fw-semibold">M {{ number_format($legacyPenalty['combined_cents'] / 100, 2) }}</dd>
                </dl>
                @if($legacyPenalty['subledger_pending'])
                    <p class="text-muted small mb-0">Pending evidence-to-native subledger link. Do not charge it again.</p>
                @else
                    <p class="text-muted small mb-0">The reviewed penalty is linked to the native loan-penalty subledger. Record actual future penalty payments through the existing Loan Penalties workflow, not as new loan principal or contractual interest.</p>
                @endif
            </div>
        </div>
    @endif
@endisset
