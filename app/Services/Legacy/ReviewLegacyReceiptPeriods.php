<?php

namespace App\Services\Legacy;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Records source-backed period attribution ONLY.
 * Does NOT create obligations, touch the posted receipt amount or write GL entries.
 */
final class ReviewLegacyReceiptPeriods
{
    private function lockImport(int $societyId, int $runId): array
    {
        $society = DB::table('societies')->where('id', $societyId)->lockForUpdate()->first();
        if (! $society) {
            throw new RuntimeException('Society not found.');
        }

        $run = DB::table('legacy_financial_import_runs')
            ->where('id', $runId)->where('society_id', $societyId)->first();
        if (! $run) {
            throw new RuntimeException('Historical import run does not belong to this society.');
        }

        $cycle = DB::table('cycles')->where('society_id', $societyId)
            ->where('id', $run->cycle_id)->lockForUpdate()->first();
        if (! $cycle || $cycle->status !== 'draft') {
            throw new RuntimeException('Period attribution requires an open historical draft cycle.');
        }

        $batch = DB::table('legacy_import_batches')->where('id', $run->batch_id)->first();
        if (! $batch) {
            throw new RuntimeException('Original source batch not found.');
        }

        // Do not change classifications after payout approval.
        if (DB::table('cycle_payout_approvals')->where('cycle_id', $cycle->id)
            ->where('society_id', $societyId)->exists()) {
            throw new RuntimeException('This cycle already has a payout approval.');
        }

        return [$run, $cycle, $batch];
    }

    private function lockReceipt(int $societyId, int $runId, int $paymentId): object
    {
        $receipt = DB::table('legacy_financial_import_runs as run')
            ->join('legacy_import_batches as batch', 'batch.id', '=', 'run.batch_id')
            ->join('legacy_record_maps as map', function ($join) {
                $join->on('map.source_id', '=', 'batch.source_id')
                    ->where('map.source_table', '=', 'transactions')
                    ->where('map.target_table', '=', 'contribution_payments');
            })
            ->join('contribution_payments as payment', 'payment.id', '=', 'map.target_id')
            ->where('run.id', $runId)
            ->where('run.society_id', $societyId)
            ->where('payment.society_id', $societyId)
            ->whereColumn('payment.cycle_id', 'run.cycle_id')
            ->where('payment.id', $paymentId)
            ->select('payment.*')
            ->first();

        if (! $receipt) {
            throw new RuntimeException('Payment is not an imported receipt in the selected society/cycle.');
        }

        // Serialize operations for the current receipt even when other transactions mutate it.
        return DB::table('contribution_payments')->where('id', $receipt->id)
            ->where('society_id', $societyId)->lockForUpdate()->first();
    }

    public function attribute(
        int $societyId,
        int $runId,
        int $paymentId,
        int $actorId,
        string $month,
        string $amount,
        string $evidenceReference,
        ?string $notes
    ): int {
        return DB::transaction(function () use (
            $societyId, $runId, $paymentId, $actorId, $month, $amount,
            $evidenceReference, $notes
        ) {
            [, $cycle] = $this->lockImport($societyId, $runId);
            $receipt = $this->lockReceipt($societyId, $runId, $paymentId);
            if (! $receipt || $receipt->contribution_obligation_id !== null) {
                throw new RuntimeException('Receipt is no longer pending historical obligation review.');
            }

            $period = LegacyReceiptPeriodRules::monthStart($month);
            LegacyReceiptPeriodRules::assertWithinCycle(
                $period, (string) $cycle->start_date, (string) $cycle->end_date
            );

            $evidenceReference = trim($evidenceReference);
            if (mb_strlen($evidenceReference) < 5 || mb_strlen($evidenceReference) > 255) {
                throw new RuntimeException('A traceable evidence reference of 5-255 characters is required.');
            }

            $newCents = Money::toCents($amount);
            $receiptCents = Money::toCents((string) $receipt->amount);

            $existing = DB::table('legacy_receipt_period_attributions')
                ->where('contribution_payment_id', $receipt->id)
                ->whereNull('reversed_at')->get();
            if ($existing->contains('period_start', $period)) {
                throw new RuntimeException('This month already has an active attribution for the receipt. Reverse it before replacing.');
            }

            $already = $existing->sum(fn ($row) => (int) $row->amount_cents);
            LegacyReceiptPeriodRules::assertMayAttribute($receiptCents, $already, $newCents);

            return DB::table('legacy_receipt_period_attributions')->insertGetId([
                'society_id' => $societyId,
                'cycle_id' => $cycle->id,
                'contribution_payment_id' => $receipt->id,
                'period_start' => $period,
                'amount_cents' => $newCents,
                'evidence_reference' => $evidenceReference,
                'notes' => $notes ?: null,
                'allocated_by' => $actorId,
                'allocated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function reverse(
        int $societyId,
        int $runId,
        int $paymentId,
        int $attributionId,
        int $actorId,
        string $reason
    ): void {
        DB::transaction(function () use (
            $societyId, $runId, $paymentId, $attributionId, $actorId, $reason
        ) {
            [, $cycle] = $this->lockImport($societyId, $runId);
            $receipt = $this->lockReceipt($societyId, $runId, $paymentId);
            if (! $receipt) {
                throw new RuntimeException('Source receipt not found.');
            }
            $attribution = DB::table('legacy_receipt_period_attributions')
                ->where('id', $attributionId)
                ->where('society_id', $societyId)
                ->where('cycle_id', $cycle->id)
                ->where('contribution_payment_id', $paymentId)
                ->lockForUpdate()->first();
            if (! $attribution || $attribution->reversed_at !== null) {
                throw new RuntimeException('Active attribution was not found.');
            }
            // Do not detach the underlying evidence after it has been linked
            // to a reviewed obligation. Reverse the Step 9E.2 link first.
            if (Schema::hasTable('legacy_receipt_obligation_links') &&
                DB::table('legacy_receipt_obligation_links')
                    ->where('legacy_receipt_period_attribution_id', $attributionId)
                    ->whereNull('reversed_at')->exists()) {
                throw new RuntimeException(
                    'This month is linked to an obligation. Reverse the Step 9E.2 link first.'
                );
            }
            $reason = trim($reason);
            if (mb_strlen($reason) < 10) {
                throw new RuntimeException('A reversal explanation of at least 10 characters is required.');
            }
            DB::table('legacy_receipt_period_attributions')
                ->where('id', $attributionId)->update([
                    'reversed_by' => $actorId,
                    'reversed_at' => now(),
                    'reversal_reason' => $reason,
                    'updated_at' => now(),
                ]);
        }, 3);
    }
}
