<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Cycle;
use Illuminate\Support\Facades\View;
use App\Models\Society;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
    
            $currentSociety = null;
            $currentCycle   = null;
    
            if (session()->has('current_society_id')) {
                $currentSociety = Society::with('activeCycle')
                    ->find(session('current_society_id'));
    
                $currentCycle = $currentSociety?->activeCycle;
            }
    
            $view->with([
                'currentSociety' => $currentSociety,
                'currentCycle'   => $currentCycle,
            ]);
        });
    }
}
