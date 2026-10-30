<?php

namespace App\Http\Controllers;

use App\Services\Legacy\LegacyReceiptPeriodRules;
use App\Services\Legacy\ReviewLegacyReceiptPeriods;
use App\Services\Legacy\AuditLegacyFinancialImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class LegacyContributionAttributionController extends Controller
{
    private function societyId(Request $request): int
    {
        $society = $request->attributes->get('currentSociety');
        abort_unless($society && $request->user()?->can('society.manage'), 403);
        return (int) $society->id;
    }

    private function run(int $societyId, int $runId): object
    {
        $run = DB::table('legacy_financial_import_runs')
            ->where('society_id', $societyId)
            ->where('id', $runId)
            ->first();
        abort_unless($run, 404);
        return $run;
    }

    public function index(Request $request, AuditLegacyFinancialImport $auditor)
    {
        $societyId = $this->societyId($request);
        $runs = DB::table('legacy_financial_import_runs')
            ->where('society_id', $societyId)->orderByDesc('id')->get();
        abort_if($runs->isEmpty(), 404);

        $runId = $request->query('run', $runs->first()->id);
        abort_unless(ctype_digit((string) $runId) && (int) $runId > 0, 404);
        $run = $this->run($societyId, (int) $runId);
        $cycle = DB::table('cycles')->where('society_id', $societyId)
            ->where('id', $run->cycle_id)->first();
        abort_unless($cycle, 404);
        $batch = DB::table('legacy_import_batches')->find($run->batch_id);
        abort_unless($batch, 404);

        $receipts = DB::table('legacy_record_maps as m')
            ->join('contribution_payments as p', 'p.id', '=', 'm.target_id')
            ->join('society_memberships as sm', 'sm.id', '=', 'p.society_membership_id')
            ->join('users as u', 'u.id', '=', 'sm.user_id')
            ->where('m.source_id', $batch->source_id)
            ->where('m.source_table', 'transactions')
            ->where('m.target_table', 'contribution_payments')
            ->where('p.society_id', $societyId)
            ->where('p.cycle_id', $run->cycle_id)
            ->where('sm.society_id', $societyId)
            ->select([
                'p.id', 'p.amount', 'p.paid_on', 'p.society_membership_id',
                'p.contribution_obligation_id', 'm.source_id_value as original_id',
                'u.name as member_name', 'sm.member_number',
            ])
            ->orderBy('p.paid_on')->orderBy('p.id')->get();

        $allocations = DB::table('legacy_receipt_period_attributions')
            ->where('society_id', $societyId)->where('cycle_id', $run->cycle_id)
            ->whereIn('contribution_payment_id', $receipts->pluck('id'))
            ->orderBy('period_start')->orderBy('id')->get()
            ->groupBy('contribution_payment_id');

        $receiptRows = $receipts->map(function ($receipt) use ($allocations) {
            $all = $allocations->get($receipt->id, collect());
            $active = $all->whereNull('reversed_at');
            $receiptCents = \App\Support\Money::toCents((string) $receipt->amount);
            $attributed = $active->sum(fn ($entry) => (int) $entry->amount_cents);
            $receipt->amount_cents = $receiptCents;
            $receipt->attributed_cents = $attributed;
            $receipt->remaining_cents = $receiptCents - $attributed;
            $receipt->allocations = $all;
            return $receipt;
        });

        $summary = [
            'count' => $receiptRows->count(),
            'complete' => $receiptRows->filter(fn ($r) => $r->remaining_cents === 0)->count(),
            'total_cents' => $receiptRows->sum('amount_cents'),
            'attributed_cents' => $receiptRows->sum('attributed_cents'),
        ];

        return view('legacy.period-attribution', compact(
            'runs', 'run', 'cycle', 'receiptRows', 'summary', 'auditor'
        ));
    }

    public function store(Request $request, ReviewLegacyReceiptPeriods $service, int $run, int $payment)
    {
        $societyId = $this->societyId($request);
        $this->run($societyId, $run);
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'evidence_reference' => ['required', 'string', 'min:5', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $service->attribute(
                $societyId, $run, $payment, (int) $request->user()->id,
                $data['month'], $data['amount'], $data['evidence_reference'],
                $data['notes'] ?? null
            );
        } catch (\InvalidArgumentException|RuntimeException $e) {
            throw ValidationException::withMessages(['month' => $e->getMessage()]);
        }

        return redirect()->route('legacy.contributions.index', ['run' => $run])
            ->with('status', 'Month attribution recorded. No new cash or obligation was created.');
    }

    public function reverse(Request $request, ReviewLegacyReceiptPeriods $service, int $run, int $payment, int $attribution)
    {
        $societyId = $this->societyId($request);
        $this->run($societyId, $run);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        try {
            $service->reverse(
                $societyId, $run, $payment, $attribution,
                (int) $request->user()->id, $data['reason']
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }
        return redirect()->route('legacy.contributions.index', ['run' => $run])
            ->with('status', 'Attribution reversed with an audit trail. Original receipt and ledger are unchanged.');
    }
}
