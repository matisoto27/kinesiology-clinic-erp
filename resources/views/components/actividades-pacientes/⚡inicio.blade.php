<?php

use App\Models\ActividadPaciente;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public ?string $filtroPago = null;

    public Collection $actividadesPacientes;

    public function mount()
    {
        $this->cargarDatos();
    }

    public function updatedFiltroPago()
    {
        $this->cargarDatos();
    }

    protected function cargarDatos()
    {
        $consulta = ActividadPaciente::with(['actividad', 'paciente', 'pagos'])
            ->withSum('pagos', 'monto');

        if ($this->filtroPago === 'completado') {
            $consulta->where('pago_completado', true);
        } elseif ($this->filtroPago === 'pendiente') {
            $consulta->where('pago_completado', false);
        }

        $this->actividadesPacientes = $consulta->orderByDesc('fecha_comienzo')->get();
    }

};
?>

<div class="contenedor-listado max-w-screen-3xl">
    @if (session()->has('error'))
        <div class="alerta-error">
            <span class="font-bold">¡Error!</span>
            {{ session('error') }}
        </div>
    @endif

    <h2 class="titulo-formulario">Listado de inscripciones</h2>

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="filtro-pago" class="etiqueta-formulario">Filtro de pago</label>
            <select id="filtro-pago" class="entrada" wire:model.live="filtroPago">
                <option value="" selected>Todas</option>
                <option value="completado">Pago Completado</option>
                <option value="pendiente">Pago Pendiente</option>
            </select>
        </div>
    </div>

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>Paciente</th>
                <th>Actividad</th>
                <th>Fecha Comienzo</th>
                <th>Cantidad de Turnos</th>
                <th>Total a Pagar</th>
                <th>Sesiones Cubiertas OS</th>
                <th>Nuevo Total a Pagar</th>
                <th>Estado Pago</th>
                <th>Deuda</th>
            </tr>
        </thead>
        <tbody>
            @forelse($actividadesPacientes as $actPac)
                @php
                    $cantidadSesiones = (int) $actPac->cant_sesiones;
                    $esGeneral = $actPac->actividad->esActividadGeneral();
                    $sesionesCubiertas = (int) ($actPac->sesiones_cubiertas ?? 0);
                    $diferencia = $sesionesCubiertas - $cantidadSesiones;
                @endphp

                <tr class="tabla-listado__fila">
                    <td>{{ $actPac->paciente->nombre_completo }}</td>
                    <td>{{ $actPac->actividad->nombre }}</td>
                    <td>{{ $actPac->fecha_comienzo->format('d/m/Y') }}</td>
                    <td>
                        @if ($esGeneral)
                            <div class="font-bold">{{ $cantidadSesiones * 4 }}</div>
                            <small>
                                ({{ $cantidadSesiones }} {{ $cantidadSesiones === 1 ? 'vez' : 'veces' }} por semana)
                            </small>
                        @else
                            {{ $cantidadSesiones }}
                        @endif
                    </td>
                    <td>
                        ${{ number_format($actPac->total_a_pagar, 2, ',', '.') }}
                    </td>
                    <td>
                        @if ($esGeneral)
                            <span class="text-gray-400 italic">N/A</span>
                        @else
                            @if ($diferencia > 0)
                                <div class="font-bold">{{ $sesionesCubiertas }}</div>
                                <small>
                                    ({{ $diferencia }} a favor)
                                </small>
                            @else
                                {{ $sesionesCubiertas }}
                            @endif
                        @endif
                    </td>
                    <td>
                        @if($actPac->total_a_pagar != $actPac->nuevo_total_a_pagar)
                            ${{ number_format($actPac->nuevo_total_a_pagar, 2, ',', '.') }}
                        @else
                            <span class="text-gray-400 italic">N/A</span>
                        @endif
                    </td>
                    <td>
                        @if($actPac->pago_completado)
                            <span class="px-3 py-1 inline-flex items-center bg-emerald-500 rounded text-sm font-semibold">
                                Completado
                            </span>
                        @else
                            <span class="px-3 py-1 inline-flex items-center bg-amber-500 rounded text-sm font-semibold">
                                Pendiente
                            </span>
                        @endif
                    </td>
                    <td>
                        @if($actPac->deuda > 0)
                            <span class="px-3 py-1 inline-flex items-center bg-red-500 rounded text-sm font-semibold">
                                ${{ number_format($actPac->deuda, 2, ',', '.') }}
                            </span>
                        @else
                            <span class="text-gray-400 italic">Saldada</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="py-10 text-center text-gray-300 italic">No hay registros disponibles.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
