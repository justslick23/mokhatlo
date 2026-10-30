<?php

namespace App\Http\Controllers;

use App\Models\Society;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CycleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()->can('cycles.view'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        $cycles = $society->cycles()
            ->with('policy')
            ->orderByDesc('start_date')
            ->paginate(12);

        $activeCycle = $society->cycles()
            ->where('status', 'active')
            ->first();

        return view('cycles.index', compact(
            'society',
            'cycles',
            'activeCycle'
        ));
    }

    public function create(Request $request): View
    {
        abort_unless(
            $request->user()->can('cycles.create'),
            403
        );

        return view('cycles.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->can('cycles.create'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        $request->merge([
            'code' => Str::upper(
                trim((string) $request->input('code'))
            ),
        ]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:160',
            ],

            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('cycles', 'code')
                    ->where('society_id', $society->id),
            ],

            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],

            'minimum_monthly_contribution' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999999999.99',
                'decimal:0,2',
            ],

            // Days 1-28 exist in every calendar month.
            'contribution_due_day' => [
                'required',
                'integer',
                'between:1,28',
            ],

            'contribution_grace_days' => [
                'required',
                'integer',
                'between:0,30',
            ],

            'contribution_penalty_percent' => [
                'required',
                'numeric',
                'between:0,100',
            ],

            'loan_interest_percent' => [
                'required',
                'numeric',
                'between:0,100',
            ],

            'loan_interest_basis' => [
                'required',
                Rule::in(['per_term', 'per_month']),
            ],

            'loan_max_term_months' => [
                'required',
                'integer',
                'between:1,60',
            ],

            'loan_penalty_percent' => [
                'required',
                'numeric',
                'between:0,100',
            ],
        ]);

        DB::transaction(function () use ($society, $data) {
            $cycle = $society->cycles()->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => 'draft',
                'active_slot' => null,
            ]);

            $cycle->policy()->create([
                'society_id' => $society->id,
                'version' => 1,

                'minimum_monthly_contribution' =>
                    $data['minimum_monthly_contribution'],

                'contribution_due_day' =>
                    $data['contribution_due_day'],

                'contribution_grace_days' =>
                    $data['contribution_grace_days'],

                'contribution_penalty_percent' =>
                    $data['contribution_penalty_percent'],

                'loan_interest_percent' =>
                    $data['loan_interest_percent'],

                'loan_interest_method' => 'flat',

                'loan_interest_basis' =>
                    $data['loan_interest_basis'],

                'loan_max_term_months' =>
                    $data['loan_max_term_months'],

                'loan_penalty_percent' =>
                    $data['loan_penalty_percent'],
            ]);
        }, 3);

        return redirect()
            ->route('cycles.index')
            ->with(
                'status',
                'Savings cycle created successfully as a draft.'
            );
    }

    public function activate(
        Request $request,
        int $cycle
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('cycles.activate'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        DB::transaction(function () use ($society, $cycle) {

            // Lock the society to serialize activation requests.
            Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Never resolve a cycle outside the selected society.
            $record = $society->cycles()
                ->whereKey($cycle)
                ->lockForUpdate()
                ->firstOrFail();

            if ($record->status !== 'draft') {
                throw ValidationException::withMessages([
                    'cycle' => 'Only draft cycles can be activated.',
                ]);
            }

            if (! $record->policy()->exists()) {
                throw ValidationException::withMessages([
                    'cycle' => 'Configure the cycle policy before activation.',
                ]);
            }

            $alreadyActive = $society->cycles()
                ->where('status', 'active')
                ->exists();

            if ($alreadyActive) {
                throw ValidationException::withMessages([
                    'cycle' => 'Close the active cycle before activating another.',
                ]);
            }

            $record->update([
                'status' => 'active',
                'active_slot' => 1,
                'activated_at' => now(),
            ]);

            app(
                \App\Services\Contributions\GenerateContributionObligations::class
            )->forCycle($record);
            
        }, 3);

        return redirect()
            ->route('cycles.index')
            ->with(
                'status',
                'Savings cycle activated successfully.'
            );
    }
}
