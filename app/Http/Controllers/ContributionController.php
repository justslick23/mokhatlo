<?php

namespace App\Http\Controllers;

use App\Models\ContributionObligation;
use App\Services\Contributions\ContributionObligationCoverage;
use App\Services\Contributions\RecordContribution;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContributionController extends Controller
{
    public function index(Request $request, ContributionObligationCoverage $coverage): View
    {
        $user = $request->user();
        $canManage = $user->can('contributions.view');

        abort_unless(
            $canManage || $user->can('contributions.view_own'),
            403
        );

        $society = $request->attributes->get('currentSociety');
        $membership = $request->attributes->get('currentMembership');

        $cycles = $society->cycles()
            ->orderByDesc('start_date')
            ->get();

        $cycle = $request->filled('cycle_id')
            ? $society->cycles()
                ->whereKey($request->integer('cycle_id'))
                ->firstOrFail()
            : $society->cycles()
                ->where('status', 'active')
                ->first();

        $obligations = ContributionObligation::query()
            ->where('society_id', $society->id)
            ->when(
                $cycle,
                fn ($query) => $query->where('cycle_id', $cycle->id)
            )
            ->when(
                ! $cycle,
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->when(
                ! $canManage,
                fn ($query) => $query->where(
                    'society_membership_id',
                    $membership->id
                )
            )
            ->with(['membership.user'])
            ->withSum('payments as paid_total', 'amount')
            ->orderByDesc('period_start')
            ->orderBy('society_membership_id')
            ->paginate(20)
            ->withQueryString();

        // A historical receipt is NOT linked by the ordinary direct FK.
        // For imported cycles, display reviewed many-to-many coverage instead.
        // Only transform the already authorized, paginated obligations.
        $isImportedCycle = $cycle !== null && DB::table('legacy_financial_import_runs')
            ->where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->exists();

        if ($isImportedCycle) {
            $coverageRows = collect(
                $coverage->forCycle((int) $society->id, (int) $cycle->id)['rows']
            )->keyBy('obligation_id');

            $obligations->getCollection()->transform(function ($obligation) use ($coverageRows) {
                $row = $coverageRows->get((int) $obligation->id);

                if ($row !== null) {
                    // Maintain compatibility with the current Blade view's paid_total.
                    // Attribute changes are only for rendering; save() is never called.
                    $obligation->setAttribute('paid_total', Money::fromCents($row['paid_cents']));
                    $obligation->setAttribute('direct_paid_total', Money::fromCents($row['direct_paid_cents']));
                    $obligation->setAttribute('historical_paid_total', Money::fromCents($row['historical_paid_cents']));
                    $obligation->setAttribute('outstanding_total', Money::fromCents($row['outstanding_cents']));
                    $obligation->setAttribute('historical_excess_total', Money::fromCents($row['excess_cents']));
                }

                return $obligation;
            });
        }

        return view('contributions.index', compact(
            'society',
            'cycle',
            'cycles',
            'obligations',
            'canManage'
        ));
    }

    public function create(Request $request, int $obligation): View
    {
        abort_unless(
            $request->user()->can('contributions.record'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        $record = ContributionObligation::query()
            ->where('society_id', $society->id)
            ->whereKey($obligation)
            ->with(['cycle', 'membership.user', 'payments'])
            ->firstOrFail();

        abort_unless($record->cycle->status === 'active', 422);

        return view('contributions.record', [
            'society' => $society,
            'obligation' => $record,
            'paymentKey' => (string) Str::uuid(),
        ]);
    }

    public function store(
        Request $request,
        int $obligation,
        RecordContribution $recorder
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('contributions.record'),
            403
        );

        $data = $request->validate([
            'amount' => [
                'required',
                'string',
                'regex:/^\d{1,16}(\.\d{1,2})?$/',
            ],
            'paid_on' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:' . now('Africa/Maseru')->toDateString(),
            ],
            'payment_method' => [
                'required',
                Rule::in(['cash', 'bank_transfer', 'mobile_money']),
            ],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $society = $request->attributes->get('currentSociety');

        $recorder->execute(
            $society,
            $request->user(),
            $obligation,
            $data
        );

        return redirect()
            ->route('contributions.index', [
                'cycle_id' => $society->cycles()
                    ->where('status', 'active')
                    ->value('id'),
            ])
            ->with('status', 'Contribution recorded successfully.');
    }
}
