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
        $consulta = ActividadPaciente::with(['actividad', 'pacienteRegular', 'pacienteCasual', 'pagos', 'turnos'])
            ->withSum('pagos', 'monto');

        if ($this->filtroPago === 'completado') {
            $consulta->where('pago_completado', true);
        } elseif ($this->filtroPago === 'pendiente') {
            $consulta->where('pago_completado', false);
        }

        $this->inscripciones = $consulta->orderByDesc('created_at')->get();
    }

    public function verDetalles(int $id)
    {
        $this->inscripcionSeleccionada = ActividadPaciente::with(['actividad', 'pacienteRegular', 'pacienteCasual', 'turnos'])->find($id);
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
            $inscripcion = ActividadPaciente::withCount('pagos')->findOrFail($id);
            if ($inscripcion->pagos_count > 0) {
                session()->flash('error', 'No se puede eliminar la inscripción porque ya tiene pagos registrados.');
                return;
            }

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
    <h2 class="titulo-formulario">Inscripciones Mensuales / Registros de Sesiones</h2>

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

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th colspan="2">Descripción</th>
                <th>Fecha de Registro</th>
                <th>Cantidad</th>
                <th>Total a Pagar</th>
                <th>Cubierta por OS</th>
                <th>Estado Pago</th>
                <th>Deuda</th>
                <th>Ver más</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse($inscripciones as $actPac)
                @php
                    $cantidad = (int) $actPac->cant_sesiones;
                    $esGeneral = $actPac->actividad->esActividadGeneral();
                    $cubiertaOS = !$esGeneral && $actPac->fecha_emision_ord !== null;
                @endphp

                <tr class="tabla-listado__fila">
                    <td colspan="2">
                        @if ($actPac->esRegular())
                            {{ $actPac->nombre_actividad }} | {{ $actPac->ap_nom_paciente }}
                        @elseif ($actPac->esGympass())
                            <span class="badge bg-emerald-600">Paciente Gympass</span>
                            {{ $actPac->ap_nom_paciente }}
                        @else
                            <span class="badge bg-purple-600">Prueba de Pilates</span>
                            {{ $actPac->ap_nom_paciente }}
                        @endif
                    </td>
                    <td>{{ $actPac->fecha_mostrar->format('d/m/Y') }}</td>
                    <td>
                        @if ($esGeneral)
                            <span class="block font-bold">
                                {{ $cantidad }} {{ $cantidad === 1 ? 'turno' : 'turnos' }}
                            </span>
                            @if ($actPac->esRegular())
                                <small>
                                    ({{ (int) ($cantidad / 4) }} {{ (int) ($cantidad / 4) === 1 ? 'vez' : 'veces' }} por semana)
                                </small>
                            @endif
                        @else
                            <span class="block font-bold">
                                {{ $cantidad }} {{ $cantidad === 1 ? 'sesión' : 'sesiones' }}
                            </span>
                        @endif
                    </td>
                    <td>
                        @if ($actPac->esRegular() || $actPac->esPruebaPilates())
                            ${{ number_format($actPac->total_a_pagar, 2, ',', '.') }}
                        @else
                            <span class="text-gray-400 italic">N/A</span>
                        @endif
                    </td>
                    <td>
                        @if ($esGeneral)
                            <span class="text-gray-400 italic">N/A</span>
                        @else
                            {{ $cubiertaOS ? 'Si' : 'No' }}
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
                            wire:confirm="¿Estás seguro de que deseas eliminar la inscripción? Se eliminará tanto la inscripción como todos los turnos asociados a la misma.">
                            <x-iconos.basura />
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="py-10 text-center text-gray-300 italic">No hay registros disponibles.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($mostrarModal && $inscripcionSeleccionada)
        <div class="modal-informativo" wire:keydown.escape.window="cerrarModal">
            <div class="modal-informativo__ventana" wire:click.outside="cerrarModal">
                <button class="modal-informativo__cerrar" wire:click="cerrarModal">
                    <x-iconos.cruz />
                </button>

                <h2 class="modal-informativo__titulo">
                    [{{ $inscripcionSeleccionada->fecha_mostrar->format('d/m/Y') }}]
                    <div>
                        {{ $inscripcionSeleccionada->nombre_actividad }} - {{ $inscripcionSeleccionada->ap_nom_paciente }}
                    </div>
                </h2>

                <div class="space-y-3">
                    @if ($inscripcionSeleccionada->actividad->esActividadGeneral() && $inscripcionSeleccionada->esRegular())
                        <div class="modal-informativo__seccion">
                            <p class="modal-informativo__etiqueta">Autogenerado</p>
                            <p class="modal-informativo__valor">{{ $inscripcionSeleccionada->es_fijo ? 'Si' : 'No' }}</p>
                        </div>
                    @endif

                    @if (!$inscripcionSeleccionada->actividad->esActividadGeneral())
                        <div class="modal-informativo__seccion">
                            <p class="modal-informativo__etiqueta">Orden Médica</p>
                            @if(!$inscripcionSeleccionada->fecha_emision_ord)
                                <p class="modal-informativo__sin-valor">No se ha aplicado una orden médica.</p>
                            @else
                                <p class="modal-informativo__valor">
                                    Emitida el {{ $inscripcionSeleccionada->fecha_emision_ord->format('d/m/Y') }}
                                    <br>
                                    <span class="text-blue-500 text-xs font-bold uppercase">
                                        Cobertura total ({{ $inscripcionSeleccionada->cant_sesiones }} sesiones)
                                    </span>
                                </p>
                            @endif
                        </div>
                    @endif

                    <div class="modal-informativo__seccion">
                        <p class="mb-2 modal-informativo__etiqueta">Turnos asociados</p>

                        <div class="pr-2 space-y-3 max-h-60 overflow-y-auto">
                            @forelse($inscripcionSeleccionada->turnos as $turno)
                                <div class="modal-informativo__elemento-lista flex justify-between items-center">
                                    <div>
                                        @if($turno->id_turno_original)
                                            <span class="text-blue-500 text-sm font-semibold uppercase">Reprogramado</span>
                                        @endif
                                        <p class="modal-informativo__etiqueta">Turno #{{ $turno->nro_turno }}</p>
                                        <p class="modal-informativo__valor">{{ $turno->fecha_hora->format('d/m/Y H:i') }}</p>
                                    </div>
                                    @if($turno->fecha_hora->isFuture() && $turno->estado === 'Ausente')
                                        <span class="turno-pendiente">PENDIENTE</span>
                                    @else
                                        <span class="turno-pasado {{ str_contains($turno->estado, 'Ausente') ? 'bg-red-500' : 'bg-emerald-500' }}">
                                            {{ $turno->estado }}
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <p class="modal-informativo__sin-valor">No hay turnos registrados.</p>
                            @endforelse
                        </div>
                        @if($inscripcionSeleccionada->turnos->count() > 0)
                            <div class="mt-2 flex justify-center">
                                <a href="{{ route('turnos.inicio', [
                                        'actividad' => $inscripcionSeleccionada->id_actividad,
                                        'paciente' => $inscripcionSeleccionada->ap_nom_paciente
                                    ]) }}"
                                    class="text-blue-500 hover:text-blue-700 text-sm font-semibold underline transition-colors">
                                    Editar turnos
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="mt-8">
                    <button class="modal-informativo__accion bg-gray-200 hover:bg-gray-400 text-gray-700 w-full" wire:click="cerrarModal">Cerrar</button>
                </div>
            </div>
        </div>
    @endif
</div>
