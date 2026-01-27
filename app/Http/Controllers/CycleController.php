<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Models\Cycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CycleController extends Controller
{
    public function index(Society $society)
    {
        $cycles = $society->cycles()->latest()->get();
        return view('cycles.index', compact('society', 'cycles'));
    }

    public function create(Society $society)
    {
        return view('cycles.create', compact('society'));
    }

    public function store(Request $request, Society $society)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        DB::transaction(function () use ($society, $request) {

            // Close current active cycle
            $society->cycles()
                ->where('status', 'active')
                ->update(['status' => 'completed']);

            // Create new cycle
            Cycle::create([
                'society_id' => $society->id,
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => 'active',
            ]);
        });

        return redirect()
            ->route('societies.cycles.index', $society)
            ->with('success', 'New cycle started.');
    }

    public function close(Society $society, Cycle $cycle)
    {
        if ($cycle->status !== 'active') {
            return back()->with('error', 'Cycle already closed.');
        }

        $cycle->update(['status' => 'completed']);

        return back()->with('success', 'Cycle closed.');
    }

    public function show(Society $society, Cycle $cycle)
{
    // Ensure the cycle belongs to the society
    abort_unless($cycle->society_id === $society->id, 404);
    
    // Load any necessary relationships
    $cycle->load(['transactions', 'loans']);
    
    return view('cycles.show', compact('society', 'cycle'));
}
}
