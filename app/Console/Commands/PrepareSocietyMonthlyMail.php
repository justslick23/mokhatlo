<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Services\MokhatloMail\MonthlyStatementBuilder;
use App\Services\MokhatloMail\RecipientOutbox;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PrepareSocietyMonthlyMail extends Command
{
    protected $signature = 'mokhatlo:mail:monthly {--society= : Restrict to one society} {--month= : Explicit completed month YYYY-MM} {--dry-run : No database changes}';
    protected $description = 'Prepare previous-month private member and shared society PDF reports; idempotent.';

    public function handle(MonthlyStatementBuilder $builder, RecipientOutbox $outbox): int
    {
        if (! config('mokhatlo_mail.enabled')) {
            $this->warn('MOKHATLO_MAIL_ENABLED=false. No mail prepared.');
            return self::FAILURE;
        }
        $query = Society::query()->where('status', 'active');
        if ($this->option('society')) {
            $query->whereKey((int) $this->option('society'));
        }
        $prepared = 0;
        foreach ($query->get() as $society) {
            $today = CarbonImmutable::today($society->timezone ?: 'Africa/Maseru');
            $period = $this->option('month') ?: $today->subMonthNoOverflow()->format('Y-m');
            try {
                [$start, $end] = MonthlyStatementBuilder::bounds($period);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            if ($end >= $today->toDateString()) {
                $this->warn('Cannot send a current or future partial month.');
                return self::FAILURE;
            }
            if (! $this->option('month') && $today->day !== 1) {
                continue;
            }
            $cycles = $society->cycles()->whereIn('status', ['active', 'closed'])
                ->whereDate('start_date', '<=', $end)
                ->whereDate('end_date', '>=', $start)->get();
            foreach ($cycles as $cycle) {
                $people = $outbox->recipients((int) $society->id);
                if ($people->isEmpty()) {
                    $this->warn("Society #{$society->id}: no active verified member email addresses.");
                }
                if ($this->option('dry-run')) {
                    $this->info("DRY RUN: {$society->name}, cycle {$cycle->id}, {$period}, recipients {$people->count()}, PDFs " . (1 + $people->count()));
                    continue;
                }
                DB::transaction(function () use ($builder, $outbox, $society, $cycle, $period, $people, &$prepared) {
                    $key = [
                        'society_id' => $society->id, 'cycle_id' => $cycle->id,
                        'period' => $period, 'audience_key' => 'all',
                    ];
                    if (! DB::table('society_monthly_snapshots')->where($key)->exists()) {
                        DB::table('society_monthly_snapshots')->insertOrIgnore([
                            ...$key,
                            'payload' => json_encode($builder->society($society, $cycle->id, $period), JSON_THROW_ON_ERROR),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    $id = (int) DB::table('society_monthly_snapshots')->where($key)->value('id');
                    $prepared += $outbox->forSnapshot((int) $society->id, $id, 'all');
                    foreach ($people as $recipient) {
                        $audienceKey = 'member:' . $recipient->membership_id;
                        $personalKey = [...$key, 'audience_key' => $audienceKey];
                        if (! DB::table('society_monthly_snapshots')->where($personalKey)->exists()) {
                            $membership = $society->memberships()->with('user')->findOrFail($recipient->membership_id);
                            $data = $builder->member($society, $cycle->id, $membership, $period);
                            DB::table('society_monthly_snapshots')->insertOrIgnore([
                                ...$personalKey, 'payload' => json_encode($data, JSON_THROW_ON_ERROR),
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                        $memberSnapshotId = (int) DB::table('society_monthly_snapshots')->where($personalKey)->value('id');
                        $prepared += $outbox->forSnapshot((int) $society->id, $memberSnapshotId, $audienceKey);
                    }
                }, 3);
            }
        }
        $this->info("New email delivery records queued: {$prepared} (duplicates ignored). Run mokhatlo:mail:process to send.");
        return self::SUCCESS;
    }
}
