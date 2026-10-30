<?php

namespace App\Http\Controllers;

use App\Services\Members\ManageInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class SocietyInvitationController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless(
            $request->user()->can('members.invite'),
            403
        );

        $allowedRoles = $request->user()->hasRole('owner')
            ? ['member', 'secretary', 'treasurer', 'chairperson']
            : ['member'];

        return view('members.invite', compact('allowedRoles'));
    }

    public function store(
        Request $request,
        ManageInvitations $manager
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('members.invite'),
            403
        );

        $request->merge([
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
        ]);

        $data = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'role_name' => [
                'required',
                Rule::in([
                    'member',
                    'secretary',
                    'treasurer',
                    'chairperson',
                ]),
            ],
        ]);

        $society = $request->attributes->get(
            'currentSociety'
        );

        $manager->invite(
            $society,
            $request->user(),
            $data['email'],
            $data['role_name']
        );

        return redirect()
            ->route('members.index')
            ->with(
                'status',
                'Invitation created and email notification submitted.'
            );
    }

    public function show(
        Request $request,
        string $token,
        ManageInvitations $manager
    ): View {
        $invitation = $manager->findPending($token);

        abort_unless(
            Str::lower($request->user()->email)
                === $invitation->email,
            403,
            'This invitation is for a different account.'
        );

        return view('invitations.show', compact(
            'invitation',
            'token'
        ));
    }

    public function accept(
        Request $request,
        string $token,
        ManageInvitations $manager
    ): RedirectResponse {
        $membership = $manager->accept(
            $token,
            $request->user()
        );

        $request->session()->put(
            'active_society_id',
            $membership->society_id
        );

        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with(
                'status',
                'You have successfully joined the society.'
            );
    }
}
