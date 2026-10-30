<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Services\Operations\MonthPreparationRules as Rules;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Read-only preflight. WARN means review needed, not that the savings cycle must be deactivated. */
final class CheckMokhatloOperations extends Command
{
    protected $signature = 'mokhatlo:ops:check {--society=1} {--strict : Return failure when any warning remains}';
    protected $description = 'Read-only operational health check for a live Mokhatlo savings society.';

    private array $checks = [];

    private function add(string $area, string $state, string $detail): void
    {
        $this->checks[] = [$area, $state, $detail];
    }

    public function handle(): int
    {
        $id = (string) $this->option('society');
        if (! ctype_digit($id) || (int) $id < 1) {
            $this->error('Please supply a positive --society ID.');
            return self::FAILURE;
        }
        $society = Society::query()->find((int) $id);
        if (! $society) {
            $this->error('Society not found.');
            return self::FAILURE;
        }
        $today = CarbonImmutable::today($society->timezone ?: 'Africa/Maseru');
        $month = $today->format('Y-m');
        $this->info("Operational readiness — {$society->name} — {$today->toDateString()}");
        $this->add('Society', $society->status === 'active' ? 'PASS' : 'FAIL', 'Society status: '.$society->status);

        $cycles = $society->cycles()->where('status', 'active')->get();
        $cycle = $cycles->first();
        $this->add('Active savings cycle', $cycles->count() === 1 ? 'PASS' : 'FAIL',
            $cycles->count().' active cycle(s)'.($cycle ? ', #'.$cycle->id : ''));

        if ($cycle) {
            $policy = $cycle->policy()->first();
            $this->add('Cycle rules', $policy ? 'PASS' : 'FAIL',
                $policy ? 'Monthly minimum M'.(string) $policy->minimum_monthly_contribution.
                    ', due day '.(string) $policy->contribution_due_day : 'No cycle policy');
            $periodOk = $today->betweenIncluded($cycle->start_date, $cycle->end_date);
            $this->add('Current cycle dates', $periodOk ? 'PASS' : 'FAIL',
                $cycle->start_date->toDateString().' to '.$cycle->end_date->toDateString());
            $this->checkObligations($society, $cycle, $today, $month);
            $this->checkLoanBasics($society, (int) $cycle->id);
        }
        $this->checkLedger($society);
        $this->checkMail($society);
        $this->checkReports($society);
        $this->checkPayoutSafeguard();

        $this->table(['Check', 'Result', 'Details'], $this->checks);
        $fails = count(array_filter($this->checks, fn ($row) => $row[1] === 'FAIL'));
        $warns = count(array_filter($this->checks, fn ($row) => $row[1] === 'WARN'));
        $this->line('PASS: '.(count($this->checks) - $fails - $warns)."  WARN: {$warns}  FAIL: {$fails}");
        $this->line('Read-only check: no financial or mail records were written.');
        $this->line('WARN items can be worked on while the active cycle continues; FAIL items need correction.');
        return $fails || ($this->option('strict') && $warns) ? self::FAILURE : self::SUCCESS;
    }

