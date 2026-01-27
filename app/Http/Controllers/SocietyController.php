<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Models\Member;
use App\Models\Cycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocietyController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | List all societies user belongs to
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $societies = auth()->user()
            ->societies()
            ->withCount('members')
            ->get();

        return view('societies.index', compact('societies'));
    }

    /*
    |--------------------------------------------------------------------------
    | Show create society form
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        return view('societies.create');
    }

    /*
    |--------------------------------------------------------------------------
    | Store new society + initial cycle
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'minimum_contribution' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0',
            'cycle_name' => 'required|string|max:255',
            'cycle_start_date' => 'required|date',
            'cycle_end_date' => 'required|date|after:cycle_start_date',
        ]);

        DB::transaction(function () use ($request) {

            $society = Society::create([
                'name' => $request->name,
                'minimum_contribution' => $request->minimum_contribution,
                'interest_rate' => $request->interest_rate,
            ]);

            // Initial cycle
            $cycle = Cycle::create([
                'society_id' => $society->id,
                'name' => $request->cycle_name,
                'start_date' => $request->cycle_start_date,
                'end_date' => $request->cycle_end_date,
                'status' => 'active',
            ]);

            // Creator becomes chairman
            Member::create([
                'user_id' => auth()->id(),
                'society_id' => $society->id,
                'role' => 'chairman',
                'status' => 'active',
                'joined_date' => now(),
            ]);
        });

        return redirect()
            ->route('societies.index')
            ->with('success', 'Society created successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Show edit society form
    |--------------------------------------------------------------------------
    */
    public function edit(Society $society)
    {
        $this->authorizeChairman($society);

        return view('societies.edit', compact('society'));
    }

    /*
    |--------------------------------------------------------------------------
    | Update society
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, Society $society)
    {
        $this->authorizeChairman($society);

        $request->validate([
            'name' => 'required|string|max:255',
            'minimum_contribution' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0',
        ]);

        $society->update($request->only([
            'name',
            'minimum_contribution',
            'interest_rate',
        ]));

        return redirect()
            ->route('societies.index')
            ->with('success', 'Society updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Delete society
    |--------------------------------------------------------------------------
    */
    public function destroy(Society $society)
    {
        $this->authorizeChairman($society);

        if ($society->cycles()->where('status', 'active')->exists()) {
            return back()->with('error', 'Close active cycle before deleting society.');
        }

        $society->delete();

        return redirect()
            ->route('societies.index')
            ->with('success', 'Society deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Society Dashboard (Active Cycle)
    |--------------------------------------------------------------------------
    */
    public function dashboard(Society $society)
    {
        $this->authorizeMember($society);
    
        $cycle = $society->activeCycle;
        abort_if(!$cycle, 403, 'No active cycle.');
    
        $stats = [
            'members' => $society->activeMembers()->count(),
    
            // 💰 All money that came IN during this cycle
            'total_contributions' => $cycle->transactions()
                ->whereIn('type', ['contribution', 'loan_repayment'])
                ->sum('amount'),
    
            // 🔁 Loan repayments only
            'total_repayments' => $cycle->transactions()
                ->where('type', 'loan_repayment')
                ->sum('amount'),
    
            // 📉 Loans still outstanding
            'active_loans' => $cycle->loans()
                ->whereIn('status', ['active', 'overdue'])
                ->count(),
    
            // 🏦 Available cash in the society
            'available_balance' => $society->availableBalance($cycle->id),
        ];
    
        return view('societies.dashboard', compact('society', 'cycle', 'stats'));
    }
    

    /*
    |--------------------------------------------------------------------------
    | Society Settings (Chairman only)
    |--------------------------------------------------------------------------
    */
    public function settings(Society $society)
    {
        $this->authorizeChairman($society);

        return view('societies.settings', compact('society'));
    }

    /*
    |--------------------------------------------------------------------------
    | Update Society Settings
    |--------------------------------------------------------------------------
    */
    public function updateSettings(Request $request, Society $society)
    {
        $this->authorizeChairman($society);

        $request->validate([
            'minimum_contribution' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0',
            'penalty_type' => 'required|in:fixed,percentage',
            'penalty_value' => 'required|numeric|min:0',
        ]);

        $society->update($request->only([
            'minimum_contribution',
            'interest_rate',
            'penalty_type',
            'penalty_value',
        ]));

        return back()->with('success', 'Settings updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL AUTHORIZATION HELPERS
    |--------------------------------------------------------------------------
    */
    protected function authorizeMember(Society $society)
    {
        if (!$society->members()
            ->where('user_id', auth()->id())
            ->exists()) {
            abort(403, 'Access denied.');
        }
    }

    protected function authorizeChairman(Society $society)
    {
        if (!$society->members()
            ->where('user_id', auth()->id())
            ->where('role', 'chairman')
            ->exists()) {
            abort(403, 'Chairman access only.');
        }
    }
}
