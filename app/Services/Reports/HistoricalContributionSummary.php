<?php

namespace App\Services\Reports;

use App\Services\Contributions\ContributionObligationCoverage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Historical compliance display for imported cycles.
 * Read only. No receipts or journals are created.
 */
final class HistoricalContributionSummary
{
    public function __construct(
        private readonly ContributionObligationCoverage $coverage
    ) {}

    public function forSociety(int $societyId): ?array
    {
        return $this->summarize($societyId, null);
    }

    public function forMember(int $societyId, int $membershipId): ?array
    {
        $belongs = DB::table('society_memberships')
            ->where('society_id', $societyId)
            ->where('id', $membershipId)
            ->exists();

        if (! $belongs) {
            throw new RuntimeException('Membership does not belong to this society.');
        }

        return $this->summarize($societyId, $membershipId);
    }

    private function summarize(int $societyId, ?int $membershipId): ?array
    {
        // Latest imported cycle is displayed as a separate historical panel.
        // It is never silently combined with active-cycle accounting figures.
        $run = DB::table('legacy_financial_import_runs')
            ->where('society_id', $societyId)
            ->orderByDesc('id')
            ->first(['id', 'cycle_id']);

        if (! $run) {
            return null;
        }

        $result = $this->coverage->forCycle($societyId, (int) $run->cycle_id);
        $rows = $result['rows'];

        if ($membershipId !== null) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool =>
                    (int) $row['society_membership_id'] === $membershipId
            ));
        }

        $totals = [
            'obligations' => count($rows),
            'fully_paid_obligations' => 0,
            'required_cents' => 0,
            'direct_paid_cents' => 0,
            'historical_paid_cents' => 0,
            'paid_cents' => 0,
            'outstanding_cents' => 0,
            'excess_cents' => 0,
        ];

        foreach ($rows as $row) {
            if ($row['fully_paid']) {
                $totals['fully_paid_obligations']++;
            }
            foreach (['required_cents', 'direct_paid_cents', 'historical_paid_cents',
                'paid_cents', 'outstanding_cents', 'excess_cents'] as $key) {
                $totals[$key] += (int) $row[$key];
            }
        }

        return [
            'cycle_id' => (int) $run->cycle_id,
            'import_run_id' => (int) $run->id,
            'member_scoped' => $membershipId !== null,
            'membership_id' => $membershipId,
            'as_of_date' => $result['as_of_date'],
            'totals' => $totals,
            'rows' => $rows,
            'notice' => $result['notice'],
        ];
    }
}
