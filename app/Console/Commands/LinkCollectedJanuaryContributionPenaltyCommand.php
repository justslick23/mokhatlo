<?php

namespace App\Console\Commands;

use App\Services\Legacy\LinkCollectedJanuaryContributionPenalty;
use Illuminate\Console\Command;
use Throwable;

final class LinkCollectedJanuaryContributionPenaltyCommand extends Command
{
    protected $signature = 'mokhatlo:legacy:contribution-penalty-link
        {--society=1 : Society ID}
        {--actor= : Existing society member user ID (apply only)}
        {--paid-on= : Actual original transaction date YYYY-MM-DD; defaults to date in encrypted source plan when available}
        {--method=legacy_import : Original payment method if known; legacy_import means unverified payment channel}
        {--reference=LEGACY-PENALTY-TX-32 : Historic provenance ID, not a claim of a bank receipt}
        {--apply : Perform the native assessment/payment link (default: preview)}
        {--confirm= : Must equal LINK-JAN-50 to apply}';

    protected $description = 'Safely link the reviewed M50 collected January 2026 penalty to contribution obligation #37';

    public function handle(LinkCollectedJanuaryContributionPenalty $linker): int
    {
        try {
            $societyId = (int) $this->option('society');
            $preview = $linker->preview($societyId);
            $this->table(['Review', 'Original tx', 'Obligation', 'Member', 'Amount', 'Evidence', 'Native status'], [[
                '#'.$preview['review_id'],
                '#'.$preview['legacy_transaction_id'],
                '#'.$preview['obligation_id'].' / Jan 2026',
                '#'.$preview['membership_id'],
                'M '.number_format($preview['amount_cents'] / 100, 2),
                $preview['decision'],
                $preview['already_linked'] ? 'Linked penalty #'.$preview['penalty_id'].' / payment #'.$preview['payment_id'] : 'Ready to link',
            ]]);
            $sourceDate = $preview['source_paid_on'];
            $this->line('Original source date: '.($sourceDate ?? 'Not present in encrypted import plan: provide --paid-on from original evidence.'));
            $this->warn('Historical collection only: NO NEW CASH or ledger journal will be posted. Ledger reconciliation gate remains open.');
            if (! $this->option('apply')) {
                $this->info('PREVIEW ONLY. No records changed.');
                return self::SUCCESS;
            }
            if ($this->option('confirm') !== 'LINK-JAN-50') {
                $this->error('Explicit confirmation mismatch; no changes made.');
                return self::FAILURE;
            }
            $actor = $this->option('actor');
            if (! is_numeric($actor) || (int) $actor < 1) {
                $this->error('An existing society user ID is required via --actor=<id>.');
                return self::FAILURE;
            }
            if ($preview['already_linked']) {
                $this->info('This historical penalty/payment is already linked. No changes made.');
                return self::SUCCESS;
            }
            $paidOn = $this->option('paid-on') ?: $sourceDate ?: ($preview['already_linked'] ? $preview['paid_on'] : null);
            if (! $paidOn) {
                $this->error('Original payment date not in imported source. Supply --paid-on=YYYY-MM-DD from actual evidence.');
                return self::FAILURE;
            }
            $result = $linker->apply($societyId, (int) $actor, (string) $paidOn,
                (string) $this->option('reference'), (string) $this->option('method'));
            $this->info($result['status'].': native contribution penalty #'.$result['penalty_id']
                .', historical payment #'.$result['payment_id'].'.');
            $this->warn('Still requires independent reconciliation of the M50 against previously verified cash/ledger. Do not post fresh cash automatically.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->warn('Operation did not complete. Review existing native penalty and source evidence before retrying.');
            return self::FAILURE;
        }
    }
}
