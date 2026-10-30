<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()->can('members.view'),
            403
        );

        $society = $request->attributes->get(
            'currentSociety'
        );

        $members = $society->memberships()
            ->with(['user.roles'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $pendingInvitations = $society->invitations()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->limit(10)
            ->get();

        return view('members.index', compact(
            'society',
            'members',
            'pendingInvitations'
        ));
    }
}
