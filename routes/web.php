<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\ActividadComboController;
use App\Http\Controllers\ActividadController;
use App\Http\Controllers\ActividadPacienteController;
use App\Http\Controllers\NotaTurnoController;
use App\Http\Controllers\ObraSocialController;
use App\Http\Controllers\ObraSocialPacienteController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PacienteFijoController;
use App\Http\Controllers\PagoController;
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
        Route::get('/actividades-pacientes', 'inicio')->name('actividades-pacientes.inicio');
        Route::get('/actividades-pacientes/general/crear', 'crearGeneral')->name('actividades-pacientes.general.crear');
        Route::get('/actividades-pacientes/kinesiologia/orden/crear', 'crearKinesiologiaConOrden')->name('actividades-pacientes.kinesiologia.con-orden.crear');
        Route::get('/actividades-pacientes/kinesiologia/sin-orden/crear', 'crearKinesiologiaSinOrden')->name('actividades-pacientes.kinesiologia.sin-orden.crear');
        Route::post('/actividades-pacientes', 'almacenar')->name('actividades-pacientes.almacenar');
        Route::post('/actividades-pacientes/actualizar-orden-medica', 'actualizarOrdenMedica')->name('actividades-pacientes.actualizar-orden-medica');
    });
    Route::view('/actividades-pacientes/aplicar-orden', 'actividades-pacientes.aplicar-orden')->name('actividades-pacientes.aplicar-orden');

    Route::controller(ObraSocialController::class)->group(function () {
        Route::get('/buscar-obras-sociales', 'buscarPorNombre');
    });

    Route::controller(ObraSocialPacienteController::class)->group(function () {
        Route::get('/obras-sociales-pacientes/crear', 'crear')->name('obras-sociales-pacientes.crear');
        Route::post('/obras-sociales-pacientes', 'almacenar')->name('obras-sociales-pacientes.almacenar');
    });

    Route::controller(PacienteController::class)->group(function () {
        Route::get('/pacientes', 'inicio')->name('pacientes.inicio');
        Route::get('/buscar-pacientes', 'buscarPorNombre');
        Route::get('/pacientes/{id}/actividades-generales-sin-suscripcion', 'obtenerActividadesGeneralesSinSuscripcion');
        Route::get('/pacientes/{paciente}/editar', 'editar')->name('pacientes.editar');
        Route::delete('/pacientes/{paciente}', 'eliminar')->name('pacientes.eliminar');
    });
    Route::view('/pacientes/crear', 'pacientes.crear')->name('pacientes.crear');

    Route::controller(PacienteFijoController::class)->group(function () {
        Route::get('/pacientes-fijos', 'inicio')->name('pacientes-fijos.inicio');
        Route::get('/pacientes-fijos/crear', 'crear')->name('pacientes-fijos.crear');
    });

    Route::controller(PagoController::class)->group(function () {
        Route::get('/pagos/crear', 'crear')->name('pagos.crear');
        Route::get('/actividades-pacientes/{id}/pagos/crear', 'crear')->name('actividades-pacientes.pagos.crear');
        Route::post('/pagos', 'almacenar')->name('pagos.almacenar');
    });

    Route::controller(NotaTurnoController::class)->group(function () {
        Route::get('/turnos/{id}/notas', 'obtenerNotasDesdeTurno');
        Route::post('/turnos/{id}/notas', 'almacenar');
        Route::delete('/notas/{id}', 'eliminar');
    });

    Route::view('/home', 'principal')->name('inicio');
    Route::view('/turnos', 'turnos.inicio')->name('turnos.inicio');
    Route::view('/turnos/calendario', 'turnos.calendario')->name('turnos.calendario');

    Route::view('/egresos/crear', 'egresos.crear')->name('egresos.crear');
    Route::view('/movimientos', 'movimientos')->name('movimientos');
    Route::view('/profesionales/horas-trabajadas/crear', 'profesionales.horas-trabajadas.crear')->name('horas-trabajadas.crear');

    Route::middleware(['verificar.acceso.admin'])->group(function () {
        Route::view('/profesionales/crear', 'profesionales.crear')->name('profesionales.crear');
        Route::view('/profesionales', 'profesionales.inicio')->name('profesionales.inicio');
        Route::view('/profesionales/{profesional}/editar', 'profesionales.editar')->name('profesionales.editar');
        Route::view('/profesionales/horas-trabajadas', 'profesionales.horas-trabajadas.inicio')->name('horas-trabajadas.inicio');
        Route::view('/actividades-combos', 'actividades-combos.inicio')->name('actividades-combos.inicio');
        Route::controller(PrecioController::class)->group(function () {
            Route::get('/precios/crear', 'crear')->name('precios.crear');
            Route::post('/precios', 'almacenar')->name('precios.almacenar');
        });
        Route::view('/obras-sociales', 'obras-sociales.inicio')->name('obras-sociales.inicio');
    });
});

Route::controller(AccesoController::class)->group(function () {
    Route::get('/', 'mostrarAcceso')->name('acceso.inicio');
    Route::post('/acceso', 'verificarAcceso')->name('acceso.verificar');
    Route::get('/admin', 'mostrarAdmin')->name('admin.inicio');
    Route::post('/admin', 'verificarAdmin')->name('admin.verificar');
    Route::post('/salir', 'salir')->name('admin.salir');
});
