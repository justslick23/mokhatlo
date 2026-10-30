<?php

namespace App\Services\Legacy;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Links one already-collected historical M50 penalty to the existing January
 * obligation. This creates a native assessment and its historic payment, but
 * deliberately does NOT create a cash/ledger posting (which needs separate
 * independent reconciliation after the original M11,790 clearing reclass).
 *
 * A dry run is always available; apply is transactional, source-linked,
 * idempotent, and intentionally refuses to guess the payment date or method.
 */
final class LinkCollectedJanuaryContributionPenalty
{
    private const SOCIETY = 1;
    private const CYCLE = 2;
    private const REVIEW = 3;
    private const LEGACY_TRANSACTION = '32';
    private const MEMBERSHIP = 3;
    private const OBLIGATION = 37;
    private const AMOUNT_CENTS = 5000;

    public function preview(int $societyId): array
    {
        $this->validateTables();
        $record = $this->loadAndCheck($societyId);
        $this->checkExisting($record);
        return $record;
    }

    /** @return array{status:string,penalty_id:int,payment_id:int} */
    public function apply(int $societyId, int $actorId, string $paidOn, string $paymentReference, string $paymentMethod): array
    {
        $this->validateOriginalPaymentDate($paidOn);
        if (! in_array($paymentMethod, ['cash', 'bank_transfer', 'mobile_money', 'legacy_import'], true)) {
            throw new RuntimeException('Invalid payment method. Choose cash, bank_transfer, mobile_money, or legacy_import (if original method unknown).');
        }
        if (strlen(trim($paymentReference)) < 5 || strlen($paymentReference) > 120) {
            throw new RuntimeException('Use the real source transaction/evidence reference (5-120 characters).');
        }
        $this->validateTables();
        return DB::transaction(function () use ($societyId, $actorId, $paidOn, $paymentReference, $paymentMethod) {
            $society = DB::table('societies')->where('id', $societyId)->lockForUpdate()->first();
            if (! $society) throw new RuntimeException('Society does not exist.');
            $actor = DB::table('users')->where('id', $actorId)->first();
            if (! $actor || ! DB::table('society_memberships')
                ->where('society_id', $societyId)->where('user_id', $actorId)->exists()) {
                throw new RuntimeException('Actor must be an existing member of the society.');
            }
            $record = $this->loadAndCheck($societyId);
            $this->checkExisting($record);
            if ($record['source_paid_on'] !== null && $record['source_paid_on'] !== $paidOn) {
                throw new RuntimeException('Specified paid_on differs from the original encrypted source transaction date: '.$record['source_paid_on'].'.');
            }
            if ($record['already_linked']) {
                if ($record['paid_on'] !== $paidOn || $record['payment_method'] !== $paymentMethod || $record['payment_reference'] !== $paymentReference) {
                    throw new RuntimeException('Already linked using different historical payment metadata; do not overwrite an audited transaction.');
                }
                return ['status' => 'already_linked', 'penalty_id' => $record['penalty_id'], 'payment_id' => $record['payment_id']];
            }
            $nativePenaltyId = (int) DB::table('contribution_penalties')->insertGetId([
                'society_id' => $societyId,
                'cycle_id' => self::CYCLE,
                'contribution_obligation_id' => self::OBLIGATION,
                'base_amount' => Money::fromCents(self::AMOUNT_CENTS),
                'rate_percent' => '0.00', // historical fixed amount, not a new calculation
                'assessed_amount' => Money::fromCents(self::AMOUNT_CENTS),
                // Recognition date: when the historical evidence was reviewed.
                // Not an invented original assessment date.
                'cutoff_date' => $record['reviewed_on'],
                'assessed_on' => $record['reviewed_on'],
                'calculation_method' => 'historical_fixed',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $nativePaymentId = (int) DB::table('contribution_penalty_payments')->insertGetId([
                'society_id' => $societyId,
                'contribution_penalty_id' => $nativePenaltyId,
                'amount' => Money::fromCents(self::AMOUNT_CENTS),
                'paid_on' => $paidOn,
                'payment_method' => $paymentMethod,
                'payment_reference' => trim($paymentReference),
                'notes' => 'Opening historical paid penalty; source transaction #32; reviewed item #3; January 2026 obligation #37. CASH/LEDGER POSTING PENDING independent reconciliation; NOT a new cash receipt.',
                'idempotency_key' => (string) Str::uuid(),
                'recorded_by' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('legacy_imported_contribution_penalties')->insert([
                'review_item_id' => self::REVIEW,
                'society_id' => $societyId,
                'contribution_obligation_id' => self::OBLIGATION,
                'contribution_penalty_id' => $nativePenaltyId,
                'contribution_penalty_payment_id' => $nativePaymentId,
                'amount_cents' => self::AMOUNT_CENTS,
                'original_paid_on' => $paidOn,
                'evidence_reference' => $record['evidence_reference'],
                'imported_by' => $actorId,
                'imported_at' => now(),
                'ledger_transaction_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['status' => 'linked_historical_paid', 'penalty_id' => $nativePenaltyId, 'payment_id' => $nativePaymentId];
        }, 3);
    }

    private function loadAndCheck(int $societyId): array
    {
        if ($societyId !== self::SOCIETY) {
            throw new RuntimeException('This one-time patch is restricted to Society #1.');
        }
        $row = DB::table('legacy_financial_review_items as r')
            ->join('legacy_financial_import_runs as run', 'run.id', '=', 'r.legacy_financial_import_run_id')
            ->join('contribution_obligations as o', function ($join) {
                $join->where('o.id', self::OBLIGATION);
            })
            ->where('r.id', self::REVIEW)
            ->where('r.source_table', 'transactions')
            ->where('r.source_id_value', self::LEGACY_TRANSACTION)
            ->where('r.reason', 'penalty_collection_not_proven')
            ->where('r.resolution_status', 'resolved')
            ->whereNull('r.loan_id')
            ->where('r.society_membership_id', self::MEMBERSHIP)
            ->where('r.amount_cents', self::AMOUNT_CENTS)
            ->where('run.society_id', self::SOCIETY)
            ->where('run.cycle_id', self::CYCLE)
            ->where('o.society_id', self::SOCIETY)
            ->where('o.cycle_id', self::CYCLE)
            ->where('o.society_membership_id', self::MEMBERSHIP)
            ->whereDate('o.period_start', '=', '2026-01-01')
            ->select(['r.id', 'r.amount_cents', 'run.batch_id', 'o.id as obligation_id', 'o.period_start'])
            ->first();
        if (! $row) {
            throw new RuntimeException('The reviewed M50 January penalty and obligation #37 do not match the expected source, member, and cycle. No changes made.');
        }
        $latest = DB::table('legacy_penalty_review_actions')
            ->where('review_item_id', self::REVIEW)
            ->orderByDesc('id')->first();
        if (! $latest || $latest->decision !== 'collected_documented'
            || mb_strlen(trim((string) $latest->evidence_reference)) < 5) {
            throw new RuntimeException('Review item #3 must have an evidence-backed latest collected_documented decision.');
        }
        $reviewedOn = substr((string) $latest->created_at, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $reviewedOn)) {
            throw new RuntimeException('Historical review date missing.');
        }
        $link = DB::table('legacy_imported_contribution_penalties')
            ->where('review_item_id', self::REVIEW)->first();
        $record = [
            'society_id' => self::SOCIETY,
            'cycle_id' => self::CYCLE,
            'review_id' => self::REVIEW,
            'legacy_transaction_id' => self::LEGACY_TRANSACTION,
            'obligation_id' => self::OBLIGATION,
            'membership_id' => self::MEMBERSHIP,
            'amount_cents' => self::AMOUNT_CENTS,
            'decision' => $latest->decision,
            'reviewed_on' => $reviewedOn,
            'evidence_reference' => (string) $latest->evidence_reference,
            'already_linked' => $link !== null,
            'penalty_id' => $link ? (int) $link->contribution_penalty_id : null,
            'payment_id' => $link ? (int) $link->contribution_penalty_payment_id : null,
            'paid_on' => null,
            'payment_method' => null,
            'payment_reference' => null,
            'source_paid_on' => $this->readHistoricalSourceDate((int) $row->batch_id),
        ];
        if ($link) {
            if ((int) $link->contribution_obligation_id !== self::OBLIGATION
                || (int) $link->society_id !== self::SOCIETY
                || (int) $link->amount_cents !== self::AMOUNT_CENTS) {
                throw new RuntimeException('Existing mapped January penalty is inconsistent; manual audit required.');
            }
            $native = DB::table('contribution_penalties')
                ->where('id', $link->contribution_penalty_id)
                ->where('society_id', $societyId)
                ->where('cycle_id', self::CYCLE)
                ->where('contribution_obligation_id', self::OBLIGATION)->first();
            $payment = DB::table('contribution_penalty_payments')
                ->where('id', $link->contribution_penalty_payment_id)
                ->where('society_id', $societyId)
                ->where('contribution_penalty_id', $link->contribution_penalty_id)->first();
            if (! $native || ! $payment
                || Money::toCents((string) $native->assessed_amount) !== self::AMOUNT_CENTS
                || Money::toCents((string) $payment->amount) !== self::AMOUNT_CENTS) {
                throw new RuntimeException('Existing mapped native assessment/payment is invalid; do not create duplicates.');
            }
            $record['paid_on'] = substr((string) $payment->paid_on, 0, 10);
            $record['payment_method'] = (string) $payment->payment_method;
            $record['payment_reference'] = (string) $payment->payment_reference;
        }
        return $record;
    }

    private function checkExisting(array $record): void
    {
        if ($record['already_linked']) return;
        // If an ordinary or scheduled penalty already exists on January's
        // obligation, importing a second charge would potentially double-bill.
        if (DB::table('contribution_penalties')
            ->where('society_id', self::SOCIETY)
            ->where('cycle_id', self::CYCLE)
            ->where('contribution_obligation_id', self::OBLIGATION)
            ->exists()) {
            throw new RuntimeException('Obligation #37 already has a native contribution penalty. Stop: inspect it to prevent double billing.');
        }
    }

    private function validateTables(): void
    {
        foreach (['societies', 'users', 'society_memberships', 'legacy_financial_review_items',
            'legacy_financial_import_runs', 'legacy_penalty_review_actions',
            'contribution_obligations', 'contribution_penalties',
            'contribution_penalty_payments', 'legacy_imported_contribution_penalties'] as $table) {
            if (! Schema::hasTable($table)) throw new RuntimeException("Missing {$table}. Run php artisan migrate first.");
        }
        $native = collect(Schema::getColumns('contribution_penalties'))->keyBy('name');
        $required = ['society_id','cycle_id','contribution_obligation_id','base_amount',
            'rate_percent','assessed_amount','cutoff_date','assessed_on','calculation_method'];
        foreach ($required as $name) {
            if (! $native->has($name)) throw new RuntimeException("Missing contribution_penalties.{$name}.");
        }
        foreach ($native as $name => $meta) {
            if (in_array($name, [...$required, 'id','created_at','updated_at'], true)) continue;
            if (! ($meta['nullable'] ?? false) && ($meta['default'] ?? null) === null && ! ($meta['auto_increment'] ?? false)) {
                throw new RuntimeException("Unexpected required native penalty column {$name}; no changes made.");
            }
        }
        $type = strtolower((string) ($native['calculation_method']['type'] ?? ''));
        if (str_contains($type, 'enum(') && ! str_contains($type, "'historical_fixed'")) {
            throw new RuntimeException('calculation_method does not accept historical_fixed. Do not bypass schema checks.');
        }
        $paymentCols = collect(Schema::getColumns('contribution_penalty_payments'))->keyBy('name');
        foreach (['society_id','contribution_penalty_id','amount','paid_on','payment_method',
            'payment_reference','notes','idempotency_key','recorded_by'] as $name) {
            if (! $paymentCols->has($name)) throw new RuntimeException("Missing contribution_penalty_payments.{$name}.");
        }
        $methodType = strtolower((string) ($paymentCols['payment_method']['type'] ?? ''));
        if (str_contains($methodType, 'enum(')) {
            throw new RuntimeException('Native payment_method uses ENUM; supply its allowed values for a safe, source-specific patch.');
        }
    }

    /** The encrypted original import plan is a preferred source for paid_on. */
    private function readHistoricalSourceDate(int $batchId): ?string
    {
        $path = storage_path("app/private/legacy-audits/batch-{$batchId}-financial-plan.enc");
        if (! is_file($path)) return null;
        try {
            $plan = json_decode(Crypt::decryptString((string) file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
            foreach (($plan['penalties_pending_review'] ?? []) as $record) {
                if ((string) ($record['legacy_transaction_id'] ?? '') !== self::LEGACY_TRANSACTION) continue;
                if ((int) ($record['amount_cents'] ?? -1) !== self::AMOUNT_CENTS
                    || (int) ($record['membership_id'] ?? 0) !== self::MEMBERSHIP) {
                    throw new RuntimeException('Encrypted source transaction #32 does not match reviewed member or amount.');
                }
                foreach (['date', 'paid_on', 'transaction_date'] as $field) {
                    $value = substr((string) ($record[$field] ?? ''), 0, 10);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
                }
                return null;
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('Cannot decrypt original financial import plan for source-date validation. '.$e->getMessage());
        }
        return null;
    }

    private function validateOriginalPaymentDate(string $date): void
    {
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Africa/Maseru');
        } catch (\Throwable $e) {
            throw new RuntimeException('Provide the original paid_on date as YYYY-MM-DD.');
        }
        if (! $day || $day->format('Y-m-d') !== $date
            || $day->toDateString() < '2026-01-01' || $day->toDateString() > '2026-12-31'
            || $day->toDateString() > now('Africa/Maseru')->toDateString()) {
            throw new RuntimeException('paid_on must be a real historical 2026 date on/before today, supported by evidence.');
        }
    }
}
