<?php

namespace App\Services\MokhatloMail;

use App\Models\Society;
use App\Models\SocietyMembership;
use Carbon\CarbonImmutable;

/** Shared calculation layer for private and transparent society PDF snapshots. */
final class MonthlyStatementBuilder
{
    public function __construct(
        private readonly MonthlyReportTransactions $transactions,
        private readonly MonthlyReportPositions $positions,
        private readonly MonthlyReportJournal $journal,
    ) {}

    public static function bounds(string $period): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new \InvalidArgumentException('Period must be YYYY-MM.');
        }
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $period . '-01');
        if (! $start || $start->format('Y-m') !== $period) {
            throw new \InvalidArgumentException('Invalid period.');
        }
        return [$start->toDateString(), $start->endOfMonth()->toDateString()];
    }

    private function totals(array $entries): array
    {
        $flow = [
            'contributions_cents' => 0, 'loan_disbursements_cents' => 0,
            'loan_repayments_cents' => 0, 'loan_interest_component_cents' => 0,
            'penalties_received_cents' => 0,
            'contributions_count' => 0, 'loan_disbursements_count' => 0,
            'loan_repayments_count' => 0, 'penalty_payments_count' => 0,
            'cash_in_cents' => 0, 'cash_out_cents' => 0,
            'net_captured_cash_cents' => 0, 'noncash_events_count' => 0,
        ];
        foreach ($entries as $row) {
            $amount = $row['amount_cents'];
            if ($row['direction'] === 'in') $flow['cash_in_cents'] += $amount;
            if ($row['direction'] === 'out') $flow['cash_out_cents'] += $amount;
            if ($row['direction'] === 'noncash') $flow['noncash_events_count']++;
            switch ($row['source']) {
                case 'contribution':
                    $flow['contributions_cents'] += $amount;
                    $flow['contributions_count']++;
                    break;
                case 'disbursement':
                    $flow['loan_disbursements_cents'] += $amount;
                    $flow['loan_disbursements_count']++;
                    break;
                case 'repayment':
                    $flow['loan_repayments_cents'] += $amount;
                    $flow['loan_interest_component_cents'] += $row['interest_component_cents'];
                    $flow['loan_repayments_count']++;
                    break;
                case 'contribution_penalty_payment':
                case 'loan_penalty_payment':
                    $flow['penalties_received_cents'] += $amount;
                    $flow['penalty_payments_count']++;
                    break;
            }
        }
        $flow['net_captured_cash_cents'] = $flow['cash_in_cents'] - $flow['cash_out_cents'];
        return $flow;
    }

    private function members(Society $society): array
    {
        // A member with historical money still appears even if not currently
        // active. This prevents silently omitting transactions in society PDF.
        return $society->memberships()->with('user')->orderBy('id')->get()
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'number' => (string) ($m->member_number ?: 'Member #' . $m->id),
                'name' => (string) ($m->user?->name ?: 'Member #' . $m->id),
                'status' => (string) $m->status,
            ])->all();
    }

    public function society(Society $society, int $cycleId, string $period): array
    {
        [$start, $end] = self::bounds($period);
        $cycle = $society->cycles()->findOrFail($cycleId);
        $members = $this->members($society);
        $activity = $this->transactions->forPeriod((int) $society->id, $cycleId, $start, $end);
        $byId = array_column($members, null, 'id');
        foreach ($activity as &$row) {
            $identity = $byId[$row['membership_id']] ?? null;
            $row['member'] = $identity['name'] ?? ('Member #' . $row['membership_id']);
            $row['member_number'] = $identity['number'] ?? '';
            unset($row['reference']); // shared PDF never leaks banking reference strings
        }
        unset($row);

        $memberStanding = [];
        foreach ($members as $m) {
            $memberActivity = array_values(array_filter($activity,
                fn ($r) => $r['membership_id'] === $m['id']));
            $memberStanding[] = [
                ...$m,
                'flows' => $this->totals($memberActivity),
                'transactions' => $memberActivity,
                'position' => $this->positions->member((int) $society->id, $cycleId, $m['id'], $end),
            ];
        }
        $openingDate = CarbonImmutable::parse($start)->subDay()->toDateString();
        $opening = $this->positions->ledger((int) $society->id, $openingDate);
        $closing = $this->positions->ledger((int) $society->id, $end);

        return [
            'schema_version' => 2, 'kind' => 'society', 'period' => $period,
            'from' => $start, 'to' => $end,
            'society' => $society->name, 'cycle' => $cycle->name,
            'currency' => $society->currency_code ?: 'LSL',
            'members_count' => count(array_filter($members, fn ($m) => $m['status'] === 'active')),
            'members' => $memberStanding,
            'transactions' => $activity,
            'journals' => $this->journal->forPeriod((int) $society->id, $cycleId, $start, $end),
            'flows' => $this->totals($activity),
            'opening' => $opening,
            'position' => $closing,
            'ledger_liquid_change_cents' => $closing['liquid_assets'] - $opening['liquid_assets'],
            'generated_at' => now()->toIso8601String(),
            'note' => 'Monthly transactions come from original payment/disbursement records. '
                . 'Interest credited to borrowers and penalty waivers are non-cash events. '
                . 'Opening/closing ledger balances are posting-date based and may differ from captured cash events '
                . 'because of historical clearing, journal adjustments or unposted receipt records. '
                . 'An unposted penalty receipt is not evidence of banked funds.',
        ];
    }

    public function member(Society $society, int $cycleId, SocietyMembership $membership, string $period): array
    {
        [$start, $end] = self::bounds($period);
        if ((int) $membership->society_id !== (int) $society->id) {
            throw new \RuntimeException('Membership belongs to a different society.');
        }
        $cycle = $society->cycles()->findOrFail($cycleId);
        $entries = array_values(array_filter(
            $this->transactions->forPeriod((int) $society->id, $cycleId, $start, $end),
            fn ($row) => $row['membership_id'] === (int) $membership->id
        ));
        $position = $this->positions->member((int) $society->id,
            $cycleId, (int) $membership->id, $end);
        $flows = $this->totals($entries);

        return [
            'schema_version' => 2, 'kind' => 'member', 'period' => $period,
            'from' => $start, 'to' => $end,
            'society' => $society->name, 'cycle' => $cycle->name,
            'currency' => $society->currency_code ?: 'LSL',
            'member' => $membership->user?->name ?: $membership->user()->value('name'),
            'member_number' => $membership->member_number,
            'entries' => $entries, 'flows' => $flows,
            'position' => $position,
            // Backwards-compatible keys for previously frozen snapshot readers.
            'savings_to_date_cents' => $position['savings_cents'],
            'interest_credit_to_date_cents' => $position['interest_cents'],
            'contractual_loan_due_cents' => $position['loan_due_cents'],
            'generated_at' => now()->toIso8601String(),
            'note' => 'This is a provisional as-of-month-end payout outlook, NOT an approved payout. '
                . 'Gross outlook includes contributed savings and the member’s own refundable interest only. '
                . 'It EXCLUDES any future contributions, future interest, pooled-penalty shares, '
                . 'uncertified opening balances and payout adjustments. The after-debt illustration '
                . 'assumes outstanding loans and recognised penalties are settled before payout; '
                . 'it is NOT an automatic offset or commitment. Loan and penalty balances are as-of report date.',
        ];
    }
}
