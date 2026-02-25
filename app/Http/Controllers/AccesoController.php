<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccesoController extends Controller
{
    public function mostrarAcceso()
    {
        if (session('autorizado')) return redirect()->route('inicio');
        return view('acceso');
    }

    public function verificarAcceso(Request $request)
    {
        if ($request->codigo === config('app.codigo_acceso')) {
            session(['autorizado' => true]);
            return redirect()->route('inicio');
        }
        return back()->withErrors(['error' => 'Código de acceso incorrecto.']);
    }

    public function mostrarAdmin()
    {
        if (session('acceso_admin')) return redirect()->route('inicio');
        return view('acceso-admin');
    }

    public function verificarAdmin(Request $request)
    {
        if ($request->codigo === config('app.codigo_admin')) {
            session([
                'acceso_admin' => true,
                'timestamp_ingreso' => now()->timestamp
            ]);
            return redirect()->route('inicio');
        }
        return back()->withErrors(['error' => 'Código de administrador incorrecto.']);
    }

    public function salir()
    {
        session()->forget('acceso_admin');
        return redirect()->route('acceso.inicio');
    }
}
