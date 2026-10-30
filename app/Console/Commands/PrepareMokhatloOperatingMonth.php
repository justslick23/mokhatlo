<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Services\Operations\MonthPreparationRules as Rules;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Creates missing current/future month minimums; NEVER posts receipts, journal entries or penalties. */
final class PrepareMokhatloOperatingMonth extends Command
{
    protected $signature = 'mokhatlo:ops:prepare-month
        {--society= : Limit to one society; omit for all active societies}
        {--month= : YYYY-MM; omit for the society-local current month}
        {--apply : Persist missing obligations; default is dry run}';

    protected $description = 'Preview or safely create missing obligations for the active operating month.';

    public function handle(): int
    {
        foreach (['cycles', 'cycle_policies', 'society_memberships', 'contribution_obligations'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Missing required table: {$table}.");
                return self::FAILURE;
            }
        }
        $societyId = $this->option('society');
        if ($societyId !== null && (! ctype_digit((string) $societyId) || (int) $societyId < 1)) {
            $this->error('--society must be a positive integer.');
            return self::FAILURE;
        }
        $query = Society::query()->where('status', 'active');
        if ($societyId !== null) {
            $query->whereKey((int) $societyId);
        }
        $societies = $query->get();
        if ($societies->isEmpty()) {
            $this->warn('No active societies selected. No changes made.');
            return self::SUCCESS;
        }
        $errors = 0;
        foreach ($societies as $society) {
            try {
                $this->prepareSociety($society);
            } catch (Throwable $e) {
                $errors++;
                $this->error("Society #{$society->id}: {$e->getMessage()}");
            }
        }
        return $errors ? self::FAILURE : self::SUCCESS;
    }

    private function prepareSociety(Society $society): void
    {
        $timezone = $society->timezone ?: 'Africa/Maseru';
        $today = CarbonImmutable::today($timezone)->toDateString();
        $month = (string) ($this->option('month') ?: substr($today, 0, 7));
        [$first, $last] = Rules::bounds($month);

        // The same society row lock is used by cycle activation. One generator run per society at a time.
        $work = function () use ($society, $today, $month, $first, $last) {
            $lock = Society::query()->whereKey($society->id)->lockForUpdate()->firstOrFail();
            $cycle = $lock->cycles()->where('status', 'active')->lockForUpdate()->first();
            if (! $cycle) {
                $this->warn("Society #{$society->id}: no active cycle. Skipped.");
                return;
            }
            if (! Rules::allowedMonth($month, $today, $cycle->start_date->toDateString(), $cycle->end_date->toDateString())) {
                throw new \RuntimeException("Month {$month} is historical, outside the cycle or a partial cycle month. No backfill is allowed.");
            }
            $policy = $cycle->policy()->first();
            if (! $policy) {
                throw new \RuntimeException('The active cycle has no policy.');
            }
            $minimum = (string) $policy->minimum_monthly_contribution;
            if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $minimum) || (float) $minimum <= 0) {
                throw new \RuntimeException('The cycle minimum is not a valid positive amount.');
            }
            $day = (int) $policy->contribution_due_day;
            $due = Rules::dueDate($month, $day);
            $members = DB::table('society_memberships')->where('society_id', $society->id)
                ->where('status', 'active')->orderBy('id')->get(['id', 'joined_at', 'left_at']);
            $found = 0;
            $created = 0;
            $skipped = 0;
            $seen = [];
            foreach ($members as $member) {
                if (! Rules::memberEligible($member->joined_at, $member->left_at, $first)) {
                    $skipped++;
                    continue;
                }
                $key = [
                    'society_id' => $society->id,
                    'cycle_id' => $cycle->id,
                    'society_membership_id' => $member->id,
                    'period_start' => $first,
                ];
                $count = DB::table('contribution_obligations')->where($key)->count();
                if ($count > 1) {
                    throw new \RuntimeException("Duplicate existing obligation for membership #{$member->id} / {$month}. Resolve manually before continuing.");
                }
                if ($count === 1) {
                    $found++;
                    continue;
                }
                $seen[] = (int) $member->id;
                if ($this->option('apply')) {
                    DB::table('contribution_obligations')->insert([
                        ...$key,
                        'due_date' => $due,
                        'required_amount' => $minimum,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            }
            $mode = $this->option('apply') ? 'APPLIED' : 'DRY RUN';
            $this->info("{$mode} — {$society->name} / cycle #{$cycle->id} / {$month} / due {$due}");
            $this->table(['Already exists', 'Missing', 'Created', 'Mid-month join / left'], [[$found, count($seen), $created, $skipped]]);
            if ($seen !== []) {
                $this->line('Membership IDs'.($this->option('apply') ? ' created' : ' to create').': '.implode(', ', $seen));
            }
            if ($skipped) {
                $this->warn('Mid-month joins or exits need human review; no automatic full-month charge.');
            }
            $this->line('No receipts, penalties, journal entries or historical obligations were modified.');
        };
        if ($this->option('apply')) {
            DB::transaction($work, 3);
        } else {
            // Dry run must make no database writes and deliberately does not lock the tables.
            $work();
        }
    }
}
