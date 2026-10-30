<?php

namespace App\Services\Payouts;

use App\Models\Society;
use App\Models\User;

final class AuthorizePayoutAction
{
    public function check(
        Society $society,
        User $actor,
        string $permission
    ): void {
        $isMember = $society->memberships()
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->exists();

        abort_unless(
            $isMember,
            403,
            'You are not an active society member.'
        );

        $previousTeam = getPermissionsTeamId();

        try {
            setPermissionsTeamId($society->id);

            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');

            abort_unless(
                $actor->can($permission),
                403,
                'You do not have permission for this payout action.'
            );
        } finally {
            setPermissionsTeamId($previousTeam);

            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
        }
    }
}
