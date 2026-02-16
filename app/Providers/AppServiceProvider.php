<?php

namespace App\Providers;

use App\Models\Actividad;
use App\Models\Egreso;
use App\Models\Pago;
use App\Models\Patologia;
use App\Models\Profesional;
use App\Models\TipoSintoma;
use App\Observers\EgresoObserver;
use App\Observers\PagoObserver;
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

        Egreso::observe(EgresoObserver::class);
        Pago::observe(PagoObserver::class);

        View::composer(['egresos.crear'], function ($vista) {
            $vista->with('profesionales', Cache::remember('profesionales_activos', now()->addHours(12), function () {
                return Profesional::where('activo', true)->orderByDesc('nombre')->get();
            }));
        });

        View::composer(['inicio', 'turnos.calendario', 'turnos.inicio'], function ($vista) {
            $vista->with('actividades', Cache::remember('todas_las_actividades', now()->addHours(12), fn () => Actividad::all()));
        });

        View::composer(['actividades-pacientes.crear'], function ($view) {
            $view->with('actividades', Cache::remember('actividades_generales', now()->addHours(12), function () {
                return Actividad::obtenerActividadesGenerales();
            }));
        });

        View::composer(['pacientes.crear', 'pacientes.editar'], function ($view) {
            $view->with('tipos_sintomas', Cache::remember('todos_tipos_sintomas', now()->addHours(12), function () {
                return TipoSintoma::all();
            }));
            $view->with('todasPatologias', Cache::remember('patologias_activas', now()->addHours(12), function () {
                return Patologia::where('activo', true)->get();
            }));
        });
    }
}
