<?php

namespace App\Services\Legacy;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Maps verified historical period attribution to a due obligation.
 * Never creates/updates a payment, receipt, journal, interest credit or penalty.
 * Ordinary contribution compliance/reporting MUST explicitly include these links.
 */
final class ResolveLegacyReceiptObligations
{
    private function lockRun(int $societyId, int $runId): object
    {
        $society = DB::table('societies')->where('id', $societyId)->lockForUpdate()->first();
        if (! $society) {
            throw new RuntimeException('Society not found.');
        }
        $run = DB::table('legacy_financial_import_runs')
            ->where('id', $runId)->where('society_id', $societyId)->lockForUpdate()->first();
        if (! $run) {
            throw new RuntimeException('Historical import run not found for this society.');
        }
        $cycle = DB::table('cycles')->where('id', $run->cycle_id)
            ->where('society_id', $societyId)->lockForUpdate()->first();
        if (! $cycle || $cycle->status !== 'draft') {
            throw new RuntimeException('Obligation reconciliation requires a draft historical cycle.');
        }
        if (DB::table('cycle_payout_approvals')
            ->where('society_id', $societyId)->where('cycle_id', $cycle->id)->exists()) {
            throw new RuntimeException('This cycle already has an approved payout.');
        }
        $batch = DB::table('legacy_import_batches')->find($run->batch_id);
        if (! $batch) {
            throw new RuntimeException('Import source batch is missing.');
        }
        return (object) [
            'id' => (int) $run->id,
            'cycle_id' => (int) $cycle->id,
            'source_id' => (int) $batch->source_id,
            'start_date' => (string) $cycle->start_date,
            'end_date' => (string) $cycle->end_date,
        ];
    }

    private function lockAttribution(int $societyId, object $run, int $attributionId): array
    {
        $attribution = DB::table('legacy_receipt_period_attributions')
            ->where('id', $attributionId)->where('society_id', $societyId)
            ->where('cycle_id', $run->cycle_id)->first();
        if (! $attribution) {
            throw new RuntimeException('Historical period attribution is not in this society/cycle.');
        }
        $receipt = DB::table('contribution_payments')
            ->where('id', $attribution->contribution_payment_id)
            ->where('society_id', $societyId)->where('cycle_id', $run->cycle_id)
            ->lockForUpdate()->first();
        if (! $receipt || $receipt->contribution_obligation_id !== null) {
            throw new RuntimeException('This receipt is missing or is already directly assigned to an obligation.');
        }
        $sourceMapExists = DB::table('legacy_record_maps')
            ->where('source_id', $run->source_id)
            ->where('source_table', 'transactions')
            ->where('target_table', 'contribution_payments')
            ->where('target_id', $receipt->id)->exists();
        if (! $sourceMapExists) {
            throw new RuntimeException('This is not a mapped historical contribution receipt.');
        }
        $attribution = DB::table('legacy_receipt_period_attributions')
            ->where('id', $attributionId)->where('contribution_payment_id', $receipt->id)
            ->lockForUpdate()->first();
        if (! $attribution || $attribution->reversed_at !== null) {
            throw new RuntimeException('Attribution was reversed or has changed.');
        }
        $prior = DB::table('legacy_receipt_obligation_links')
            ->where('legacy_receipt_period_attribution_id', $attributionId)
            ->whereNull('reversed_at')->exists();
        if ($prior) {
            throw new RuntimeException('This reviewed month is already linked. Reverse the existing link first.');
        }
        $alreadyLinked = (int) DB::table('legacy_receipt_obligation_links')
            ->where('society_id', $societyId)->where('cycle_id', $run->cycle_id)
            ->where('contribution_payment_id', $receipt->id)->whereNull('reversed_at')
            ->sum('amount_cents');
        LegacyObligationReviewRules::assertAmount(
            (int) $attribution->amount_cents,
            Money::toCents((string) $receipt->amount),
            $alreadyLinked
        );
        return [$receipt, $attribution];
    }

    private function evidence(string $reference): string
    {
        $reference = trim($reference);
        if (mb_strlen($reference) < 5 || mb_strlen($reference) > 255) {
            throw new RuntimeException('Evidence reference must contain 5 to 255 characters.');
        }
        return $reference;
    }

    private function obligationFor(int $societyId, object $run, object $receipt, object $attribution, int $obligationId): object
    {
        $obligation = DB::table('contribution_obligations')
            ->where('id', $obligationId)->where('society_id', $societyId)
            ->where('cycle_id', $run->cycle_id)
            ->where('society_membership_id', $receipt->society_membership_id)
            ->lockForUpdate()->first();
        if (! $obligation || substr((string) $obligation->period_start, 0, 10) !==
            substr((string) $attribution->period_start, 0, 10)) {
            throw new RuntimeException('Obligation must belong to the same member, cycle and reviewed month.');
        }
        $duplicates = DB::table('contribution_obligations')
            ->where('society_id', $societyId)->where('cycle_id', $run->cycle_id)
            ->where('society_membership_id', $receipt->society_membership_id)
            ->whereDate('period_start', substr((string) $attribution->period_start, 0, 10))
            ->count();
        if ($duplicates !== 1) {
            throw new RuntimeException('Duplicate monthly obligations exist; resolve them manually before linking.');
        }
        if (Money::toCents((string) $obligation->required_amount) <= 0) {
            throw new RuntimeException('Monthly obligation must have a positive verified required amount.');
        }
        LegacyObligationReviewRules::assertObligationDate(
            substr((string) $attribution->period_start, 0, 10),
            substr((string) $obligation->due_date, 0, 10),
            $run->start_date, $run->end_date
        );
        return $obligation;
    }