    private function checkObligations(Society $society, $cycle, CarbonImmutable $today, string $month): void
    {
        if (! Schema::hasTable('contribution_obligations')) {
            $this->add('Monthly savings', 'FAIL', 'contribution_obligations table missing');
            return;
        }
        if (! Rules::allowedMonth($month, $today->toDateString(), $cycle->start_date->toDateString(), $cycle->end_date->toDateString())) {
            $this->add('Monthly savings', 'WARN', 'Current month outside full-cycle month; manual review');
            return;
        }
        [$first] = Rules::bounds($month);
        $members = DB::table('society_memberships')->where('society_id', $society->id)
            ->where('status', 'active')->get(['id', 'joined_at', 'left_at']);
        $eligible = $members->filter(fn ($m) => Rules::memberEligible($m->joined_at, $m->left_at, $first));
        $existing = DB::table('contribution_obligations')->where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)->whereDate('period_start', $first)
            ->selectRaw('society_membership_id, COUNT(*) AS n')
            ->groupBy('society_membership_id')->get()->keyBy('society_membership_id');
        $missing = $eligible->filter(fn ($m) => ! isset($existing[$m->id]))->count();
        $duplicates = $existing->filter(fn ($r) => (int) $r->n > 1)->count();
        $this->add('Monthly savings', $duplicates ? 'FAIL' : ($missing ? 'WARN' : 'PASS'),
            "{$month}: {$eligible->count()} eligible members, {$missing} missing obligations, {$duplicates} duplicates");
    }

    private function checkLoanBasics(Society $society, int $cycleId): void
    {
        foreach (['loans', 'loan_repayments', 'loan_penalties', 'contribution_penalties'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->add('Loans and penalties', 'FAIL', "{$table} table missing");
                return;
            }
        }
        $open = DB::table('loans')->where('society_id', $society->id)->where('cycle_id', $cycleId)
            ->where('status', 'disbursed')->count();
        $this->add('Loan records', 'PASS', "{$open} active loan(s). Open balances are normal during an ongoing cycle.");
        $approvalRoutes = Route::has('loans.approve') || Route::has('loans.reject');
        $this->add('Loan capture policy', $approvalRoutes ? 'WARN' : 'PASS',
            $approvalRoutes ? 'Approval/rejection routes still exist. Confirm captured loans are immediately disbursed under your no-approval policy.'
                : 'No legacy loan approval routes detected. Check actual loan creation behavior in staging.');
        if (Schema::hasTable('legacy_financial_review_items')) {
            $pending = DB::table('legacy_financial_review_items as r')
            ->join(
                'legacy_financial_import_runs as run',
                'run.id',
                '=',
                'r.legacy_financial_import_run_id'
            )
            ->where('run.society_id', $society->id)
            ->where('r.resolution_status', 'pending')
            ->count();
            $this->add('Historical penalty evidence', $pending ? 'WARN' : 'PASS',
                "{$pending} unresolved historical review item(s). Linked native penalties are not duplicated.");
        }
    }

    private function checkLedger(Society $society): void
    {
        if (! Schema::hasTable('ledger_transactions') || ! Schema::hasTable('ledger_lines')) {
            $this->add('Financial journal', 'FAIL', 'Ledger tables missing');
            return;
        }
        // Convert every amount to integer cents; do not compare floats.
        $unbalanced = 0;
        $orphans = 0;
        $txs = DB::table('ledger_transactions')->where('society_id', $society->id)->pluck('id');
        foreach ($txs->chunk(500) as $batch) {
            $rows = DB::table('ledger_lines')->whereIn('ledger_transaction_id', $batch)->get([
                'ledger_transaction_id', 'debit_amount', 'credit_amount',
            ])->groupBy('ledger_transaction_id');
            foreach ($batch as $transactionId) {
                $lines = $rows->get($transactionId, collect());
                if ($lines->isEmpty()) {
                    $orphans++;
                    continue;
                }
                $debit = $lines->sum(fn ($r) => Money::toCents((string) $r->debit_amount));
                $credit = $lines->sum(fn ($r) => Money::toCents((string) $r->credit_amount));
                if ($debit !== $credit) {
                    $unbalanced++;
                }
            }
        }
        $this->add('Financial journal', ($unbalanced || $orphans) ? 'FAIL' : 'PASS',
            "{$txs->count()} journals; {$unbalanced} unbalanced; {$orphans} empty");
    }

    private function checkMail(Society $society): void
    {
        foreach (['society_mail_events', 'society_mail_deliveries', 'society_monthly_snapshots'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->add('Email and PDF outbox', 'FAIL', "{$table} table missing; apply existing mail migration");
                return;
            }
        }
        $enabled = (bool) config('mokhatlo_mail.enabled', false);
        $started = config('mokhatlo_mail.started_at');
        $mailer = (string) config('mail.default', '');
        $realMailer = in_array($mailer, ['smtp', 'ses', 'postmark', 'resend', 'mailgun'], true);
        $this->add('Transaction emails', $enabled && $started && $realMailer ? 'PASS' : 'WARN',
            'Enabled: '.($enabled ? 'yes' : 'no').', UTC activation: '.($started ?: 'unset').', transport: '.($mailer ?: 'unset'));
        $members = DB::table('society_memberships as sm')->join('users as u', 'u.id', '=', 'sm.user_id')
            ->where('sm.society_id', $society->id)->where('sm.status', 'active')
            ->get(['u.email', 'u.email_verified_at']);
        $missing = $members->filter(fn ($m) => ! $m->email || ! $m->email_verified_at)->count();
        $this->add('Email recipients', $members->isNotEmpty() && $missing === 0 ? 'PASS' : 'WARN',
            "{$members->count()} active members; {$missing} without verified email");
        $states = DB::table('society_mail_deliveries')->where('society_id', $society->id)
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $bad = (int) ($states['exhausted'] ?? 0);
        $waiting = (int) ($states['pending'] ?? 0) + (int) ($states['retry'] ?? 0);
        $this->add('Email deliveries', $bad ? 'WARN' : 'PASS',
            "{$waiting} pending/retry, {$bad} exhausted, ".(int) ($states['sent'] ?? 0).' accepted by transport');
    }

    private function checkReports(Society $society): void
    {
        $builder = class_exists(\App\Services\MokhatloMail\MonthlyStatementBuilder::class);
        $pdf = class_exists(\Barryvdh\DomPDF\Facade\Pdf::class);
        $views = view()->exists('pdf.mokhatlo-member') && view()->exists('pdf.mokhatlo-society');
        $this->add('Monthly PDF reports', $builder && $pdf && $views ? 'PASS' : 'FAIL',
            'Builder: '.($builder ? 'yes' : 'no').', DomPDF: '.($pdf ? 'yes' : 'no').', templates: '.($views ? 'yes' : 'no'));
        $today = CarbonImmutable::today($society->timezone ?: 'Africa/Maseru');
        $previous = $today->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        $snapshots = Schema::hasTable('society_monthly_snapshots')
            ? DB::table('society_monthly_snapshots')->where('society_id', $society->id)
                ->where('period', $previous)->count()
            : 0;
        $this->add('Previous-month reports', $snapshots ? 'PASS' : 'WARN',
            "{$previous}: {$snapshots} frozen snapshot(s). Until the 1st, preview completed months only.");
    }

    private function checkPayoutSafeguard(): void
    {
        $guard = class_exists(\App\Http\Middleware\BlockUnsafeLegacyPayouts::class);
        $this->add('Payout safety', $guard ? 'WARN' : 'FAIL',
            $guard ? 'Guard class exists; confirm it is attached to payout routes before any final payout' : 'Guard middleware missing; do not approve cycle payouts');
    }
}
