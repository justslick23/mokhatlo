<?php

namespace App\Services\Legacy;

use App\Models\Society;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Evidence review + independently authorised clearing journal.
 * Does NOT manufacture collected penalties, waive loans or forgive debt.
 */
final class LegacyResolutionActions
{
    public function reviewPenalty(Society $society, int $itemId, int $actorId,
        string $decision, string $reference, string $notes): void
    {
        DB::transaction(function () use ($society, $itemId, $actorId, $decision, $reference, $notes) {
            DB::table('societies')->where('id', $society->id)->lockForUpdate()->first();
            $item = DB::table('legacy_financial_review_items as i')
                ->join('legacy_financial_import_runs as r', 'r.id', '=', 'i.legacy_financial_import_run_id')
                ->where('i.id', $itemId)->where('r.society_id', $society->id)
                ->select('i.*')->lockForUpdate()->first();
            if (! $item) throw new RuntimeException('Review item is not in this society.');
            if (Schema::hasTable('legacy_imported_loan_penalties')
                && DB::table('legacy_imported_loan_penalties')->where('review_item_id', $itemId)->exists()) {
                throw new RuntimeException('This reviewed charge is already in the native penalty subledger. Do not reclassify it; use an audited reversal process.');
            }
            if (Schema::hasTable('legacy_imported_contribution_penalties')
                && DB::table('legacy_imported_contribution_penalties')->where('review_item_id', $itemId)->exists()) {
                throw new RuntimeException('This historical collected contribution penalty has been linked to a native assessment/payment. Use a documented reversal, not another review classification.');
            }
            $allowed = $item->source_table === 'loans'
                ? ['outstanding_documented', 'disputed', 'not_due_or_void']
                : ['assessed_only', 'collected_documented', 'not_collected', 'duplicate_void', 'disputed'];
            if (! in_array($decision, $allowed, true)) throw new RuntimeException('Invalid decision for this source record.');
            if (mb_strlen(trim($reference)) < 5 || mb_strlen(trim($notes)) < 10) {
                throw new RuntimeException('Evidence reference and detailed review notes are required.');
            }
            DB::table('legacy_penalty_review_actions')->insert([
                'review_item_id' => $itemId, 'decision' => $decision,
                'evidence_reference' => trim($reference), 'notes' => trim($notes),
                'reviewed_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('legacy_financial_review_items')->where('id', $itemId)->update([
                'resolution_status' => $decision === 'disputed' ? 'pending' : 'resolved',
                'resolution_notes' => "Decision: {$decision}; evidence reference: {$reference}; {$notes}",
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function prepareClearing(Society $society, int $runId, int $actorId,
        array $amounts, string $reference, string $notes): void
    {
        $cash = Money::toCents((string) $amounts['cash']);
        $bank = Money::toCents((string) $amounts['bank']);
        $mobile = Money::toCents((string) $amounts['mobile']);
        if ($cash < 0 || $bank < 0 || $mobile < 0) throw new RuntimeException('Negative asset balances are not allowed.');
        if (mb_strlen(trim($reference)) < 5 || mb_strlen(trim($notes)) < 10) {
            throw new RuntimeException('A real statement reference and detailed evidence notes are required.');
        }
        DB::transaction(function () use ($society, $runId, $actorId, $cash, $bank, $mobile, $reference, $notes) {
            DB::table('societies')->where('id', $society->id)->lockForUpdate()->first();
            $run = DB::table('legacy_financial_import_runs')->where('id', $runId)
                ->where('society_id', $society->id)->lockForUpdate()->first();
            if (! $run) throw new RuntimeException('Import run not found.');
            $s = json_decode($run->posted_totals, true, 512, JSON_THROW_ON_ERROR);
            $expected = (int) $s['contributions_cents'] + (int) $s['repayments_cents'] - (int) $s['disbursements_cents'];
            if ($expected < 0) throw new RuntimeException('Negative clearing expected. Requires separate reviewed journal.');
            $existing = DB::table('legacy_clearing_reconciliations')
                ->where('import_run_id', $runId)->lockForUpdate()->first();
            if ($existing && $existing->status !== 'prepared') {
                throw new RuntimeException('Approved or posted reconciliation cannot be edited.');
            }
            $data = [
                'society_id' => $society->id, 'expected_cents' => $expected,
                'cash_cents' => $cash, 'bank_cents' => $bank, 'mobile_cents' => $mobile,
                'evidence_reference' => trim($reference), 'notes' => trim($notes),
                'status' => 'prepared', 'prepared_by' => $actorId,
                'prepared_at' => now(), 'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('legacy_clearing_reconciliations')->where('id', $existing->id)->update($data);
            } else {
                DB::table('legacy_clearing_reconciliations')->insert([
                    ...$data, 'import_run_id' => $runId, 'created_at' => now(),
                ]);
            }
        }, 3);
    }

    public function approveClearing(Society $society, int $runId, int $actorId): void
    {
        DB::transaction(function () use ($society, $runId, $actorId) {
            DB::table('societies')->where('id', $society->id)->lockForUpdate()->first();
            $r = DB::table('legacy_clearing_reconciliations')
                ->where('society_id', $society->id)->where('import_run_id', $runId)
                ->lockForUpdate()->first();
            if (! $r || $r->status !== 'prepared') throw new RuntimeException('No prepared reconciliation.');
            if ((int) $r->prepared_by === $actorId) throw new RuntimeException('Independent approval must be by another user.');
            $sum = (int) $r->cash_cents + (int) $r->bank_cents + (int) $r->mobile_cents;
            if ($sum !== (int) $r->expected_cents) {
                throw new RuntimeException('Evidence balances do not equal import clearing amount; resolve variance before approving.');
            }
            DB::table('legacy_clearing_reconciliations')->where('id', $r->id)->update([
                'status' => 'approved', 'approved_by' => $actorId,
                'approved_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function postClearing(Society $society, int $runId, int $actorId): void
    {
        DB::transaction(function () use ($society, $runId, $actorId) {
            DB::table('societies')->where('id', $society->id)->lockForUpdate()->first();
            $r = DB::table('legacy_clearing_reconciliations')
                ->where('society_id', $society->id)->where('import_run_id', $runId)
                ->lockForUpdate()->first();
            if (! $r || $r->status !== 'approved') throw new RuntimeException('Clearing needs independent approval before posting.');
            if ($r->ledger_transaction_id) throw new RuntimeException('Clearing already posted.');
            if ((int) $r->prepared_by === (int) $r->approved_by) throw new RuntimeException('Approver must be independent.');
            $existing = DB::table('ledger_transactions')->where('society_id', $society->id)
                ->where('source_type', 'legacy_clearing_reconciliation')->where('source_id', $r->id)->exists();
            if ($existing) throw new RuntimeException('Duplicate clearing posting detected.');
            $accounts = DB::table('ledger_accounts')->where('society_id', $society->id)
                ->whereIn('code', ['LEGACY_IMPORT_CLEARING','CASH_ON_HAND','BANK_ACCOUNT','MOBILE_MONEY'])
                ->lockForUpdate()->get()->keyBy('code');
            foreach (['LEGACY_IMPORT_CLEARING','CASH_ON_HAND','BANK_ACCOUNT','MOBILE_MONEY'] as $code) {
                $a = $accounts->get($code);
                if (! $a || $a->type !== 'asset' || ! $a->is_active) {
                    throw new RuntimeException("Asset account {$code} is missing/inactive or has the wrong type.");
                }
            }
            // Conservative double-counting protection. If any verified asset
            // account already carries a ledger balance, a full opening cash
            // reclassification could count funds twice. Stop for manual review.
            foreach (['CASH_ON_HAND', 'BANK_ACCOUNT', 'MOBILE_MONEY'] as $code) {
                $assetId = $accounts[$code]->id;
                $existingAssetNet = DB::table('ledger_lines')
                    ->where('ledger_account_id', $assetId)
                    ->get(['debit_amount', 'credit_amount'])
                    ->sum(fn ($l) => Money::toCents($l->debit_amount) - Money::toCents($l->credit_amount));
                if ($existingAssetNet !== 0) {
                    throw new RuntimeException("{$code} already has ledger activity/net balance; reconcile existing assets before moving historical clearing to avoid double-counting.");
                }
            }
            $clearingId = $accounts['LEGACY_IMPORT_CLEARING']->id;
            // Verify CURRENT society-wide net clearing, not only original cycle's lines.
            $ledgerBalance = DB::table('ledger_lines')
                ->where('ledger_account_id', $clearingId)
                ->get(['debit_amount','credit_amount'])
                ->sum(fn ($l) => Money::toCents($l->debit_amount) - Money::toCents($l->credit_amount));
            if ($ledgerBalance !== (int) $r->expected_cents) {
                throw new RuntimeException('Live clearing account balance differs from prepared reconciliation.');
            }
            $sum = (int) $r->cash_cents + (int) $r->bank_cents + (int) $r->mobile_cents;
            if ($sum !== (int) $r->expected_cents || $sum <= 0) {
                throw new RuntimeException('Clearing distribution is not fully evidenced.');
            }
            // Keep historical import journals immutable. This posting must
            // have NULL cycle_id so the original 65-journal audit remains scoped.
            $cycleColumn = collect(Schema::getColumns('ledger_transactions'))
                ->firstWhere('name', 'cycle_id');
            if (! $cycleColumn || ! ($cycleColumn['nullable'] ?? false)) {
                throw new RuntimeException('ledger_transactions.cycle_id must allow NULL for the society-wide clearing reclassification. Review your schema before proceeding.');
            }
            // Cycle NULL: reclassification of verified opening cash, NOT another
            // source transaction for the historical cycle (original 65 remain auditable).
            $txId = DB::table('ledger_transactions')->insertGetId([
                'society_id' => $society->id, 'cycle_id' => null,
                'source_type' => 'legacy_clearing_reconciliation', 'source_id' => $r->id,
                'posting_date' => now($society->timezone ?: 'Africa/Maseru')->toDateString(),
                'description' => 'Independent legacy cash reconciliation; evidence: ' . $r->evidence_reference,
                'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (['CASH_ON_HAND' => 'cash_cents', 'BANK_ACCOUNT' => 'bank_cents', 'MOBILE_MONEY' => 'mobile_cents'] as $code => $column) {
                $value = (int) $r->{$column};
                if ($value === 0) continue;
                DB::table('ledger_lines')->insert([
                    'ledger_transaction_id' => $txId,
                    'ledger_account_id' => $accounts[$code]->id,
                    'debit_amount' => Money::fromCents($value), 'credit_amount' => '0.00',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('ledger_lines')->insert([
                'ledger_transaction_id' => $txId,
                'ledger_account_id' => $clearingId,
                'debit_amount' => '0.00', 'credit_amount' => Money::fromCents($sum),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('legacy_clearing_reconciliations')->where('id', $r->id)->update([
                'status' => 'posted', 'ledger_transaction_id' => $txId,
                'posted_by' => $actorId, 'posted_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }
}
