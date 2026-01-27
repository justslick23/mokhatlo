<?php

namespace App\Http\Controllers;

use App\Mail\MemberAddedToSociety;
use App\Mail\NewMemberJoinedSociety;
use App\Models\Member;
use App\Models\Society;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MemberController extends Controller
{
    /**
     * Display a listing of members.
     */
    public function index(Society $society)
    {
        $this->authorizeAccess($society);

        $members = $society->members()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return view('members.index', compact('society', 'members'));
    }

    /**
     * Show the form for creating a new member.
     */
    public function create(Society $society)
    {
        $this->authorizeOfficer($society);

        return view('members.create', compact('society'));
    }

    /**
     * Store a newly created member.
     */
    public function store(Request $request, Society $society)
    {
        $this->authorizeOfficer($society);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email',
            'role'        => 'required|in:member,chairman,treasurer,secretary',
            'joined_date' => 'required|date',
        ]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name'     => $data['name'],
                'password' => bcrypt(str()->random(10)),
            ]
        );

        $member = Member::create([
            'user_id'     => $user->id,
            'society_id'  => $society->id,
            'role'        => $data['role'],
            'status'      => 'active',
            'joined_date' => $data['joined_date'],
        ]);

        // 🔔 Send email to the newly added member
        Mail::to($user->email)->send(new MemberAddedToSociety($user, $society, $member));

        // 🔔 Notify all other active members about the new member
        $this->notifyExistingMembers($society, $member);

        return redirect()
            ->route('societies.members.index', $society)
            ->with('success', 'Member added successfully.');
    }

    /**
     * Show the form for editing the member.
     */
    public function edit(Society $society, Member $member)
    {
        $this->authorizeOfficer($society);
        $this->ensureSameSociety($society, $member);

        return view('members.edit', compact('society', 'member'));
    }

    /**
     * Update member details.
     */
    public function update(Request $request, Society $society, Member $member)
    {
        $this->authorizeOfficer($society);
        $this->ensureSameSociety($society, $member);

        $data = $request->validate([
            'role'   => 'required|in:member,chairman,treasurer,secretary',
            'status' => 'required|in:active,inactive',
        ]);

        $member->update($data);

        return redirect()
            ->route('societies.members.index', $society)
            ->with('success', 'Member updated.');
    }

    /**
     * Remove member.
     */
    public function destroy(Society $society, Member $member)
    {
        $this->authorizeOfficer($society);
        $this->ensureSameSociety($society, $member);

        $member->delete();

        return back()->with('success', 'Member removed.');
    }

    /**
     * Update role only (quick action).
     */
    public function updateRole(Request $request, Society $society, Member $member)
    {
        $this->authorizeChairman($society);
        $this->ensureSameSociety($society, $member);

        $request->validate([
            'role' => 'required|in:member,chairman,treasurer,secretary',
        ]);

        $member->update(['role' => $request->role]);

        return back()->with('success', 'Role updated.');
    }

    /**
     * Toggle active/inactive.
     */
    public function toggleStatus(Society $society, Member $member)
    {
        $this->authorizeOfficer($society);
        $this->ensureSameSociety($society, $member);

        $member->update([
            'status' => $member->status === 'active' ? 'inactive' : 'active'
        ]);

        return back()->with('success', 'Member status updated.');
    }

    /* ===================== HELPERS ===================== */

    /**
     * Notify all existing society members about the new member.
     */
    protected function notifyExistingMembers(Society $society, Member $newMember)
    {
        // Get all other active members in the society
        $existingMembers = $society->members()
            ->where('id', '!=', $newMember->id)
            ->where('status', 'active')
            ->with('user')
            ->get();

        // Send notification to each existing member
        foreach ($existingMembers as $member) {
            Mail::to($member->user->email)->send(
                new NewMemberJoinedSociety($member->user, $society, $newMember)
            );
        }
    }

    protected function authorizeAccess(Society $society)
    {
        abort_unless(auth()->user()->isMemberOf($society), 403);
    }

    protected function authorizeOfficer(Society $society)
    {
        abort_unless(
            auth()->user()->isChairmanOf($society)
            || auth()->user()->isTreasurerOf($society)
            || auth()->user()->isSecretaryOf($society),
            403
        );
    }

    protected function authorizeChairman(Society $society)
    {
        abort_unless(auth()->user()->isChairmanOf($society), 403);
    }

    protected function ensureSameSociety(Society $society, Member $member)
    {
        abort_if($member->society_id !== $society->id, 404);
    }
}