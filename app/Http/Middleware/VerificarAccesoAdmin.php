<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarAccesoAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tiempoMaximo = 5 * 60; // 5 minutos (expresado en segundos)

        if (!session('acceso_admin')) {
            return redirect()->route('admin.inicio')->withErrors(['error' => 'Requiere permisos de administrador.']);
        }

        $ingreso = session('timestamp_ingreso');
        $tiempoTranscurrido = now()->timestamp - $ingreso;

        if ($tiempoTranscurrido > $tiempoMaximo) {
            session()->forget(['acceso_admin', 'timestamp_ingreso']);
            return redirect()->route('admin.inicio')->withErrors(['error' => 'La sesión de administrador ha expirado (máximo 5 min).']);
        }

        return $next($request);
    }
}
