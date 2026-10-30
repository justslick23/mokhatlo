<?php

namespace App\Services\Contributions;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * READ-ONLY bridge from ordinary direct-FK receipts + reviewed many-to-many
 * historical links. Never creates obligations, payments, penalties or journals.
 *
 * Integration boundary: use these paid/outstanding figures in ordinary reports,
 * statements, and any future compliance calculations. Do not retroactively
 * calculate historical penalties without separately approved policy evidence.
 */
final class ContributionObligationCoverage
{
    public function forCycle(int $societyId, int $cycleId, ?string $asOf = null): array
    {
        if ($societyId < 1 || $cycleId < 1) {
            throw new InvalidArgumentException('Invalid society or cycle ID.');
        }

        $date = $asOf ?? now('Africa/Maseru')->toDateString();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw new InvalidArgumentException('as-of date must be YYYY-MM-DD.');
        }

        foreach (['contribution_obligations', 'contribution_payments',
            'legacy_receipt_obligation_links', 'legacy_receipt_period_attributions'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Missing required table {$table}; install Step 9E.2 first.");
            }
        }

        if (! DB::table('cycles')->where('id', $cycleId)->where('society_id', $societyId)->exists()) {
            throw new RuntimeException('Cycle does not belong to selected society.');
        }

        $obligations = DB::table('contribution_obligations as o')
            ->where('o.society_id', $societyId)
            ->where('o.cycle_id', $cycleId)
            ->orderBy('o.society_membership_id')
            ->orderBy('o.period_start')
            ->get([
                'o.id', 'o.society_id', 'o.cycle_id', 'o.society_membership_id',
                'o.period_start', 'o.due_date', 'o.required_amount',
            ]);

        // Ordinary receipts with a direct obligation foreign key.
        $direct = DB::table('contribution_payments as p')
            ->where('p.society_id', $societyId)
            ->where('p.cycle_id', $cycleId)
            ->whereNotNull('p.contribution_obligation_id')
            ->whereDate('p.paid_on', '<=', $date)
            ->selectRaw('p.contribution_obligation_id AS obligation_id, SUM(p.amount) AS total_amount')
            ->groupBy('p.contribution_obligation_id')
            ->get()
            ->keyBy('obligation_id');

        // Verified historical links. No extra cash exists in this bridge.
        // Joining source payment, attribution and obligation with matching
        // tenant/member/period prevents cross-society or cross-month leakage.
        $historical = DB::table('legacy_receipt_obligation_links as l')
            ->join('legacy_receipt_period_attributions as a',
                'a.id', '=', 'l.legacy_receipt_period_attribution_id')
            ->join('contribution_payments as p',
                'p.id', '=', 'l.contribution_payment_id')
            ->join('contribution_obligations as o',
                'o.id', '=', 'l.contribution_obligation_id')
            ->where('l.society_id', $societyId)
            ->where('l.cycle_id', $cycleId)
            ->whereNull('l.reversed_at')
            ->whereNull('a.reversed_at')
            ->whereNull('p.contribution_obligation_id')
            ->where('a.society_id', $societyId)
            ->where('a.cycle_id', $cycleId)
            ->where('p.society_id', $societyId)
            ->where('p.cycle_id', $cycleId)
            ->where('o.society_id', $societyId)
            ->where('o.cycle_id', $cycleId)
            ->whereColumn('a.contribution_payment_id', 'p.id')
            ->whereColumn('o.society_membership_id', 'p.society_membership_id')
            ->whereColumn('a.period_start', 'o.period_start')
            ->whereDate('p.paid_on', '<=', $date)
            ->selectRaw('l.contribution_obligation_id AS obligation_id, SUM(l.amount_cents) AS total_cents')
            ->groupBy('l.contribution_obligation_id')
            ->get()
            ->keyBy('obligation_id');

        $rows = [];
        $totals = [
            'obligations' => 0,
            'required_cents' => 0,
            'direct_paid_cents' => 0,
            'historical_paid_cents' => 0,
            'paid_cents' => 0,
            'outstanding_cents' => 0,
            'excess_cents' => 0,
            'fully_paid_obligations' => 0,
        ];

        foreach ($obligations as $o) {
            $id = (int) $o->id;
            $directCents = isset($direct[$id])
                ? Money::toCents((string) $direct[$id]->total_amount)
                : 0;
            $historicalCents = isset($historical[$id])
                ? (int) $historical[$id]->total_cents
                : 0;

            $coverage = ObligationCoverageMath::calculate(
                Money::toCents((string) $o->required_amount),
                $directCents,
                $historicalCents
            );

            $rows[] = array_merge([
                'obligation_id' => $id,
                'society_membership_id' => (int) $o->society_membership_id,
                'period_start' => substr((string) $o->period_start, 0, 10),
                'due_date' => substr((string) $o->due_date, 0, 10),
            ], $coverage);

            $totals['obligations']++;
            foreach (['required_cents', 'direct_paid_cents', 'historical_paid_cents',
                'paid_cents', 'outstanding_cents', 'excess_cents'] as $key) {
                $totals[$key] += $coverage[$key];
            }
            if ($coverage['fully_paid']) {
                $totals['fully_paid_obligations']++;
            }
        }

        return [
            'society_id' => $societyId,
            'cycle_id' => $cycleId,
            'as_of_date' => $date,
            'rows' => $rows,
            'totals' => $totals,
            'notice' => 'Only existing obligations are assessed; absence of a monthly obligation does NOT prove compliance. Historical penalty generation remains disabled pending policy review.',
        ];
    }
}
