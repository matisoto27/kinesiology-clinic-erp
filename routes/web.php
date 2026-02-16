<?php

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
use App\Http\Controllers\TurnoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['verificar.acceso'])->group(function () {
    Route::controller(ActividadController::class)->group(function () {
        Route::get('/actividades', 'inicio');
        Route::get('/actividades/{id}/combos', 'obtenerCombos');
        Route::get('/actividades/{id}/turnos-disponibles', 'obtenerTurnosDisponibles');
    });

    Route::controller(ActividadComboController::class)->group(function () {
        Route::get('/actividades-combos/{id}/precio-vigente', 'obtenerPrecioVigente');
    });

    Route::controller(ActividadPacienteController::class)->group(function () {
        Route::get('/actividades-pacientes', 'inicio')->name('actividades-pacientes.inicio');
        Route::get('/actividades-pacientes/general/crear', 'crearGeneral')->name('actividades-pacientes.general.crear');
        Route::get('/actividades-pacientes/kinesiologia/orden/crear', 'crearKinesiologiaConOrden')->name('actividades-pacientes.kinesiologia.con-orden.crear');
        Route::get('/actividades-pacientes/kinesiologia/sin-orden/crear', 'crearKinesiologiaSinOrden')->name('actividades-pacientes.kinesiologia.sin-orden.crear');
        Route::get('/actividades-pacientes/aplicar-orden', 'aplicarOrden')->name('actividades-pacientes.aplicar-orden');
        Route::post('/actividades-pacientes', 'almacenar')->name('actividades-pacientes.almacenar');
        Route::post('/actividades-pacientes/actualizar-orden-medica', 'actualizarOrdenMedica')->name('actividades-pacientes.actualizar-orden-medica');
    });

    Route::controller(ObraSocialController::class)->group(function () {
        Route::get('/buscar-obras-sociales', 'buscarPorNombre');
    });

    Route::controller(ObraSocialPacienteController::class)->group(function () {
        Route::get('/obras-sociales-pacientes/crear', 'crear')->name('obras-sociales-pacientes.crear');
        Route::post('/obras-sociales-pacientes', 'almacenar')->name('obras-sociales-pacientes.almacenar');
    });

    Route::controller(PacienteController::class)->group(function () {
        Route::get('/pacientes', 'inicio')->name('pacientes.inicio');
        Route::get('/pacientes/crear', 'crear')->name('pacientes.crear');
        Route::post('/pacientes', 'almacenar')->name('pacientes.almacenar');
        Route::get('/buscar-pacientes', 'buscarPorNombre');
        Route::get('/pacientes/{id}/actividades-generales-sin-suscripcion', 'obtenerActividadesGeneralesSinSuscripcion');
        Route::get('/pacientes/{paciente}/editar', 'editar')->name('pacientes.editar');
        Route::put('/pacientes/{paciente}', 'actualizar')->name('pacientes.actualizar');
        Route::delete('/pacientes/{paciente}', 'eliminar')->name('pacientes.eliminar');
    });

    Route::controller(PacienteFijoController::class)->group(function () {
        Route::get('/pacientes-fijos', 'inicio')->name('pacientes-fijos.inicio');
        Route::get('/pacientes-fijos/crear', 'crear')->name('pacientes-fijos.crear');
    });

    Route::controller(PagoController::class)->group(function () {
        Route::get('/pagos/crear', 'crear')->name('pagos.crear');
        Route::get('/actividades-pacientes/{id}/pagos/crear', 'crear')->name('actividades-pacientes.pagos.crear');
        Route::post('/pagos', 'almacenar')->name('pagos.almacenar');
    });

    Route::controller(PrecioController::class)->group(function () {
        Route::get('/precios/crear', 'crear')->name('precios.crear');
        Route::get('/actividades-combos/{id}/precios/crear', 'crear')->name('actividades-combos.precios.crear');
        Route::post('/precios', 'almacenar')->name('precios.almacenar');
    });

    Route::controller(NotaTurnoController::class)->group(function () {
        Route::get('/turnos/{id}/notas', 'obtenerNotasDesdeTurno');
        Route::post('/turnos/{id}/notas', 'almacenar');
        Route::delete('/notas/{id}', 'eliminar');
    });

    Route::controller(TurnoController::class)->group(function () {
        Route::get('/inicio', 'home')->name('inicio');
        Route::get('/turnos', 'inicio')->name('turnos.inicio');
        Route::get('/turnos/calendario', 'calendario')->name('turnos.calendario');
        Route::post('/turnos/{id}/confirmar-asistencia', 'confirmarAsistencia')->name('turnos.confirmar-asistencia');
    });

    Route::view('/egresos/crear', 'egresos.crear')->name('egresos.crear');
    Route::view('/movimientos', 'movimientos')->name('movimientos');
});

Route::get('/', function () {
    if (session('autorizado')) {
        return redirect()->route('inicio');
    }
    return view('acceso');
})->name('acceso.inicio');

Route::post('/acceso', function (\Illuminate\Http\Request $request) {
    $codigo = env('CODIGO_ACCESO_SISTEMA');

    if ($request->codigo === $codigo) {
        session(['autorizado' => true]);
        return redirect()->route('inicio');
    }

    return back()->withErrors(['error' => 'El código ingresado es incorrecto.']);
})->name('acceso.verificar');
