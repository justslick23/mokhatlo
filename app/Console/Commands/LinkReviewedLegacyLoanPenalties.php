<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyNativePenaltyImporter;
use Illuminate\Console\Command;
use Throwable;

/** Dry-run by default. Never re-assesses historical penalties or posts cash. */
final class LinkReviewedLegacyLoanPenalties extends Command
{
    protected $signature = 'mokhatlo:legacy:penalties-link
        {--society=1 : Society ID}
        {--actor= : Existing society member user ID for evidence/audit}
        {--apply : Perform the link (default preview only)}
        {--confirm= : Must equal LINK-LEGACY-444 when applying}';

    protected $description = 'Safely link the two verified historical loan penalties into native loan_penalties';

    public function handle(LegacyNativePenaltyImporter $importer): int
    {
        $societyId = (int) $this->option('society');
        try {
            $preview = $importer->preview($societyId);
            $this->table(['Loan', 'Reviewed charge', 'Evidence item', 'Native status'],
                array_map(fn ($r) => [
                    $r['loan_number'],
                    'M '.number_format($r['amount_cents'] / 100, 2),
                    '#'.$r['review_id'],
                    $r['linked_penalty_id'] ? 'Linked #'.$r['linked_penalty_id'] : 'Ready to link',
                ], $preview['rows']));

            $this->line('These are already assessed-but-unpaid balances. No fresh interest, payments, ledger lines or cash receipts are generated.');
            if (! $this->option('apply')) {
                $this->info('PREVIEW ONLY. To link, supply --apply --actor=<user-id> --confirm=LINK-LEGACY-444.');
                return self::SUCCESS;
            }
            if ($this->option('confirm') !== 'LINK-LEGACY-444') {
                $this->error('Explicit confirmation mismatch; nothing changed.');
                return self::FAILURE;
            }
            $actor = $this->option('actor');
            if (! is_numeric($actor) || (int) $actor < 1) {
                $this->error('Provide --actor=<real member user ID>; nothing changed.');
                return self::FAILURE;
            }
            $result = $importer->import($societyId, (int) $actor);
            $this->table(['Loan', 'Result', 'Native penalty ID'], array_map(fn ($r) => [
                $r['loan_number'], $r['action'], $r['penalty_id'],
            ], $result));
            $this->info('Native assessed penalties mapped safely. Use the normal loan-penalty payment screen for future actual receipts.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->warn('No new historical penalty links should be assumed. Check the database and rerun the preview.');
            return self::FAILURE;
        }
    }
}
