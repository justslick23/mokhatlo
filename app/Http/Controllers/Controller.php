<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function financeAccess(\App\Models\Society $society)
{
    $member = $society->members()
        ->where('user_id', auth()->id())
        ->first();

    abort_if(
        !$member || !in_array($member->role, ['treasurer', 'chairman']),
        403,
        'You do not have permission to perform this action.'
    );

    return $member;
}

}
