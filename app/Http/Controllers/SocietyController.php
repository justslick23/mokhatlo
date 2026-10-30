<?php

namespace App\Http\Controllers;

use App\Models\Society;
use App\Services\Societies\CreateSociety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocietyController extends Controller
{
    public function index(Request $request): View
    {
        $memberships = $request->user()
            ->memberships()
            ->with('society')
            ->where('status', 'active')
            ->whereHas('society', function ($query) {
                $query->where('status', 'active');
            })
            ->latest()
            ->get();

        return view('societies.index', compact('memberships'));
    }

    public function create(): View
    {
        return view('societies.create');
    }

    public function store(
        Request $request,
        CreateSociety $creator
    ): RedirectResponse {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:160',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $society = $creator->execute(
            $request->user(),
            $data
        );

        $request->session()->put(
            'active_society_id',
            $society->id
        );

        $request->session()->regenerate();

        setPermissionsTeamId($society->id);

        $request->user()->unsetRelation('roles');
        $request->user()->unsetRelation('permissions');

        return redirect()
            ->route('dashboard')
            ->with('status', 'Your society was created successfully.');
    }

    public function switch(
        Request $request,
        Society $society
    ): RedirectResponse {
        $isMember = $request->user()
            ->memberships()
            ->where('society_id', $society->id)
            ->where('status', 'active')
            ->exists();

        abort_unless(
            $isMember && $society->status === 'active',
            403,
            'You do not have access to this society.'
        );

        $request->session()->put(
            'active_society_id',
            $society->id
        );

        $request->session()->regenerate();

        setPermissionsTeamId($society->id);

        $request->user()->unsetRelation('roles');
        $request->user()->unsetRelation('permissions');

        return redirect()->route('dashboard');
    }
}