    private function insertLink(int $societyId, object $run, object $receipt, object $attribution,
        int $obligationId, int $actorId, string $reference): int
    {
        return DB::table('legacy_receipt_obligation_links')->insertGetId([
            'society_id' => $societyId,
            'cycle_id' => $run->cycle_id,
            'contribution_payment_id' => $receipt->id,
            'legacy_receipt_period_attribution_id' => $attribution->id,
            'contribution_obligation_id' => $obligationId,
            'amount_cents' => (int) $attribution->amount_cents,
            'evidence_reference' => $this->evidence($reference),
            'linked_by' => $actorId,
            'linked_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function link(int $societyId, int $runId, int $attributionId, int $obligationId,
        int $actorId, string $evidenceReference): int
    {
        return DB::transaction(function () use ($societyId, $runId, $attributionId,
            $obligationId, $actorId, $evidenceReference) {
            $run = $this->lockRun($societyId, $runId);
            [$receipt, $attribution] = $this->lockAttribution($societyId, $run, $attributionId);
            $this->obligationFor($societyId, $run, $receipt, $attribution, $obligationId);
            return $this->insertLink($societyId, $run, $receipt, $attribution,
                $obligationId, $actorId, $evidenceReference);
        }, 3);
    }

    public function createAndLink(int $societyId, int $runId, int $attributionId, int $actorId,
        string $requiredAmount, string $dueDate, string $evidenceReference): int
    {
        return DB::transaction(function () use ($societyId, $runId, $attributionId, $actorId,
            $requiredAmount, $dueDate, $evidenceReference) {
            $run = $this->lockRun($societyId, $runId);
            [$receipt, $attribution] = $this->lockAttribution($societyId, $run, $attributionId);
            $period = substr((string) $attribution->period_start, 0, 10);
            LegacyObligationReviewRules::assertObligationDate($period, $dueDate, $run->start_date, $run->end_date);
            $requiredCents = Money::toCents($requiredAmount);
            if ($requiredCents <= 0) {
                throw new RuntimeException('Verified monthly required amount must be positive.');
            }
            $reference = $this->evidence($evidenceReference);
            $existing = DB::table('contribution_obligations')
                ->where('society_id', $societyId)->where('cycle_id', $run->cycle_id)
                ->where('society_membership_id', $receipt->society_membership_id)
                ->whereDate('period_start', $period)->exists();
            if ($existing) {
                throw new RuntimeException('A monthly obligation already exists. Link it instead of creating a duplicate.');
            }
            $obligationId = DB::table('contribution_obligations')->insertGetId([
                'society_id' => $societyId,
                'cycle_id' => $run->cycle_id,
                'society_membership_id' => $receipt->society_membership_id,
                'period_start' => $period,
                'due_date' => $dueDate,
                'required_amount' => number_format($requiredCents / 100, 2, '.', ''),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('legacy_reviewed_contribution_obligations')->insert([
                'society_id' => $societyId,
                'cycle_id' => $run->cycle_id,
                'contribution_obligation_id' => $obligationId,
                'evidence_reference' => $reference,
                'verified_required_cents' => $requiredCents,
                'verified_due_date' => $dueDate,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $this->insertLink($societyId, $run, $receipt, $attribution,
                $obligationId, $actorId, $reference);
        }, 3);
    }

    public function reverse(int $societyId, int $runId, int $linkId, int $actorId, string $reason): void
    {
        DB::transaction(function () use ($societyId, $runId, $linkId, $actorId, $reason) {
            $run = $this->lockRun($societyId, $runId);
            $link = DB::table('legacy_receipt_obligation_links')
                ->where('id', $linkId)->where('society_id', $societyId)
                ->where('cycle_id', $run->cycle_id)->first();
            if (! $link) {
                throw new RuntimeException('Historical obligation link not found.');
            }
            DB::table('contribution_payments')->where('id', $link->contribution_payment_id)
                ->where('society_id', $societyId)->lockForUpdate()->first();
            $link = DB::table('legacy_receipt_obligation_links')->where('id', $linkId)
                ->where('society_id', $societyId)->where('cycle_id', $run->cycle_id)
                ->lockForUpdate()->first();
            if (! $link || $link->reversed_at !== null) {
                throw new RuntimeException('Link has already been reversed or is missing.');
            }
            $reason = trim($reason);
            if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
                throw new RuntimeException('Reversal reason must contain 10 to 2000 characters.');
            }
            DB::table('legacy_receipt_obligation_links')->where('id', $linkId)->update([
                'reversed_by' => $actorId, 'reversed_at' => now(),
                'reversal_reason' => $reason, 'updated_at' => now(),
            ]);
        }, 3);
    }
}
