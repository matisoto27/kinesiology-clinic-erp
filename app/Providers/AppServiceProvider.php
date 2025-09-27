<?php

namespace App\Providers;

use App\Models\Actividad;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

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
        View::composer('inicio', function ($view) {
            $view->with('actividades', Cache::remember('todas_actividades', now()->addHours(12), function () {
                return Actividad::all();
            }));
        });
    }
}
