<?php

use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    protected $queryString = [
        'filtroActividad' => ['as' => 'idActividad', 'except' => null],
        'consultaPaciente' => ['as' => 'nombreApellidoPac', 'except' => '']
    ];

    public Collection $actividades;

    public ?int $filtroActividad = null;

    public string $consultaPaciente = '';

    public bool $mostrarModal = false;

    public ?Turno $turnoSeleccionado = null;

    public array $turnosTotalesDisponibles = [];

    public array $fechasUnicas = [];

    public string $fechaSeleccionada = '';

    public array $horasDisponiblesParaFecha = [];

    public string $horaSeleccionada = '';

    public function updatedConsultaPaciente()
    {
        $this->resetPage();
    }

    public function updatedFiltroActividad()
    {
        $this->resetPage();
    }

    public function abrirModal(int $id)
    {
        $this->turnoSeleccionado = Turno::with(['actividadPaciente.actividad', 'actividadPaciente.paciente'])->find($id);
        $fechaHora = $this->turnoSeleccionado->fecha_hora;

        $actividad = $this->turnoSeleccionado->actividadPaciente->actividad;
        $idPaciente = $this->turnoSeleccionado->actividadPaciente->id_paciente;
        $comienzo = $fechaHora->copy()->startOfWeek()->startOfDay();
        $fin = $comienzo->copy()->addDays(4)->endOfDay();

        $this->turnosTotalesDisponibles = $actividad->turnosDisponibles($idPaciente, $comienzo, $fin);
        $this->turnosTotalesDisponibles[] = $fechaHora->format('Y-m-d H:i:s');

        $diasOcupadosInscripcion = $this->turnoSeleccionado->actividadPaciente->turnos()
            ->whereBetween('fecha_hora', [$comienzo, $fin])
            ->where('id', '!=', $this->turnoSeleccionado->id)
            ->pluck('fecha_hora')
            ->map(fn($fecha) => $fecha->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();

        $this->fechasUnicas = collect($this->turnosTotalesDisponibles)
            ->map(fn($t) => Carbon::parse($t)->format('Y-m-d'))
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
        $this->horaSeleccionada = $this->horasDisponiblesParaFecha[0] ?? '';
    }

    public function obtenerHorasParaFecha($fecha)
    {
        $this->horasDisponiblesParaFecha = collect($this->turnosTotalesDisponibles)
            ->filter(fn($t) => str_starts_with($t, $fecha))
            ->map(fn($t) => Carbon::parse($t)->format('H:i:s'))
            ->sort()
            ->values()
            ->toArray();
    }

    public function actualizar()
    {
        if (!$this->fechaSeleccionada || !$this->horaSeleccionada) return;

        DB::beginTransaction();

        try {
            $nuevaFechaHora = $this->fechaSeleccionada . ' ' . $this->horaSeleccionada;
            $this->turnoSeleccionado->update(['fecha_hora' => $nuevaFechaHora]);
            DB::commit();

            $this->cerrarModal();
            session()->flash('exito', '¡El turno ha sido reasignado con éxito!');

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

    public function render()
    {
        $consulta = Turno::with(['actividadPaciente.actividad', 'actividadPaciente.paciente']);

        if ($this->filtroActividad) {
            $consulta->whereHas('actividadPaciente', fn ($c) => $c->where('id_actividad', $this->filtroActividad));
        }

        if ($this->consultaPaciente !== '') {
            $consulta->whereHas('actividadPaciente.paciente', function ($consulta) {
                $consulta->where(DB::raw("CONCAT(nombre, ' ', apellido)"), 'like', '%' . $this->consultaPaciente . '%');
            });
        }

        return $this->view(['turnos' => $consulta->orderByDesc('fecha_hora')->paginate(8)]);
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
                placeholder="Ingrese nombre + apellido"
                wire:model.live.debounce.300ms="consultaPaciente"
            >
        </div>

        <div class="columna-campo">
            <label for="filtro-actividad" class="etiqueta-formulario">Filtrar por Actividad</label>
            <select id="filtro-actividad" class="entrada" wire:model.live="filtroActividad">
                <option value="">Todas las actividades</option>
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
                <th># Turno</th>
                <th>Paciente</th>
                <th>Actividad</th>
                <th>Fecha y Hora</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse($turnos as $turno)
                <tr class="tabla-listado__fila">
                    <td class="text-gray-400 font-bold">#ID{{ $turno->id_act_pac }} - NRO{{ $turno->nro_turno }}</td>
                    <td>{{ $turno->actividadPaciente->paciente->nombre_completo }}</td>
                    <td>{{ $turno->actividadPaciente->actividad->nombre }}</td>
                    <td>{{ $turno->fecha_hora->format('d/m/Y H:i') }} hs</td>
                    <td>
                        @if($turno->fecha_hora->isFuture())
                            <span class="turno-pendiente inline-flex items-center">PENDIENTE</span>
                        @else
                            <span class="turno-pasado {{ $turno->asiste ? 'bg-emerald-500' : 'bg-red-500' }}">
                                {{ $turno->asiste ? 'PRESENTE' : 'AUSENTE' }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="centrado-total">
                            <button type="button" wire:click="abrirModal({{ $turno->id }})">
                                <x-iconos.lapiz />
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-10 text-center text-gray-300 italic">No se encontraron turnos.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $turnos->links() }}
    </div>

    @if($mostrarModal && $turnoSeleccionado)
        <div class="modal-informativo" wire:keydown.escape.window="cerrarModal">
            <div class="modal-informativo__ventana" wire:click.outside="cerrarModal">
                <button class="modal-informativo__cerrar" wire:click="cerrarModal">
                    <x-iconos.cruz />
                </button>

                <h2 class="modal-informativo__titulo text-center">Reasignar Turno</h2>

                <div class="mb-6">
                    <p class="text-emerald-400 text-lg font-semibold">{{ $turnoSeleccionado->actividadPaciente->paciente->nombre_completo }}</p>
                    <p class="text-gray-400 text-base">{{ $turnoSeleccionado->actividadPaciente->actividad->nombre }}</p>
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
                        <select class="entrada w-full" wire:model="horaSeleccionada">
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
                    <button class="modal-informativo__accion flex-1 bg-emerald-600 hover:bg-emerald-700 text-white transition-all" wire:click="actualizar" wire:loading.attr="disabled">
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
