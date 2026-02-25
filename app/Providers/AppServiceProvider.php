<?php

namespace App\Providers;

use App\Models\Actividad;
use App\Models\Egreso;
use App\Models\Pago;
use App\Models\Patologia;
use App\Models\Profesional;
use App\Models\TipoActividad;
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

        View::composer(['principal', 'turnos.calendario'], function ($vista) {
            $vista->with('tiposActividad', Cache::remember('todos_tipos_actividad', now()->addHours(12), function () {
                return TipoActividad::with('actividades')->get();
            }));
        });

        View::composer(['actividades.turnos-disponibles', 'turnos.inicio'], function ($vista) {
            $vista->with('actividades', Cache::remember('todas_las_actividades', now()->addHours(12), fn () => Actividad::all()));
        });

        View::composer(['actividades-pacientes.crear'], function ($view) {
            $view->with('actividades', Cache::remember('actividades_generales', now()->addHours(12), function () {
                return Actividad::obtenerActividadesGenerales();
            }));
        });

        View::composer(['pacientes.crear', 'pacientes.editar'], function ($view) {
            $view->with('tiposSintoma', Cache::remember('tipos_sintoma_activos', now()->addHours(12), function () {
                return TipoSintoma::where('activo', true)
                    ->with('sintomasActivos')
                    ->get();
            }));
            $view->with('todasPatologias', Cache::remember('patologias_activas', now()->addHours(12), function () {
                return Patologia::where('activo', true)->orderBy('nombre')->get();
            }));
        });
    }
}
