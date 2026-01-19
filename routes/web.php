<?php

use App\Http\Controllers\ActividadComboController;
use App\Http\Controllers\ActividadController;
use App\Http\Controllers\ActividadPacienteController;
use App\Http\Controllers\NotaTurnoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PrecioController;
use App\Http\Controllers\TurnoController;
use Illuminate\Support\Facades\Route;

Route::controller(ActividadController::class)->group(function () {
    Route::get('/actividades', 'inicio')->name('actividades.inicio');
    Route::get('/actividades/{id}/combos', 'obtenerCombos');
    Route::get('/actividades/{id}/turnos-disponibles', 'obtenerTurnosDisponibles');
});

Route::controller(ActividadComboController::class)->group(function () {
    Route::get('/actividades-combos/{id}/precio-vigente', 'obtenerPrecioVigente');
});

Route::controller(ActividadPacienteController::class)->group(function () {
    Route::get('/actividades-pacientes/general/crear', 'crearGeneral')->name('actividades-pacientes.general.crear');
    Route::get('/actividades-pacientes/kinesiologia/orden/crear', 'crearKinesiologiaConOrden')->name('actividades-pacientes.kinesiologia.con-orden.crear');
    Route::get('/actividades-pacientes/kinesiologia/sin-orden/crear', 'crearKinesiologiaSinOrden')->name('actividades-pacientes.kinesiologia.sin-orden.crear');
    Route::get('/actividades-pacientes/aplicar-orden', 'aplicarOrden')->name('actividades-pacientes.aplicar-orden');
    Route::post('/actividades-pacientes', 'almacenar')->name('actividades-pacientes.almacenar');
    Route::post('/actividades-pacientes/actualizar-orden-medica', 'actualizarOrdenMedica')->name('actividades-pacientes.actualizar-orden-medica');
});

Route::controller(PacienteController::class)->group(function () {
    Route::get('/pacientes', 'inicio')->name('pacientes.inicio');
    Route::get('/pacientes/crear', 'crear')->name('pacientes.crear');
    Route::post('/pacientes', 'almacenar')->name('pacientes.almacenar');
    Route::get('/buscar-pacientes', 'buscarPorNombre');
    Route::get('/pacientes/{id}/actividades-generales-sin-suscripcion', 'obtenerActividadesGeneralesSinSuscripcion');
});

Route::controller(PagoController::class)->group(function () {
    Route::get('/pagos/crear', 'crear')->name('pagos.crear');
    Route::post('/pagos', 'almacenar')->name('pagos.almacenar');
});

Route::controller(PrecioController::class)->group(function () {
    Route::get('/precios/crear', 'crear')->name('precios.crear');
    Route::post('/precios', 'almacenar')->name('precios.almacenar');
});

Route::controller(NotaTurnoController::class)->group(function () {
    Route::get('/turnos/{id}/notas', 'obtenerNotasDesdeTurno');
    Route::post('/turnos/{id}/notas', 'almacenar');
    Route::delete('/notas/{id}', 'eliminar');
});

Route::controller(TurnoController::class)->group(function () {
    Route::get('/', 'inicio')->name('inicio');
    Route::get('/turnos/calendario', 'calendario')->name('turnos.calendario');
    Route::post('/turnos/{id}/confirmar-asistencia', 'confirmarAsistencia')->name('turnos.confirmar-asistencia');
});
