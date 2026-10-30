<?php

namespace App\Http\Controllers;

use App\Services\Legacy\LegacyReconciliationStatus;
use App\Services\Legacy\LegacyResolutionActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

final class LegacyFinalizationController extends Controller
{
    private function society(Request $request)
    {
        abort_unless($request->user()?->can('society.manage'), 403);
        $society = $request->attributes->get('currentSociety');
        abort_unless($society, 403);
        return $society;
    }

    public function index(Request $request, LegacyReconciliationStatus $service): View
    {
        $society = $this->society($request);
        $status = $service->forSociety($society);
        abort_unless($status['imported'], 404);
        $run = $status['run'];
        $reviews = DB::table('legacy_financial_review_items as r')
            ->leftJoin('legacy_penalty_review_actions as d', function ($join) {
                $join->on('d.review_item_id', '=', 'r.id')
                    ->whereRaw('d.id = (SELECT MAX(d2.id) FROM legacy_penalty_review_actions d2 WHERE d2.review_item_id = r.id)');
            })
            ->where('r.legacy_financial_import_run_id', $run->id)
            ->orderBy('r.id')
            ->get(['r.*','d.decision','d.evidence_reference']);
        return view('legacy.finalize', compact('society', 'status', 'run', 'reviews'));
    }

    public function penalty(Request $request, int $review, LegacyResolutionActions $actions): RedirectResponse
    {
        $society = $this->society($request);
        $data = $request->validate([
            'decision' => ['required','string','max:32'],
            'evidence_reference' => ['required','string','min:5','max:255'],
            'notes' => ['required','string','min:10','max:2000'],
        ]);
        try {
            $actions->reviewPenalty($society, $review, (int) $request->user()->id,
                $data['decision'], $data['evidence_reference'], $data['notes']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }
        return back()->with('status', 'Historical penalty evidence decision saved. No cash or loan balance was changed.');
    }

    public function prepare(Request $request, LegacyResolutionActions $actions): RedirectResponse
    {
        $society = $this->society($request);
        $data = $request->validate([
            'run_id' => ['required','integer','min:1'],
            'cash' => ['required','regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'bank' => ['required','regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'mobile' => ['required','regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'evidence_reference' => ['required','string','min:5','max:255'],
            'notes' => ['required','string','min:10','max:2000'],
        ]);
        try {
            $actions->prepareClearing($society, (int) $data['run_id'], (int) $request->user()->id,
                $data, $data['evidence_reference'], $data['notes']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['clearing' => $e->getMessage()]);
        }
        return back()->with('status', 'Clearing evidence prepared. No journal posted. An independent reviewer must approve.');
    }

    public function approve(Request $request, LegacyResolutionActions $actions): RedirectResponse
    {
        $society = $this->society($request);
        $runId = $request->validate(['run_id' => ['required','integer','min:1']])['run_id'];
        try {
            $actions->approveClearing($society, (int) $runId, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['clearing' => $e->getMessage()]);
        }
        return back()->with('status', 'Independent clearing approval saved; funds have NOT yet been reclassified.');
    }

    public function post(Request $request, LegacyResolutionActions $actions): RedirectResponse
    {
        $society = $this->society($request);
        $runId = $request->validate(['run_id' => ['required','integer','min:1']])['run_id'];
        try {
            $actions->postClearing($society, (int) $runId, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['clearing' => $e->getMessage()]);
        }
        return back()->with('status', 'Verified clearing reclassified to real asset ledger accounts using one balanced journal.');
    }
}
