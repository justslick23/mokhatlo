<?php

namespace App\Http\Middleware;

use Closure;

class SetCurrentSociety
{
    public function handle($request, Closure $next)
    {
        if (auth()->check()) {

            // Society
            if (!session()->has('current_society_id')) {
                $member = auth()->user()->members()->first();
                if ($member) {
                    session(['current_society_id' => $member->society_id]);
                }
            }

            // Cycle
            if (session()->has('current_society_id') && !session()->has('current_cycle_id')) {
                $cycle = \App\Models\Cycle::where('society_id', session('current_society_id'))
                    ->where('status', 'active')
                    ->first();

                if ($cycle) {
                    session(['current_cycle_id' => $cycle->id]);
                }
            }
        }

        return $next($request);
    }
}
