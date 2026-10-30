<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\Society;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Set the missing financial policy on an existing draft cycle; never import or repost money. */
final class CyclePolicySetupController extends Controller
{
    public function edit(Request $request, Cycle $cycle): View
    {
        abort_unless($request->user()->can('cycles.activate'), 403);
        $society = $request->attributes->get('currentSociety');
        abort_unless($society && (int) $cycle->society_id === (int) $society->id, 404);
        abort_unless($cycle->status === 'draft', 409, 'Policy changes are allowed only before activation.');

        $cycle->load('policy');
        return view('cycles.policy', compact('cycle', 'society'));
    }

    public function update(Request $request, Cycle $cycle): RedirectResponse
    {
        abort_unless($request->user()->can('cycles.activate'), 403);
        $society = $request->attributes->get('currentSociety');
        abort_unless($society && (int) $cycle->society_id === (int) $society->id, 404);

        // Same policy rules as the original CycleController::store() in Phase 2.
        // 29-31 (month-end) is not supported by its due-day validator.
        $data = $request->validate([
            'minimum_monthly_contribution' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99', 'decimal:0,2'],
            'contribution_due_day' => ['required', 'integer', 'between:1,28'],
            'contribution_grace_days' => ['required', 'integer', 'between:0,60'],
            'contribution_penalty_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,5'],
            'loan_interest_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,5'],
            'loan_interest_method' => ['required', Rule::in(['flat', 'reducing_balance'])],
            'loan_interest_basis' => ['required', Rule::in(['per_term', 'monthly'])],
            'loan_max_term_months' => ['required', 'integer', 'between:1,120'],
            'loan_penalty_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,5'],
        ]);

        DB::transaction(function () use ($society, $cycle, $data): void {
            Society::query()->whereKey($society->id)->lockForUpdate()->firstOrFail();
            $locked = Cycle::query()
                ->where('society_id', $society->id)
                ->whereKey($cycle->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($locked->status === 'draft', 409, 'Cannot change an active or closed cycle policy.');

            $policy = $locked->policy()->first();
            if ($policy) {
                $policy->fill($data);
                $policy->save();
            } else {
                $locked->policy()->create([
                    'society_id' => $society->id,
                    'version' => 1,
                    ...$data,
                ]);
            }
        });

        return redirect()->route('cycles.index')
            ->with('status', 'Cycle policy saved. Verify the settings, then activate the existing cycle.');
    }
}
