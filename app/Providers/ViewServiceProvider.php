<?php

namespace App\Providers;

use App\Models\Society;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (request()->route('society')) {
                $view->with(
                    'currentSociety',
                    request()->route('society')
                );
            }
        });
    }
}
