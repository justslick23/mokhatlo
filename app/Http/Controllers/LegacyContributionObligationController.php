<?php

namespace App\Http\Controllers;

use App\Services\Legacy\ResolveLegacyReceiptObligations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class LegacyContributionObligationController extends Controller
{
    private function society(Request $request): int
    {
        $society = $request->attributes->get('currentSociety');
        abort_unless($society && $request->user()?->can('society.manage'), 403);
        return (int) $society->id;
    }

    private function run(int $societyId, int $runId): object
    {
        $run = DB::table('legacy_financial_import_runs')
            ->where('id', $runId)->where('society_id', $societyId)->first();
        abort_unless($run, 404);
        return $run;
    }

    public function index(Request $request)
    {
        $societyId = $this->society($request);
        $runs = DB::table('legacy_financial_import_runs')
            ->where('society_id', $societyId)->orderByDesc('id')->get();
        abort_if($runs->isEmpty(), 404);
        $runId = $request->query('run', $runs->first()->id);
        abort_unless(ctype_digit((string) $runId) && (int) $runId > 0, 404);
        $run = $this->run($societyId, (int) $runId);
        $cycle = DB::table('cycles')->where('id', $run->cycle_id)
            ->where('society_id', $societyId)->first();
        abort_unless($cycle, 404);
        $batch = DB::table('legacy_import_batches')->find($run->batch_id);
        abort_unless($batch, 404);

        $rows = DB::table('legacy_receipt_period_attributions as a')
            ->join('contribution_payments as p', 'p.id', '=', 'a.contribution_payment_id')
            ->join('legacy_record_maps as m', function ($join) use ($batch) {
                $join->on('m.target_id', '=', 'p.id')
                    ->where('m.source_id', '=', $batch->source_id)
                    ->where('m.source_table', '=', 'transactions')
                    ->where('m.target_table', '=', 'contribution_payments');
            })
            ->join('society_memberships as sm', 'sm.id', '=', 'p.society_membership_id')
            ->join('users as u', 'u.id', '=', 'sm.user_id')
            ->where('a.society_id', $societyId)
            ->where('a.cycle_id', $run->cycle_id)
            ->where('p.society_id', $societyId)
            ->where('p.cycle_id', $run->cycle_id)
            ->where('sm.society_id', $societyId)
            ->whereNull('a.reversed_at')
            ->select([
                'a.id', 'a.contribution_payment_id', 'a.period_start',
                'a.amount_cents', 'a.evidence_reference', 'a.allocated_at',
                'p.paid_on', 'p.society_membership_id', 'm.source_id_value as source_tx',
                'u.name as member_name', 'sm.member_number',
            ])
            ->orderBy('u.name')->orderBy('a.period_start')->orderBy('a.id')
            ->get();

        $links = DB::table('legacy_receipt_obligation_links')
            ->where('society_id', $societyId)->where('cycle_id', $run->cycle_id)
            ->whereNull('reversed_at')
            ->whereIn('legacy_receipt_period_attribution_id', $rows->pluck('id'))
            ->get()->keyBy('legacy_receipt_period_attribution_id');

        $obligations = DB::table('contribution_obligations')
            ->where('society_id', $societyId)->where('cycle_id', $run->cycle_id)
            ->orderBy('society_membership_id')->orderBy('period_start')->get()
            ->groupBy(fn ($o) => (int) $o->society_membership_id . ':' . substr($o->period_start, 0, 10));

        $rows->each(function ($r) use ($links, $obligations) {
            $r->link = $links->get($r->id);
            $r->possible_obligations = $obligations->get(
                (int) $r->society_membership_id . ':' . substr($r->period_start, 0, 10), collect()
            );
        });
        $summary = [
            'attributions' => $rows->count(),
            'linked' => $rows->filter(fn ($r) => $r->link !== null)->count(),
            'total_cents' => $rows->sum(fn ($r) => (int) $r->amount_cents),
            'linked_cents' => $rows->filter(fn ($r) => $r->link !== null)
                ->sum(fn ($r) => (int) $r->link->amount_cents),
        ];
        return view('legacy.obligation-review', compact('runs', 'run', 'cycle', 'rows', 'summary'));
    }

    public function link(Request $request, ResolveLegacyReceiptObligations $service,
        int $run, int $attribution)
    {
        $societyId = $this->society($request);
        $this->run($societyId, $run);
        $data = $request->validate([
            'obligation_id' => ['required', 'integer', 'min:1'],
            'evidence_reference' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        try {
            $service->link($societyId, $run, $attribution, (int) $data['obligation_id'],
                (int) $request->user()->id, $data['evidence_reference']);
        } catch (RuntimeException|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['obligation_id' => $e->getMessage()]);
        }
        return redirect()->route('legacy.obligations.index', ['run' => $run])
            ->with('status', 'Verified attribution linked to the existing monthly obligation. No cash movement occurred.');
    }

    public function createAndLink(Request $request, ResolveLegacyReceiptObligations $service,
        int $run, int $attribution)
    {
        $societyId = $this->society($request);
        $this->run($societyId, $run);
        $data = $request->validate([
            'required_amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'evidence_reference' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        try {
            $service->createAndLink($societyId, $run, $attribution,
                (int) $request->user()->id, $data['required_amount'],
                $data['due_date'], $data['evidence_reference']);
        } catch (RuntimeException|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['required_amount' => $e->getMessage()]);
        }
        return redirect()->route('legacy.obligations.index', ['run' => $run])
            ->with('status', 'Evidence-backed historical obligation created and receipt attribution linked. No new payment or journal was created.');
    }

    public function reverse(Request $request, ResolveLegacyReceiptObligations $service,
        int $run, int $link)
    {
        $societyId = $this->society($request);
        $this->run($societyId, $run);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        try {
            $service->reverse($societyId, $run, $link, (int) $request->user()->id, $data['reason']);
        } catch (RuntimeException|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }
        return redirect()->route('legacy.obligations.index', ['run' => $run])
            ->with('status', 'Obligation link reversed with an audit trail. The original receipt is unchanged.');
    }
}
