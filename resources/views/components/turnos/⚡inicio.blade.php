<?php

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividad;
use App\Models\Turno;
use App\Services\TurnoService;
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

    public bool $ocultarTurnosFuturos = true;

    public bool $mostrarModal = false;

    public ?Turno $turnoSeleccionado = null;

    #[Locked] 
    public ?Collection $turnosTotalesDisponibles = null;

    public array $fechasUnicas = [];

    public string $fechaSeleccionada = '';

    public array $horasDisponiblesParaFecha = [];

    public string $horaSeleccionada = '';

    public ?string $modoKinesio = null;

    public bool $avisoRecienMarcado = false;

    public int $idActividadDestino = 0;

    public function updatingConsultaPaciente(): void
    {
        $this->resetPage();
    }

    public function updatingIdActividad(): void
    {
        $this->resetPage();
    }

    public function updatingOcultarTurnosFuturos(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function turnos()
    {
        return Turno::query()
            ->conActPac()
            ->select([
                'turnos.id',
                'turnos.id_act_pac',
                'turnos.fecha_hora',
                'turnos.estado',
                'turnos.id_turno_original',
            ])
            ->with([
                'actividadPaciente:id,id_actividad,id_paciente,id_paciente_casual,cant_sesiones,id_act_pac_dual',
                'actividadPaciente.actPacDual:id,cant_sesiones',
                'actividadPaciente.actividad:id,nombre,id_tipo_actividad',
                'actividadPaciente.pacienteRegular:id,nombre,apellido',
                'actividadPaciente.pacienteCasual:id,nombre,apellido',
                'turnoOriginal:id,fecha_hora',
                'turnoRecuperacion:id,id_turno_original',
            ])
            ->when($this->idActividad > 0, fn ($q) => $q->deLaActividad($this->idActividad))
            ->when($this->consultaPaciente !== '', fn ($q) => $q->buscarPaciente($this->consultaPaciente))
            ->when($this->ocultarTurnosFuturos, fn ($q) => $q
                ->where('turnos.fecha_hora', '<', now()->startOfDay()->addWeek()))
            ->orderByDesc('turnos.fecha_hora')
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
        $idActividadOrigen = (int) $this->turnoSeleccionado->actividadPaciente->id_actividad;

        return $original === $nueva && (int) $this->idActividadDestino === $idActividadOrigen;
    }

    #[Computed]
    public function esKinesio(): bool
    {
        if (!$this->turnoSeleccionado) {
            return false;
        }

        return !$this->turnoSeleccionado->actividadPaciente->actividad->esActividadGeneral();
    }

    #[Computed]
    public function puedeCruzarActividad(): bool
    {
        $inscripcion = $this->turnoSeleccionado?->actividadPaciente;

        if (!$inscripcion?->actividad?->esActividadGeneral()) {
            return false;
        }

        return $inscripcion->esDualOperativo();
    }

    public function abrirModal(int $id)
    {
        $this->turnoSeleccionado = Turno::with([
            'actividadPaciente.actividad',
            'actividadPaciente.actPacDual.actividad',
            'actividadPaciente.pacienteRegular',
            'actividadPaciente.pacienteCasual',
        ])->findOrFail($id);

        $actividad = $this->turnoSeleccionado->actividadPaciente->actividad;
        $this->idActividadDestino = (int) $actividad->id;
        $this->cargarDisponibilidad($this->idActividadDestino);

        $this->avisoRecienMarcado = false;

        if ($actividad->esActividadGeneral()) {
            $this->modoKinesio = null;
        } elseif ($this->turnoSeleccionado->esReprogramado()) {
            $this->modoKinesio = 'corregir';
        } elseif ($this->turnoSeleccionado->esAusenteAviso()) {
            $this->modoKinesio = 'aviso';
        } else {
            $this->modoKinesio = null;
        }

        $this->mostrarModal = true;
    }

    public function marcarAusenteAviso(): void
    {
        if (!$this->turnoSeleccionado) {
            return;
        }

        try {
            DB::transaction(function () {
                $turno = Turno::lockForUpdate()->findOrFail($this->turnoSeleccionado->id);
                $turno->load(['actividadPaciente.actividad', 'turnoRecuperacion']);

                if (str_contains($turno->estado, 'Presente')) {
                    throw new \Exception("No puede marcarse como 'Ausente avisó' un turno cuya asistencia ya fue confirmada.");
                }

                if ($turno->esReprogramado() || $turno->turnoRecuperacion) {
                    throw new \Exception('Este turno ya fue reprogramado.');
                }

                if (!$turno->esAusenteAviso()) {
                    $turno->update(['estado' => 'Ausente avisó']);
                }

                $this->turnoSeleccionado = $turno->fresh([
                    'actividadPaciente.actividad',
                    'actividadPaciente.actPacDual.actividad',
                    'actividadPaciente.pacienteRegular',
                    'actividadPaciente.pacienteCasual',
                ]);
            });

            $this->avisoRecienMarcado = true;
            $this->cargarDisponibilidad($this->idActividadDestino);

            if ($this->esKinesio) {
                $this->modoKinesio = 'aviso';
            }
        } catch (\Throwable $th) {
            Log::error('[(Livewire) turnos.inicio@marcarAusenteAviso] Error al marcar ausente avisó.', [
                'id' => $this->turnoSeleccionado?->id,
                'excepción' => $th->getMessage(),
            ]);

            $this->cerrarModal();
            session()->flash('error', $th instanceof \Exception
                ? $th->getMessage()
                : 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function updatedFechaSeleccionada($valor)
    {
        $this->obtenerHorasParaFecha($valor);
        $this->horaSeleccionada = $this->horasDisponiblesParaFecha[0] ?? '';
    }

    public function updatedIdActividadDestino(): void
    {
        if (!$this->turnoSeleccionado) {
            return;
        }

        $this->cargarDisponibilidad((int) $this->idActividadDestino);
    }

    public function obtenerHorasParaFecha($fecha)
    {
        $this->horasDisponiblesParaFecha = collect($this->turnosTotalesDisponibles)
            ->filter(fn($t) => $fecha !== '' && str_starts_with($t, $fecha))
            ->map(fn($t) => substr($t, 11, 8))
            ->sort()
            ->values()
            ->toArray();
    }

    private function cargarDisponibilidad(int $idActividad): void
    {
        $turno = $this->turnoSeleccionado;
        $inscripcion = $turno->actividadPaciente;
        $fechaHora = $turno->fecha_hora;
        $actividad = Actividad::findOrFail($idActividad);
        $idPaciente = $inscripcion->id_paciente;
        $comienzo = $fechaHora->copy()->startOfWeek()->subWeek()->startOfDay();
        $fin = $fechaHora->copy()->startOfWeek()->addWeek()->addDays(4)->endOfDay();

        $slots = collect($actividad->turnosDisponibles($idPaciente, $comienzo, $fin));

        if ((int) $inscripcion->id_actividad === $idActividad) {
            $slots->push($fechaHora->format('Y-m-d H:i:s'));
        }

        $this->turnosTotalesDisponibles = $slots->unique()->sort()->values();

        $inscripcionCupo = (int) $inscripcion->id_actividad === $idActividad
            ? $inscripcion
            : $inscripcion->actPacDual;

        $diasOcupados = collect();

        if ($inscripcionCupo) {
            $diasOcupados = $inscripcionCupo->turnos()
                ->whereDoesntHave('turnoRecuperacion')
                ->where('estado', '!=', 'Ausente avisó')
                ->whereBetween('fecha_hora', [$comienzo, $fin])
                ->when(
                    (int) $inscripcionCupo->id === (int) $turno->id_act_pac,
                    fn ($q) => $q->where('id', '!=', $turno->id)
                )
                ->pluck('fecha_hora')
                ->map(fn ($fecha) => $fecha->format('Y-m-d'))
                ->unique();
        }

        $this->fechasUnicas = $this->turnosTotalesDisponibles
            ->map(fn ($t) => substr($t, 0, 10))
            ->unique()
            ->diff($diasOcupados)
            ->values()
            ->toArray();

        $fechaActual = $fechaHora->format('Y-m-d');
        $this->fechaSeleccionada = in_array($fechaActual, $this->fechasUnicas, true)
            ? $fechaActual
            : ($this->fechasUnicas[0] ?? '');

        $this->obtenerHorasParaFecha($this->fechaSeleccionada);

        $horaActual = $fechaHora->format('H:i:s');
        $this->horaSeleccionada = in_array($horaActual, $this->horasDisponiblesParaFecha, true)
            ? $horaActual
            : ($this->horasDisponiblesParaFecha[0] ?? '');
    }

    public function elegirModificacionSimple(): void
    {
        if (!$this->esKinesio) {
            return;
        }

        $this->modoKinesio = 'corregir';
    }

    public function volverChooserKinesio(): void
    {
        if (!$this->esKinesio || $this->turnoSeleccionado?->esAusenteAviso() || $this->turnoSeleccionado?->esReprogramado()) {
            return;
        }

        $this->modoKinesio = null;
    }

    public function actualizar(TurnoService $turnoService)
    {
        try {
            $esGeneral = $this->turnoSeleccionado->actividadPaciente->actividad->esActividadGeneral();

            if ($this->fechaSeleccionada === '' || $this->horaSeleccionada === '') {
                throw new ReglaNegocioException('Debe seleccionar un horario disponible.');
            }

            $nuevaFechaHora = Carbon::parse($this->fechaSeleccionada . ' ' . $this->horaSeleccionada);

            if (!$esGeneral && $this->modoKinesio === 'corregir') {
                $turnoService->corregirFecha($this->turnoSeleccionado, $nuevaFechaHora);

                $this->cerrarModal();
                session()->flash('exito', 'La fecha del turno de Kinesiología ha sido corregida.');

                return;
            }

            if ($esGeneral && $this->turnoSeleccionado->esReprogramado()) {
                $turnoService->moverReprogramado(
                    $this->turnoSeleccionado,
                    $nuevaFechaHora,
                    $this->idActividadDestino
                );

                $this->cerrarModal();
                session()->flash('exito', 'El turno reprogramado fue actualizado.');

                return;
            }

            if (!$this->turnoSeleccionado->esAusenteAviso()) {
                throw new ReglaNegocioException("Primero debe marcarse el turno como 'Ausente avisó'.");
            }

            $turnoService->reprogramar($this->turnoSeleccionado, $nuevaFechaHora, (int) $this->idActividadDestino);

            $this->cerrarModal();
            session()->flash('exito', $esGeneral
                ? '¡El turno ha sido reprogramado con éxito!'
                : 'La fecha del turno de Kinesiología ha sido actualizada.');

        } catch (ReglaNegocioException $ex) {
            $this->cerrarModal();
            session()->flash('error', $ex->getMessage());
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) turnos.inicio@actualizar] Error al actualizar la fecha del turno.', ['excepción' => $ex->getMessage()]);

            $this->cerrarModal();
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function cerrarModal()
    {
        $this->reset([
            'mostrarModal',
            'turnoSeleccionado',
            'turnosTotalesDisponibles',
            'fechasUnicas',
            'fechaSeleccionada',
            'horasDisponiblesParaFecha',
            'horaSeleccionada',
            'modoKinesio',
            'avisoRecienMarcado',
            'idActividadDestino',
        ]);
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

        <div class="flex items-center gap-1 self-end pb-2">
            <input
                id="ocultar-turnos-futuros"
                type="checkbox"
                class="checkbox-formulario"
                wire:model.live="ocultarTurnosFuturos"
            >
            <label for="ocultar-turnos-futuros" class="etiqueta-formulario">
                Ocultar turnos futuros
            </label>
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
                            Turno: {{ $turno->nro_turno }} / {{ $turno->actividadPaciente->cantSesionesGrupo() }}
                        @elseif ($turno->actividadPaciente->esGympass())
                            <span class="badge bg-emerald-600">Paciente Gympass</span>
                            {{ $turno->ap_nom_paciente }} |
                            Turno: {{ $turno->nro_turno }} / {{ $turno->actividadPaciente->cantSesionesGrupo() }}
                        @else
                            <span class="badge bg-purple-600">Prueba de Pilates</span>
                            {{ $turno->ap_nom_paciente }}
                        @endif
                        @if($turno->esReprogramado())
                            <div class="mt-2">
                                <span class="badge bg-blue-600">Turno Reprogramado</span>
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
                            @if($turno->puedeSerModificado())
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
        @php
            $esChooserKinesio = $this->esKinesio && $modoKinesio === null;
            $esSoloAvisoGeneral = !$this->esKinesio
                && !$turnoSeleccionado->esAusenteAviso()
                && !$turnoSeleccionado->esReprogramado();
            $mostrarFechas = !$esChooserKinesio && !$esSoloAvisoGeneral;
        @endphp
        <div class="modal-informativo" wire:keydown.escape.window="cerrarModal">
            <div class="modal-informativo__ventana" wire:click.outside="cerrarModal">
                <button class="modal-informativo__cerrar" wire:click="cerrarModal">
                    <x-iconos.cruz />
                </button>

                <h2 class="modal-informativo__titulo text-center">
                    @if ($this->esKinesio && $modoKinesio === 'corregir')
                        Corregir fecha
                    @elseif ($turnoSeleccionado->esReprogramado())
                        Mover turno reprogramado
                    @elseif ($turnoSeleccionado->esAusenteAviso())
                        Asignar nueva fecha
                    @else
                        Reasignar Turno
                    @endif
                </h2>

                <div class="mb-6 flex items-center justify-between">
                    <div class="flex flex-col">
                        <p class="text-emerald-400 text-lg font-semibold">{{ $turnoSeleccionado->ap_nom_paciente }}</p>
                        <p class="text-gray-400">{{ $turnoSeleccionado->actividadPaciente->nombre_actividad }}</p>
                    </div>

                    @if ($turnoSeleccionado->esAusenteAviso())
                        <span class="px-4 py-2 bg-red-600 text-white font-semibold rounded-md cursor-not-allowed">
                            Ausente avisó (AA)
                        </span>
                    @endif
                </div>

                @if ($esChooserKinesio)
                    <div class="mb-8 flex flex-col gap-6">
                        <div class="flex flex-col items-center">
                            <button
                                type="button"
                                class="w-full rounded-md bg-slate-600 px-4 py-3 text-center text-lg font-semibold text-white transition-all duration-100 hover:bg-slate-700 active:scale-95"
                                wire:click="elegirModificacionSimple"
                                wire:loading.attr="disabled">
                                Modificación simple
                            </button>
                            <p class="mt-2 text-center text-sm text-gray-400">
                                Para corregir fechas mal agendadas
                            </p>
                        </div>

                        <div class="flex flex-col items-center">
                            <button
                                type="button"
                                class="w-full rounded-md bg-orange-400 px-4 py-3 text-center text-lg font-semibold text-white transition-all duration-100 hover:bg-red-600 active:scale-95"
                                wire:click="marcarAusenteAviso"
                                wire:confirm="¿Estás seguro de que deseas marcar este turno como 'Ausente avisó'? Se liberará el cupo de esta fecha."
                                wire:loading.attr="disabled">
                                Avisó que no viene
                            </button>
                            <p class="mt-2 text-center text-sm text-gray-400">
                                El paciente acaba de notificar que no va a poder asistir a este turno y desea cambiar la fecha
                            </p>
                        </div>
                    </div>

                    <button class="modal-informativo__accion w-full bg-gray-100 text-gray-700 transition-all hover:bg-gray-200" wire:click="cerrarModal">
                        Cancelar
                    </button>
                @elseif ($esSoloAvisoGeneral)
                    <div class="mb-8 flex flex-col items-center">
                        <button
                            type="button"
                            class="w-full rounded-md bg-orange-400 px-4 py-3 text-center text-lg font-semibold text-white transition-all duration-100 hover:bg-red-600 active:scale-95"
                            wire:click="marcarAusenteAviso"
                            wire:confirm="¿Estás seguro de que deseas marcar este turno como 'Ausente avisó'? Se liberará el cupo de esta fecha."
                            wire:loading.attr="disabled">
                            Avisó que no viene
                        </button>
                    </div>

                    <button class="modal-informativo__accion w-full bg-gray-100 text-gray-700 transition-all hover:bg-gray-200" wire:click="cerrarModal">
                        Cancelar
                    </button>
                @elseif ($mostrarFechas)
                    @if ($turnoSeleccionado->esAusenteAviso())
                        <p class="mb-4 text-center text-sm text-gray-400">
                            @if ($avisoRecienMarcado)
                                El cupo de esta fecha ya quedó libre. Podés asignar otra ahora o cerrar y hacerlo más tarde.
                            @else
                                El cupo de esta fecha está libre. Podés asignar otra ahora o cerrar y hacerlo más tarde.
                            @endif
                        </p>
                    @elseif ($turnoSeleccionado->esReprogramado())
                        <p class="mb-4 text-center text-sm text-gray-400">
                            Podés cambiar la fecha de este turno reprogramado. El ausente avisó original no se modifica.
                        </p>
                    @endif

                    <div class="mb-8 space-y-3">
                        @if ($this->puedeCruzarActividad)
                            <div class="modal-informativo__seccion">
                                <label class="modal-informativo__etiqueta mb-1 block">Actividad del nuevo turno</label>
                                <select class="entrada w-full" wire:model.live="idActividadDestino">
                                    <option value="{{ $turnoSeleccionado->actividadPaciente->id_actividad }}">
                                        {{ $turnoSeleccionado->actividadPaciente->actividad->nombre }}
                                    </option>
                                    <option value="{{ $turnoSeleccionado->actividadPaciente->actPacDual->id_actividad }}">
                                        {{ $turnoSeleccionado->actividadPaciente->actPacDual->actividad->nombre }}
                                    </option>
                                </select>
                            </div>
                        @endif

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
                        @if ($this->esKinesio && $modoKinesio === 'corregir' && !$turnoSeleccionado->esReprogramado())
                            <button class="modal-informativo__accion flex-1 bg-gray-100 text-gray-700 transition-all hover:bg-gray-200" wire:click="volverChooserKinesio">
                                Volver
                            </button>
                        @else
                            <button class="modal-informativo__accion flex-1 bg-gray-100 text-gray-700 transition-all hover:bg-gray-200" wire:click="cerrarModal">
                                {{ $turnoSeleccionado->esAusenteAviso() ? 'Cerrar' : 'Cancelar' }}
                            </button>
                        @endif
                        <button
                            class="modal-informativo__accion flex-1 transition-all {{ $this->esMismoTurno ? 'bg-gray-400 cursor-not-allowed opacity-50' : 'bg-emerald-600 hover:bg-emerald-700 text-white' }}"
                            wire:click="actualizar"
                            wire:loading.attr="disabled"
                            @disabled($this->esMismoTurno)
                        >
                            Guardar Cambios
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
