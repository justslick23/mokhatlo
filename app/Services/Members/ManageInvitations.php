<?php

namespace App\Services\Members;

use App\Models\Society;
use App\Models\SocietyInvitation;
use App\Models\SocietyMembership;
use App\Models\User;
use App\Notifications\SocietyInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class ManageInvitations
{
    public function invite(
        Society $society,
        User $actor,
        string $email,
        string $roleName
    ): SocietyInvitation {
        $email = Str::lower(trim($email));

        // Only owners may invite members into official roles.
        $allowedRoles = $actor->hasRole('owner')
            ? ['member', 'secretary', 'treasurer', 'chairperson']
            : ['member'];

        if (! in_array($roleName, $allowedRoles, true)) {
            throw ValidationException::withMessages([
                'role_name' => 'You cannot assign this role.',
            ]);
        }

        $token = Str::random(64);

        $invitation = DB::transaction(function () use (
            $society,
            $actor,
            $email,
            $roleName,
            $token
        ) {
            // Serialize invitation operations for this society.
            Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyMember = $society->memberships()
                ->whereHas('user', function ($query) use ($email) {
                    $query->where('email', $email);
                })
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'email' => 'This user already has a membership in this society.',
                ]);
            }

            // Revoke any previous pending invitation.
            $society->invitations()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                ]);

            return $society->invitations()->create([
                'email' => $email,
                'role_name' => $roleName,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $actor->id,
                'expires_at' => now()->addDays(7),
            ]);
        }, 3);

        Notification::route('mail', $email)->notify(
            new SocietyInvitationNotification(
                $society->name,
                $actor->name,
                $token,
                $invitation->expires_at
                    ->timezone($society->timezone)
                    ->format('d M Y H:i')
            )
        );

        return $invitation;
    }

    public function findPending(string $token): SocietyInvitation
    {
        abort_unless(
            strlen($token) === 64 && ctype_alnum($token),
            404
        );

        return SocietyInvitation::query()
            ->with('society')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }

    public function accept(
        string $token,
        User $user
    ): SocietyMembership {
        $previousTeamId = getPermissionsTeamId();

        try {
            return DB::transaction(function () use ($token, $user) {
                abort_unless(
                    strlen($token) === 64 && ctype_alnum($token),
                    404
                );

                $invitation = SocietyInvitation::query()
                    ->where(
                        'token_hash',
                        hash('sha256', $token)
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($invitation->isPending(), 404);

                abort_unless(
                    $user->hasVerifiedEmail()
                    && Str::lower($user->email) === $invitation->email,
                    403,
                    'This invitation belongs to another email address.'
                );

                $society = Society::query()
                    ->whereKey($invitation->society_id)
                    ->where('status', 'active')
                    ->firstOrFail();

                $existing = SocietyMembership::query()
                    ->where('society_id', $society->id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'invitation' => 'You already have a membership in this society.',
                    ]);
                }

                $allowedRoles = [
                    'member',
                    'secretary',
                    'treasurer',
                    'chairperson',
                ];

                abort_unless(
                    in_array(
                        $invitation->role_name,
                        $allowedRoles,
                        true
                    ),
                    422
                );

                $role = Role::query()
                    ->where('society_id', $society->id)
                    ->where('name', $invitation->role_name)
                    ->where('guard_name', 'web')
                    ->firstOrFail();

                $membership = SocietyMembership::create([
                    'society_id' => $society->id,
                    'user_id' => $user->id,
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                $membership->update([
                    'member_number' => 'MK' . str_pad(
                        (string) $membership->id,
                        8,
                        '0',
                        STR_PAD_LEFT
                    ),
                ]);

                setPermissionsTeamId($society->id);

                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');

                $user->assignRole($role);

                $activeCycle = $society->cycles()
                ->where('status', 'active')
                ->first();
            
            if ($activeCycle) {
                app(
                    \App\Services\Contributions\GenerateContributionObligations::class
                )->forMembership(
                    $activeCycle,
                    $membership
                );
            }
            
                $invitation->update([
                    'accepted_at' => now(),
                ]);

                return $membership;
            }, 3);
        } finally {
            setPermissionsTeamId($previousTeamId);

            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        }
    }
}
