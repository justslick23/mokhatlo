<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveSocietyContext
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $societyId = $request->session()
            ->get('active_society_id');

        if (!$societyId) {
            return redirect()->route('societies.index');
        }

        $membership = $request->user()
            ->memberships()
            ->where('society_id', $societyId)
            ->where('status', 'active')
            ->whereHas('society', function ($query) {
                $query->where('status', 'active');
            })
            ->with('society')
            ->first();

        if (!$membership) {

            $request->session()
                ->forget('active_society_id');

            setPermissionsTeamId(null);

            return redirect()
                ->route('societies.index')
                ->with(
                    'warning',
                    'Please select an active society.'
                );
        }

        setPermissionsTeamId($membership->society_id);

        $request->user()->unsetRelation('roles');
        $request->user()->unsetRelation('permissions');

        $request->attributes->set(
            'currentSociety',
            $membership->society
        );

        $request->attributes->set(
            'currentMembership',
            $membership
        );

        return $next($request);
    }
}