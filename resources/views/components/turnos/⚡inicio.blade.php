<?php

use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Collection $actividades;

    #[Url(as: 'actividad')]
    public int $idActividad = 0;

    #[Url(as: 'paciente')]
    public string $consultaPaciente = '';

    public bool $mostrarModal = false;

    public ?Turno $turnoSeleccionado = null;

    #[Locked] 
    public ?Collection $turnosTotalesDisponibles = null;

    public array $fechasUnicas = [];

    public string $fechaSeleccionada = '';

    public array $horasDisponiblesParaFecha = [];

    public string $horaSeleccionada = '';

    #[Computed]
    public function turnos()
    {
        return Turno::query()
            ->with([
                'actividadPaciente.actividad',
                'actividadPaciente.pacienteRegular',
                'actividadPaciente.pacienteCasual',
                'turnoOriginal',
                'turnoRecuperacion'
            ])
            ->when($this->idActividad > 0, function($consulta) {
                $consulta->whereHas('actividadPaciente', fn($sc) => $sc->where('id_actividad', $this->idActividad));
            })
            ->when(!empty($this->consultaPaciente), function($consulta) {
                $consulta->whereHas('actividadPaciente', fn($sc) => $sc->buscarPaciente($this->consultaPaciente));
            })
            ->orderByDesc('fecha_hora')
            ->paginate(10);
    }

    #[Computed]
    public function esMismoTurno(): bool
    {
        if (!$this->turnoSeleccionado || !$this->fechaSeleccionada || !$this->horaSeleccionada) {
            return true;
        }

        $original = $this->turnoSeleccionado->fecha_hora->format('Y-m-d H:i:s');
        $nueva = Carbon::parse($this->fechaSeleccionada . ' ' . $this->horaSeleccionada)->format('Y-m-d H:i:s');

        return $original === $nueva;
    }

    public function abrirModal(int $id)
    {
        $this->turnoSeleccionado = Turno::with(['actividadPaciente.actividad', 'actividadPaciente.pacienteRegular', 'actividadPaciente.pacienteCasual'])->findOrFail($id);
        $fechaHora = $this->turnoSeleccionado->fecha_hora;

        $actividad = $this->turnoSeleccionado->actividadPaciente->actividad;
        $idPaciente = $this->turnoSeleccionado->actividadPaciente->id_paciente;
        $comienzo = $fechaHora->copy()->startOfWeek()->startOfDay();
        $fin = $comienzo->copy()->addWeek()->addDays(4)->endOfDay();

        $this->turnosTotalesDisponibles = collect($actividad->turnosDisponibles($idPaciente, $comienzo, $fin))
            ->push($fechaHora->format('Y-m-d H:i:s'))
            ->sort()
            ->values();

        $diasOcupadosInscripcion = $this->turnoSeleccionado->actividadPaciente->turnos()
            ->whereBetween('fecha_hora', [$comienzo, $fin])
            ->where('id', '!=', $this->turnoSeleccionado->id)
            ->pluck('fecha_hora')
            ->map(fn($fecha) => $fecha->format('Y-m-d'))
            ->unique();

        $this->fechasUnicas = $this->turnosTotalesDisponibles
            ->map(fn($t) => substr($t, 0, 10))
            ->unique()
            ->diff($diasOcupadosInscripcion)
            ->values()
            ->toArray();

        $this->fechaSeleccionada = $fechaHora->format('Y-m-d');
        $this->obtenerHorasParaFecha($this->fechaSeleccionada);
        $this->horaSeleccionada = $fechaHora->format('H:i:s');

        $this->mostrarModal = true;
    }

    public function updatedFechaSeleccionada($valor)
    {
        $this->obtenerHorasParaFecha($valor);
        $this->horaSeleccionada = $this->horasDisponiblesParaFecha[0];
    }

    public function obtenerHorasParaFecha($fecha)
    {
        $this->horasDisponiblesParaFecha = $this->turnosTotalesDisponibles
            ->filter(fn($t) => str_starts_with($t, $fecha))
            ->map(fn($t) => substr($t, 11, 8))
            ->sort()
            ->values()
            ->toArray();
    }

    public function actualizar()
    {
        if (str_contains($this->turnoSeleccionado->estado, 'Presente')) {
            session()->flash('error', 'No se puede editar un turno donde el paciente ya ha asistido.');
            $this->cerrarModal();
            return;
        }

        try {
            $mensaje = DB::transaction(function () {
                $nuevaFechaHora = $this->fechaSeleccionada . ' ' . $this->horaSeleccionada;

                if ($this->turnoSeleccionado->actividadPaciente->actividad->esActividadGeneral()) {
                    if ($this->turnoSeleccionado->estado !== 'Ausente avisó') {
                        $this->turnoSeleccionado->update(['estado' => 'Ausente avisó']);
                    }

                    Turno::create([
                        'id_act_pac' => $this->turnoSeleccionado->id_act_pac,
                        'nro_turno' => $this->turnoSeleccionado->nro_turno,
                        'fecha_hora' => $nuevaFechaHora,
                        'id_turno_original' => $this->turnoSeleccionado->id
                    ]);

                    return '¡El turno ha sido reprogramado con éxito!';

                } else {
                    $this->turnoSeleccionado->update(['fecha_hora' => $nuevaFechaHora]);
                    return 'La fecha del turno de Kinesiología ha sido actualizada.';
                }
            });

            $this->cerrarModal();
            session()->flash('exito', $mensaje);

        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error('[(Livewire) turnos.inicio@actualizar] Error al actualizar la fecha del turno.', ['excepción' => $ex->getMessage()]);

            $this->cerrarModal();
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function cerrarModal()
    {
        $this->reset(['mostrarModal', 'turnoSeleccionado', 'turnosTotalesDisponibles', 'fechasUnicas', 'fechaSeleccionada', 'horasDisponiblesParaFecha', 'horaSeleccionada']);
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <h2 class="titulo-formulario">Listado de Turnos</h2>

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="buscar-paciente" class="etiqueta-formulario">Buscar Paciente</label>
            <input
                id="buscar-paciente"
                type="text"
                class="entrada w-[28ch]"
                placeholder="Ingrese nombre y/o apellido"
                wire:model.live.debounce.300ms="consultaPaciente"
            >
        </div>

        <div class="columna-campo">
            <label for="filtro-actividad" class="etiqueta-formulario">Filtrar por Actividad</label>
            <select id="filtro-actividad" class="entrada" wire:model.live="idActividad">
                <option value="0">Todas las actividades</option>
                @foreach($actividades as $act)
                    <option value="{{ $act->id }}">{{ $act->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>Descripción</th>
                <th>Fecha y Hora</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse($this->turnos as $turno)
                <tr class="tabla-listado__fila">
                    <td>
                        @if ($turno->actividadPaciente->esRegular())
                            {{ $turno->actividadPaciente->nombre_actividad }} |
                            {{ $turno->ap_nom_paciente }} |
                            Turno: {{ $turno->nro_turno }} / {{ $turno->actividadPaciente->cant_sesiones }}
                        @elseif ($turno->actividadPaciente->esGympass())
                            <span class="badge-turno bg-emerald-600">Paciente Gympass</span>
                            {{ $turno->ap_nom_paciente }} |
                            Turno: {{ $turno->nro_turno }} / {{ $turno->actividadPaciente->cant_sesiones }}
                        @else
                            <span class="badge-turno bg-purple-600">Prueba de Pilates</span>
                            {{ $turno->ap_nom_paciente }}
                        @endif
                        @if($turno->esReprogramado())
                            <div class="mt-2">
                                <span class="badge-turno bg-blue-600">Turno Reprogramado</span>
                            </div>
                        @endif
                    </td>
                    <td>{{ $turno->fecha_hora->format('d/m/Y H:i') }} hs</td>
                    <td>
                        @if ($turno->fecha_hora->isFuture() && $turno->estado === 'Ausente')
                            <span class="turno-pendiente inline-flex items-center">PENDIENTE</span>
                        @else
                            <span class="turno-pasado {{ str_contains($turno->estado, 'Ausente') ? 'bg-red-500' : 'bg-emerald-500' }}">
                                {{ $turno->estado }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="centrado-total">
                            @if($turno->puedeSerReprogramado())
                                <button type="button" wire:click="abrirModal({{ $turno->id }})">
                                    <x-iconos.lapiz />
                                </button>
                            @else
                                <span class="text-gray-500 cursor-not-allowed opacity-50">
                                    <x-iconos.lapiz />
                                </span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-10 text-center text-gray-300 italic">No se encontraron turnos.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $this->turnos->links(data: ['scrollTo' => false]) }}
    </div>

    @if($mostrarModal && $turnoSeleccionado)
        <div class="modal-informativo" wire:keydown.escape.window="cerrarModal">
            <div class="modal-informativo__ventana" wire:click.outside="cerrarModal">
                <button class="modal-informativo__cerrar" wire:click="cerrarModal">
                    <x-iconos.cruz />
                </button>

                <h2 class="modal-informativo__titulo text-center">Reasignar Turno</h2>

                <div class="mb-6">
                    <p class="text-emerald-400 text-lg font-semibold">{{ $turnoSeleccionado->ap_nom_paciente }}</p>
                    <p class="text-gray-400 text-base">{{ $turnoSeleccionado->actividadPaciente->nombre_actividad }}</p>
                </div>

                <div class="mb-8 space-y-3">
                    <div class="modal-informativo__seccion">
                        <label class="modal-informativo__etiqueta mb-1 block">Día de la semana</label>
                        <select class="entrada w-full" wire:model.live="fechaSeleccionada">
                            @foreach($fechasUnicas as $fecha)
                                <option value="{{ $fecha }}">
                                    {{ Carbon::parse($fecha)->translatedFormat('l d/m/Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="modal-informativo__seccion">
                        <label class="modal-informativo__etiqueta mb-1 block">Horario disponible</label>
                        <select class="entrada w-full" wire:model.live="horaSeleccionada">
                            @forelse($horasDisponiblesParaFecha as $hora)
                                <option value="{{ $hora }}">
                                    {{ Carbon::parse($hora)->format('H:i') }} hs
                                </option>
                            @empty
                                <option value="">No hay horarios disponibles</option>
                            @endforelse
                        </select>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button class="modal-informativo__accion flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 transition-all" wire:click="cerrarModal">
                        Cancelar
                    </button>
                    <button
                        class="modal-informativo__accion flex-1 transition-all {{ $this->esMismoTurno ? 'bg-gray-400 cursor-not-allowed opacity-50' : 'bg-emerald-600 hover:bg-emerald-700 text-white' }}"
                        wire:click="actualizar"
                        wire:loading.attr="disabled"
                        @disabled($this->esMismoTurno)
                    >
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
