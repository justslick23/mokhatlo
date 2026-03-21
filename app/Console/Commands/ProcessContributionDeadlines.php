<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Models\Transaction;
use App\Mail\ContributionReminder;
use App\Mail\PenaltyApplied;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ProcessContributionDeadlines extends Command
{
    protected $signature   = 'contributions:process-deadlines';
    protected $description = 'Send contribution reminders for current month and apply penalties for previous month missed contributions';

    const REMINDER_DAYS_BEFORE = 5;

    public function handle(): void
    {
        $today         = Carbon::today();
        $previousMonth = $today->copy()->subMonth();

        Society::with(['activeCycle', 'members.user'])
            ->where('status', 'active')
            ->each(function (Society $society) use ($today, $previousMonth) {

                $cycle = $society->activeCycle;

                if (!$cycle) {
                    $this->warn("  [{$society->name}] No active cycle. Skipping.");
                    return;
                }

                $this->info("  Processing [{$society->name}]...");

                // ── REMINDERS: current month, not yet due ─────────────
                // Clamp due day to last day of month to avoid overflow
                // e.g. day 31 in February → 28 instead of rolling to March 3
                $dueDayThisMonth = $today->copy()->day(
                    min($society->contribution_due_day, $today->daysInMonth)
                );
                $daysUntilDue = $today->diffInDays($dueDayThisMonth, false);

                if ($daysUntilDue > 0 && $daysUntilDue <= self::REMINDER_DAYS_BEFORE) {
                    $unpaidThisMonth = $this->getDefaultersForMonth(
                        $society,
                        $cycle->id,
                        $today->month,
                        $today->year
                    );

                    if ($unpaidThisMonth->isNotEmpty()) {
                        $this->sendReminders($society, $unpaidThisMonth, (int) $daysUntilDue);
                    } else {
                        $this->info("    [{$society->name}] All members contributed this month ✓");
                    }
                }

                // ── PENALTIES: runs on 1st — checks last month ────────
                if ($today->day === 1) {
                    $defaultersLastMonth = $this->getDefaultersForMonth(
                        $society,
                        $cycle->id,
                        $previousMonth->month,
                        $previousMonth->year
                    );

                    if ($defaultersLastMonth->isEmpty()) {
                        $this->info("    [{$society->name}] No defaulters for {$previousMonth->format('F Y')} ✓");
                        return;
                    }

                    $this->applyPenalties($society, $cycle, $defaultersLastMonth, $previousMonth);
                }
            });

        $this->info('Done.');
    }

    // ── Members with no contribution in a specific month/year ─────
    protected function getDefaultersForMonth(
        Society $society,
        int $cycleId,
        int $month,
        int $year
    ) {
        $paidMemberIds = Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycleId)
            ->where('type', 'contribution')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->pluck('member_id')
            ->toArray();

        return $society->members()
            ->where('status', 'active')
            ->whereNotIn('id', $paidMemberIds)
            ->with('user')
            ->get();
    }

    // ── Send reminder emails (current month, within reminder window)
    protected function sendReminders(Society $society, $defaulters, int $daysLeft): void
    {
        foreach ($defaulters as $member) {
            Mail::to($member->user->email)
                ->send(new ContributionReminder($society, $member, $daysLeft));

            $this->info("    Reminder → {$member->user->email} ({$daysLeft} days left)");
        }
    }

    // ── Apply penalties for missed PREVIOUS month contributions ───
    protected function applyPenalties(
        Society $society,
        $cycle,
        $defaulters,
        Carbon $previousMonth
    ): void {
        foreach ($defaulters as $member) {

            // Guard: don't double-penalise for the same missed month
            $alreadyPenalised = Transaction::where('society_id', $society->id)
                ->where('member_id', $member->id)
                ->where('cycle_id', $cycle->id)
                ->where('type', 'penalty')
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->whereRaw("notes LIKE ?", ["%{$previousMonth->format('F Y')}%"])
                ->exists();

            if ($alreadyPenalised) {
                $this->warn("    Already penalised for {$previousMonth->format('F Y')} → {$member->user->email}. Skipping.");
                continue;
            }

            // ── Calculate penalty amount ───────────────────────────
            if ($society->penalty_type === 'fixed') {
                $penaltyAmount = (float) $society->penalty_value;
            } else {
                // Percentage of minimum_contribution
                $penaltyAmount = round(
                    $society->minimum_contribution * ($society->penalty_value / 100),
                    2
                );
            }

            // ── Record penalty transaction ─────────────────────────
            $penalty = Transaction::create([
                'society_id'       => $society->id,
                'member_id'        => $member->id,
                'cycle_id'         => $cycle->id,
                'type'             => 'penalty',
                'amount'           => $penaltyAmount,
                'transaction_date' => now()->toDateString(),
                'notes'            => $society->penalty_type === 'fixed'
                    ? "Auto-penalty: fixed M{$penaltyAmount} — missed contribution for {$previousMonth->format('F Y')}."
                    : "Auto-penalty: {$society->penalty_value}% of minimum contribution (M{$society->minimum_contribution}) — missed contribution for {$previousMonth->format('F Y')}.",
            ]);

            // ── Notify member ──────────────────────────────────────
            Mail::to($member->user->email)
                ->send(new PenaltyApplied($society, $member, $penalty));

            $this->info("    Penalty M{$penaltyAmount} → {$member->user->email} (missed {$previousMonth->format('F Y')})");
        }
    }
}