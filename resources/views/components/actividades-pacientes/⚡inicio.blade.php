<?php

use App\Models\ActividadPaciente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public ?string $filtroPago = null;

    public Collection $inscripciones;

    public ?ActividadPaciente $inscripcionSeleccionada = null;

    public bool $mostrarModal = false;

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
        $consulta = ActividadPaciente::with(['actividad', 'paciente', 'pagos', 'turnos'])
            ->withSum('pagos', 'monto');

        if ($this->filtroPago === 'completado') {
            $consulta->where('pago_completado', true);
        } elseif ($this->filtroPago === 'pendiente') {
            $consulta->where('pago_completado', false);
        }

        $this->inscripciones = $consulta->orderByDesc('fecha_comienzo')->get();
    }

    public function verDetalles(int $id)
    {
        $this->inscripcionSeleccionada = ActividadPaciente::with(['actividad', 'paciente', 'turnos'])->find($id);
        $this->mostrarModal = true;
    }

    public function cerrarModal()
    {
        $this->mostrarModal = false;
        $this->inscripcionSeleccionada = null;
    }

    public function eliminar(int $id)
    {
        DB::beginTransaction();

        try {
            $inscripcion = ActividadPaciente::findOrFail($id);
            $inscripcion->delete();

            DB::commit();
            session()->flash('exito', 'La inscripción ha sido eliminada correctamente.');
            $this->cargarDatos();
        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error('[(Livewire) actividades-pacientes.inicio@eliminar] Error al eliminar la inscripción.', [
                'id' => $id,
                'excepción' => $ex->getMessage()
            ]);

            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <x-alerta tipo="error" />

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
                <th>Ver más</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($inscripciones as $actPac)
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
                            <div class="font-bold">{{ $cantidadSesiones }}</div>
                            <small>
                                ({{ (int) ($cantidadSesiones / 4) }} {{ (int) ($cantidadSesiones / 4) === 1 ? 'vez' : 'veces' }} por semana)
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
                    <td>
                        <div class="flex justify-center items-center">
                            <button type="button" wire:click="verDetalles({{ $actPac->id }})">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            </button>
                        </div>
                    </td>
                    <td>
                        <button
                            type="button"
                            class="text-white hover:text-red-400 transition-colors duration-200"
                            wire:click="eliminar({{ $actPac->id }})"
                            wire:confirm="¿Estás seguro de que deseas eliminar la inscripción? Se eliminará tanto la inscripción como todos los turnos asociados a la misma."
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="py-10 text-center text-gray-300 italic">No hay registros disponibles.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($mostrarModal && $inscripcionSeleccionada)
        <div class="modal-informativo" wire:keydown.escape.window="cerrarModal">
            <div class="modal-informativo__ventana" wire:click.outside="cerrarModal">
                <button class="modal-informativo__cerrar" wire:click="cerrarModal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>

                <h2 class="modal-informativo__titulo">
                    {{ $inscripcionSeleccionada->actividad->nombre }} -
                    {{ $inscripcionSeleccionada->paciente->nombre_completo }}
                    [{{ $inscripcionSeleccionada->fecha_comienzo->format('d/m/Y') }}]
                </h2>

                <div class="space-y-3">
                    <div class="modal-informativo__seccion">
                        <p class="modal-informativo__etiqueta">Autogenerado</p>
                        <p class="modal-informativo__valor">{{ $inscripcionSeleccionada->es_fijo ? 'Si' : 'No' }}</p>
                    </div>

                    <div class="modal-informativo__seccion">
                        <p class="modal-informativo__etiqueta">Fecha emisión orden médica</p>
                        @if($inscripcionSeleccionada->actividad->esActividadGeneral())
                            <p class="modal-informativo__sin-valor">N/A</p>
                        @elseif(!$inscripcionSeleccionada->fecha_emision_ord)
                            <p class="modal-informativo__sin-valor">No se ha aplicado una orden médica.</p>
                        @else
                            <p class="modal-informativo__valor">{{ $inscripcionSeleccionada->fecha_emision_ord->format('d/m/Y') }}</p>
                        @endif
                    </div>

                    <div class="modal-informativo__seccion">
                        <p class="mb-2 modal-informativo__etiqueta">Turnos asociados</p>

                        <div class="pr-2 space-y-3 max-h-60 overflow-y-auto">
                            @forelse($inscripcionSeleccionada->turnos as $turno)
                                <div class="modal-informativo__elemento-lista flex justify-between items-center">
                                    <div>
                                        <p class="modal-informativo__etiqueta">Turno #{{ $turno->nro_turno }}</p>
                                        <p class="modal-informativo__valor">{{ $turno->fecha_hora->format('d/m/Y H:i') }}</p>
                                    </div>
                                    @if($turno->fecha_hora->isFuture())
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded">
                                            PENDIENTE
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-bold rounded {{ $turno->asiste ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $turno->asiste ? 'PRESENTE' : 'AUSENTE' }}
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <p class="modal-informativo__sin-valor">No hay turnos registrados.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <button class="modal-informativo__accion bg-gray-100 hover:bg-gray-200 text-gray-700 w-full" wire:click="cerrarModal">Cerrar</button>
                </div>
            </div>
        </div>
    @endif

    <x-alerta tipo="exito" />
</div>
