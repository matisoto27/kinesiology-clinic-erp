<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\ActividadComboController;
use App\Http\Controllers\ActividadController;
use App\Http\Controllers\ActividadPacienteController;
use App\Http\Controllers\NotaTurnoController;
use App\Http\Controllers\ObraSocialController;
use App\Http\Controllers\ObraSocialPacienteController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PrecioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.acceso'])->group(function () {
    Route::controller(ActividadController::class)->group(function () {
        Route::get('/actividades', 'inicio');
        Route::get('/actividades/{id}/combos', 'obtenerCombos');
        Route::get('/actividades/{id}/turnos-disponibles', 'obtenerTurnosDisponibles');
    });
    Route::view('/actividades/turnos-disponibles', 'actividades.turnos-disponibles')->name('actividades.turnos-disponibles');

    Route::controller(ActividadComboController::class)->group(function () {
        Route::get('/actividades-combos/{id}/precio-vigente', 'obtenerPrecioVigente');
    });

    Route::controller(ActividadPacienteController::class)->group(function () {
        Route::post('/actividades-pacientes', 'store')->name('actividades-pacientes.store');
    });

    Route::view('/actividades-pacientes/general/crear', 'actividades-pacientes.general.crear')->name('actividades-pacientes.general.crear');
    Route::view('/actividades-pacientes/kinesiologia/orden/crear', 'actividades-pacientes.kinesiologia.con-orden.crear')->name('actividades-pacientes.kinesiologia.con-orden.crear');
    Route::view('/actividades-pacientes/kinesiologia/sin-orden/crear', 'actividades-pacientes.kinesiologia.sin-orden.crear')->name('actividades-pacientes.kinesiologia.sin-orden.crear');

    Route::livewire('/actividades-pacientes', 'actividades-pacientes.inicio')->name('actividades-pacientes.inicio');
    Route::livewire('/actividades-pacientes/aplicar-orden', 'actividades-pacientes.aplicar-orden')->name('actividades-pacientes.aplicar-orden');

    Route::controller(ObraSocialController::class)->group(function () {
        Route::get('/buscar-obras-sociales', 'buscarPorNombre');
    });

    Route::controller(ObraSocialPacienteController::class)->group(function () {
        Route::get('/obras-sociales-pacientes/crear', 'crear')->name('obras-sociales-pacientes.crear');
        Route::post('/obras-sociales-pacientes', 'almacenar')->name('obras-sociales-pacientes.almacenar');
    });

    Route::controller(PacienteController::class)->group(function () {
        Route::view('/pacientes', 'pacientes.inicio')->name('pacientes.inicio');
        Route::get('/buscar-pacientes', 'buscarPorNombre');
        Route::get('/pacientes/{id}/actividades-generales-sin-suscripcion', 'obtenerActividadesGeneralesSinSuscripcion');
        Route::get('/pacientes/{id}/inscripcion-dual/pendiente', 'obtenerInscripcionDualPendiente');
        Route::get('/pacientes/{id}/inscripcion-dual/preview', 'obtenerPreviewInscripcionDual');
        Route::delete('/pacientes/{paciente}', 'eliminar')->name('pacientes.eliminar');
    });
    Route::livewire('/pacientes/crear', 'pacientes.crear')->name('pacientes.crear');
    Route::livewire('/pacientes/{paciente}/editar', 'pacientes.editar')->name('pacientes.editar');

    Route::livewire('/pacientes-casuales', 'pacientes-casuales.inicio')->name('pacientes-casuales.inicio');
    Route::livewire('/pacientes-casuales/crear', 'pacientes-casuales.crear')->name('pacientes-casuales.crear');
    Route::livewire('/pacientes-casuales/{paciente}/editar', 'pacientes-casuales.editar')->name('pacientes-casuales.editar');
    Route::livewire('/pacientes-casuales/turnos/crear', 'pacientes-casuales.turnos.crear')->name('pacientes-casuales.turnos.crear');

    Route::livewire('/pacientes-fijos', 'pacientes-fijos.inicio')->name('pacientes-fijos.inicio');
    Route::livewire('/pacientes-fijos/crear', 'pacientes-fijos.crear')->name('pacientes-fijos.crear');

    Route::livewire('/pagos/crear', 'pagos.crear')->name('pagos.crear');
    Route::livewire('/actividades-pacientes/{id}/pagos/crear', 'pagos.crear')->name('actividades-pacientes.pagos.crear');
    Route::livewire('/pagos/copagos/crear', 'pagos.copagos.crear')->name('copagos.crear');

    Route::controller(NotaTurnoController::class)->group(function () {
        Route::get('/turnos/{id}/notas', 'obtenerNotasDesdeTurno');
        Route::post('/turnos/{id}/notas', 'almacenar');
        Route::delete('/notas/{id}', 'eliminar');
    });

    Route::view('/home', 'principal')->name('inicio');
    Route::view('/turnos', 'turnos.inicio')->name('turnos.inicio');
    Route::view('/turnos/calendario', 'turnos.calendario')->name('turnos.calendario');

    Route::livewire('/egresos/crear', 'egresos.crear')->name('egresos.crear');
    Route::livewire('/movimientos', 'movimientos')->name('movimientos');
    Route::livewire('/profesionales/horas-trabajadas/crear', 'profesionales.horas-trabajadas.crear')->name('horas-trabajadas.crear');

    Route::middleware(['verificar.acceso.admin'])->group(function () {
        Route::livewire('/profesionales/crear', 'profesionales.crear')->name('profesionales.crear');
        Route::livewire('/profesionales', 'profesionales.inicio')->name('profesionales.inicio');
        Route::livewire('/profesionales/{profesional}/editar', 'profesionales.editar')->name('profesionales.editar');
        Route::livewire('/profesionales/horas-trabajadas', 'profesionales.horas-trabajadas.inicio')->name('horas-trabajadas.inicio');
        Route::livewire('/actividades-combos', 'actividades-combos.inicio')->name('actividades-combos.inicio');
        Route::controller(PrecioController::class)->group(function () {
            Route::get('/precios/crear', 'crear')->name('precios.crear');
            Route::post('/precios', 'almacenar')->name('precios.almacenar');
        });
        Route::livewire('/obras-sociales', 'obras-sociales.inicio')->name('obras-sociales.inicio');
    });
});

Route::controller(AccesoController::class)->group(function () {
    Route::get('/', 'mostrarAcceso')->name('acceso.inicio');
    Route::post('/acceso', 'verificarAcceso')->name('acceso.verificar');
    Route::get('/admin', 'mostrarAdmin')->name('admin.inicio');
    Route::post('/admin', 'verificarAdmin')->name('admin.verificar');
    Route::post('/salir', 'salir')->name('admin.salir');
});
