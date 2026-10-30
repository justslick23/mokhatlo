<?php

namespace App\Services\Legacy;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Brings forward assessed-but-unpaid historical charges into LoanPenalty.
 * Does not post cash, revenue, repayments, ledger journals or waive debt.
 * Transactions are anchored to immutable review item IDs and two unique links.
 */
final class LegacyNativePenaltyImporter
{
    private const EXPECTED = ['LEG-2-3' => 18000, 'LEG-2-5' => 26400];

    /** @return array{rows:array<int,array<string,mixed>>,ready:bool} */
    public function preview(int $societyId): array
    {
        $this->assertTables();
        $this->validateNativeSchema();
        $rows = $this->readTargets($societyId);
        if (count($rows) !== 2) {
            throw new RuntimeException('Expected precisely two evidence-backed legacy charges on loans LEG-2-3 and LEG-2-5 for this society. Found '.count($rows).'. No changes made.');
        }
        foreach ($rows as $row) {
            $this->validateTarget($row);
            if ($row['linked_penalty_id'] === null
                && DB::table('loan_penalties')->where('society_id', $societyId)
                    ->where('loan_id', $row['loan_id'])->exists()) {
                throw new RuntimeException('Loan '.$row['loan_number'].' already has native penalties. Stop and reconcile manually to avoid double billing.');
            }
        }

        return ['rows' => $rows, 'ready' => true];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function import(int $societyId, int $actorId): array
    {
        $this->assertTables();
        $this->validateNativeSchema();

        return DB::transaction(function () use ($societyId, $actorId) {
            $society = DB::table('societies')->where('id', $societyId)
                ->lockForUpdate()->first();
            if (! $society) {
                throw new RuntimeException('Society not found.');
            }
            if (! DB::table('users')->where('id', $actorId)->exists()
                || ! DB::table('society_memberships')
                    ->where('society_id', $societyId)
                    ->where('user_id', $actorId)
                    ->exists()) {
                throw new RuntimeException('Actor must be an existing member of this society.');
            }

            $rows = $this->readTargets($societyId);
            if (count($rows) !== 2) {
                throw new RuntimeException('Expected two reviewed loan charges; found '.count($rows).'. No changes made.');
            }

            $results = [];
            foreach ($rows as $row) {
                // Under the society-wide transaction lock, recheck each item.
                $item = DB::table('legacy_financial_review_items')
                    ->where('id', $row['review_id'])->lockForUpdate()->first();
                if (! $item || $item->resolution_status !== 'resolved') {
                    throw new RuntimeException('Review item changed during integration.');
                }
                $latest = DB::table('legacy_penalty_review_actions')
                    ->where('review_item_id', $row['review_id'])
                    ->orderByDesc('id')->lockForUpdate()->first();
                if (! $latest || $latest->decision !== 'outstanding_documented'
                    || (int) $latest->id !== $row['review_action_id']) {
                    throw new RuntimeException('Review evidence changed; run preview again.');
                }

                $this->validateTarget($row);
                if ($row['linked_penalty_id'] !== null) {
                    $results[] = ['loan_number' => $row['loan_number'], 'action' => 'already_linked', 'penalty_id' => $row['linked_penalty_id']];
                    continue;
                }

                $native = DB::table('loan_penalties')
                    ->where('society_id', $societyId)
                    ->where('loan_id', $row['loan_id'])
                    ->lockForUpdate()->get();
                if ($native->isNotEmpty()) {
                    throw new RuntimeException('Loan '.$row['loan_number'].' already has native penalty rows. Manual reconciliation required before importing any additional charge.');
                }

                $data = $this->nativeRow($row);
                $penaltyId = (int) DB::table('loan_penalties')->insertGetId($data);
                DB::table('legacy_imported_loan_penalties')->insert([
                    'review_item_id' => $row['review_id'],
                    'society_id' => $societyId,
                    'loan_id' => $row['loan_id'],
                    'loan_penalty_id' => $penaltyId,
                    'amount_cents' => $row['amount_cents'],
                    'evidence_reference' => $row['evidence_reference'],
                    'imported_by' => $actorId,
                    'imported_at' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $results[] = ['loan_number' => $row['loan_number'], 'action' => 'linked', 'penalty_id' => $penaltyId];
            }

            return $results;
        }, 3);
    }

    private function assertTables(): void
    {
        foreach (['loan_penalties', 'legacy_financial_import_runs', 'legacy_financial_review_items',
            'legacy_penalty_review_actions', 'legacy_imported_loan_penalties', 'loans'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Missing table {$table}; run php artisan migrate first.");
            }
        }
    }

    /**
     * Read only the two *reviewed outstanding balances*, not the other three
     * legacy penalty transactions whose collection status is distinct.
     */
    private function readTargets(int $societyId): array
    {
        $rows = DB::table('legacy_financial_review_items as i')
            ->join('legacy_financial_import_runs as r', 'r.id', '=', 'i.legacy_financial_import_run_id')
            ->join('loans as l', 'l.id', '=', 'i.loan_id')
            ->leftJoin('legacy_imported_loan_penalties as m', 'm.review_item_id', '=', 'i.id')
            ->where('r.society_id', $societyId)
            ->where('l.society_id', $societyId)
            ->whereColumn('l.cycle_id', 'r.cycle_id')
            ->where('i.source_table', 'loans')
            ->where('i.reason', 'outstanding_penalty_unverified')
            ->whereIn('l.loan_number', array_keys(self::EXPECTED))
            ->select(['i.id as review_id', 'i.resolution_status', 'i.amount_cents',
                'l.id as loan_id', 'l.loan_number', 'l.status as loan_status',
                'l.matures_on', 'r.id as run_id', 'm.loan_penalty_id as linked_penalty_id',
                'm.amount_cents as linked_amount_cents', 'm.loan_id as linked_loan_id'])
            ->orderBy('l.loan_number')->get();

        return $rows->map(function ($row) {
            $action = DB::table('legacy_penalty_review_actions')
                ->where('review_item_id', $row->review_id)
                ->orderByDesc('id')->first();
            return [
                'review_id' => (int) $row->review_id,
                'run_id' => (int) $row->run_id,
                'resolution_status' => (string) $row->resolution_status,
                'amount_cents' => (int) $row->amount_cents,
                'loan_id' => (int) $row->loan_id,
                'loan_number' => (string) $row->loan_number,
                'loan_status' => (string) $row->loan_status,
                'matures_on' => $row->matures_on ? (string) $row->matures_on : null,
                'review_action_id' => $action ? (int) $action->id : null,
                'decision' => $action?->decision,
                'reviewed_at' => $action?->created_at,
                'evidence_reference' => (string) ($action?->evidence_reference ?? ''),
                'linked_penalty_id' => $row->linked_penalty_id === null ? null : (int) $row->linked_penalty_id,
                'linked_amount_cents' => $row->linked_amount_cents === null ? null : (int) $row->linked_amount_cents,
                'linked_loan_id' => $row->linked_loan_id === null ? null : (int) $row->linked_loan_id,
            ];
        })->all();
    }

    private function validateTarget(array $row): void
    {
        $expected = self::EXPECTED[$row['loan_number']] ?? null;
        if ($expected === null || $row['amount_cents'] !== $expected) {
            throw new RuntimeException('Unexpected amount for '.$row['loan_number'].'; expected reviewed M '.number_format($expected / 100, 2).'.');
        }
        if ($row['resolution_status'] !== 'resolved'
            || $row['decision'] !== 'outstanding_documented'
            || $row['review_action_id'] === null
            || strlen($row['evidence_reference']) < 5) {
            throw new RuntimeException('Loan '.$row['loan_number'].' lacks a current, resolved, evidence-backed outstanding decision.');
        }
        if ($row['loan_status'] !== 'disbursed') {
            throw new RuntimeException('Loan '.$row['loan_number'].' is not disbursed; inspect its current status before posting a penalty.');
        }
        if ($row['linked_penalty_id'] !== null) {
            if ($row['linked_loan_id'] !== $row['loan_id']
                || $row['linked_amount_cents'] !== $row['amount_cents']) {
                throw new RuntimeException('Existing legacy/native penalty link has inconsistent loan or amount.');
            }
            $native = DB::table('loan_penalties')->where('id', $row['linked_penalty_id'])
                ->where('loan_id', $row['loan_id'])->first();
            if (! $native || Money::toCents((string) $native->assessed_amount) !== $row['amount_cents']) {
                throw new RuntimeException('Existing native penalty is missing or mismatched; do not post a duplicate.');
            }
        }
    }

    /** Abort rather than guess if the installed native schema is different. */
    private function validateNativeSchema(): void
    {
        $columns = collect(Schema::getColumns('loan_penalties'))->keyBy('name');
        foreach (['society_id', 'loan_id', 'base_amount', 'rate_percent', 'assessed_amount',
            'cutoff_date', 'assessed_on', 'calculation_method'] as $name) {
            if (! $columns->has($name)) {
                throw new RuntimeException("Native loan_penalties schema lacks {$name}; no data will be written. Provide the migration for a targeted adapter.");
            }
        }
        foreach ($columns as $name => $meta) {
            if (in_array($name, ['id','society_id','loan_id','loan_installment_id','base_amount','rate_percent',
                'assessed_amount','cutoff_date','assessed_on','calculation_method',
                'assessment_scope','maturity_slot','created_at','updated_at'], true)) {
                continue;
            }
            if (! ($meta['nullable'] ?? false) && ($meta['default'] ?? null) === null
                && ! ($meta['auto_increment'] ?? false)) {
                throw new RuntimeException("Unrecognised required loan_penalties column {$name}; no data will be written.");
            }
        }
        foreach (['calculation_method', 'assessment_scope'] as $field) {
            if (! $columns->has($field)) continue;
            $type = strtolower((string) ($columns[$field]['type'] ?? ''));
            if (str_contains($type, 'enum(')) {
                $token = $field === 'calculation_method' ? 'historical_fixed' : 'legacy_import';
                if (! str_contains($type, "'{$token}'")) {
                    throw new RuntimeException("Native {$field} is enum without '{$token}'. No data written; supply current native migration to choose supported value.");
                }
            }
        }
        if ($columns->has('maturity_slot')) {
            $type = strtolower((string) ($columns['maturity_slot']['type'] ?? ''));
            if (str_contains($type, 'date') || str_contains($type, 'time') || str_contains($type, 'enum')) {
                throw new RuntimeException('maturity_slot has a date/enum type; no safe historical slot mapping inferred. Provide the schema.');
            }
        }
    }

    private function nativeRow(array $row): array
    {
        $cols = collect(Schema::getColumns('loan_penalties'))->keyBy('name');
        // These are fixed opening balances supported by reviewed evidence, NOT
        // an invented new percentage assessment. The 0% is a sentinel showing
        // that no retrospective rate calculation took place.
        $date = substr((string) $row['reviewed_at'], 0, 10);
        $data = [
            'society_id' => (int) DB::table('loans')->where('id', $row['loan_id'])->value('society_id'),
            'loan_id' => $row['loan_id'],
            'base_amount' => Money::fromCents($row['amount_cents']),
            'rate_percent' => '0.00',
            'assessed_amount' => Money::fromCents($row['amount_cents']),
            // Date of evidence-based recognition; not an invented original assessment date.
            'cutoff_date' => $date,
            'assessed_on' => $date,
            'calculation_method' => 'historical_fixed',
            'created_at' => now(), 'updated_at' => now(),
        ];
        if ($cols->has('loan_installment_id')) $data['loan_installment_id'] = null;
        if ($cols->has('assessment_scope')) $data['assessment_scope'] = 'legacy_import';
        if ($cols->has('maturity_slot')) {
            $type = strtolower((string) ($cols['maturity_slot']['type'] ?? ''));
            $data['maturity_slot'] = preg_match('/int|decimal|numeric/', $type)
                ? 0 : 'legacy-review-'.$row['review_id'];
        }
        return $data;
    }
}
