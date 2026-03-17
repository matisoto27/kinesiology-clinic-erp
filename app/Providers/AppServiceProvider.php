<?php

namespace App\Providers;

use App\Models\Actividad;
use App\Models\TipoActividad;
use Carbon\Carbon;
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
        Carbon::setLocale('es');

        View::composer(['principal', 'turnos.calendario'], function ($vista) {
            $vista->with('tiposActividad', Cache::remember('todos_tipos_actividad', now()->addHours(12), function () {
                return TipoActividad::with('actividades')->get();
            }));
        });

        View::composer(['actividades.turnos-disponibles', 'turnos.inicio'], function ($vista) {
            $vista->with('actividades', Cache::remember('todas_las_actividades', now()->addHours(12), fn() => Actividad::all()));
        });

        View::composer(['actividades-pacientes.crear'], function ($view) {
            $view->with('actividades', Cache::remember('actividades_generales', now()->addHours(12), function () {
                return Actividad::obtenerActividadesGenerales();
            }));
        });
    }
}
