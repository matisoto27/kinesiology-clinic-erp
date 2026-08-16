<?php

use App\Models\ActividadPaciente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'pago')]
    public string $filtroPago = '';

    #[Url(as: 'ocultarFuturas')]
    public bool $ocultarInscripcionesFuturas = true;

    #[Url(as: 'paciente')]
    public string $consultaPaciente = '';

    public ?ActividadPaciente $inscripcionSeleccionada = null;

    public bool $mostrarModal = false;

    public function updatingFiltroPago(): void
    {
        $this->resetPage();
    }

    public function updatingOcultarInscripcionesFuturas(): void
    {
        $this->resetPage();
    }

    public function updatingConsultaPaciente(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function inscripciones()
    {
        return ActividadPaciente::query()
            ->select([
                'actividades_pacientes.id',
                'actividades_pacientes.id_act_pac_dual',
                'actividades_pacientes.id_actividad',
                'actividades_pacientes.id_paciente',
                'actividades_pacientes.id_paciente_casual',
                'actividades_pacientes.cant_sesiones',
                'actividades_pacientes.total_a_pagar',
                'actividades_pacientes.pago_completado',
                'actividades_pacientes.fecha_emision_ord',
                'actividades_pacientes.fecha_recargo',
                'actividades_pacientes.porcentaje_recargo',
                'actividades_pacientes.monto_recargo',
                'actividades_pacientes.frecuencia_total_dual',
                'actividades_pacientes.created_at',
            ])
            ->with([
                'actividad:id,nombre,id_tipo_actividad',
                'pacienteRegular:id,nombre,apellido',
                'pacienteCasual:id,nombre,apellido',
                'pacienteFijo:id,id_paciente',
                'primerTurno:id,id_act_pac,fecha_hora',
                'actPacDual' => fn ($q) => $q->withSum('pagos', 'monto'),
                'actPacDual.actividad:id,nombre,id_tipo_actividad',
                'actPacDual.primerTurno:id,id_act_pac,fecha_hora',
            ])
            ->withSum('pagos', 'monto')
            ->where(function ($q) {
                $q->whereNull('actividades_pacientes.id_act_pac_dual')
                    ->orWhereNull('actividades_pacientes.frecuencia_total_dual')
                    ->orWhereColumn('actividades_pacientes.id', '<', 'actividades_pacientes.id_act_pac_dual');
            })
            ->addSelect(DB::raw('
                GREATEST(
                    actividades_pacientes.id,
                    COALESCE(actividades_pacientes.id_act_pac_dual, actividades_pacientes.id)
                ) as dual_group_key
            '))
            ->addSelect(DB::raw('
                (
                    SELECT MIN(t.fecha_hora)
                    FROM turnos t
                    WHERE t.id_turno_original IS NULL
                      AND t.id_act_pac IN (
                          actividades_pacientes.id,
                          COALESCE(actividades_pacientes.id_act_pac_dual, actividades_pacientes.id)
                      )
                ) as ancla_ciclo
            '))
            ->when($this->filtroPago === 'completado', fn ($q) => $q->where(function ($q) {
                $q->where(fn ($q) => $q
                        ->where('actividades_pacientes.pago_completado', true)
                        ->where('actividades_pacientes.total_a_pagar', '>', 0))
                    ->orWhereHas('actPacDual', fn ($q) => $q
                        ->where('pago_completado', true)
                        ->where('total_a_pagar', '>', 0));
            }))
            ->when($this->filtroPago === 'pendiente', fn ($q) => $q->where(function ($q) {
                $q->where(fn ($q) => $q
                        ->where('actividades_pacientes.pago_completado', false)
                        ->where('actividades_pacientes.total_a_pagar', '>', 0))
                    ->orWhereHas('actPacDual', fn ($q) => $q
                        ->where('pago_completado', false)
                        ->where('total_a_pagar', '>', 0));
            }))
            ->when($this->consultaPaciente !== '', fn ($q) => $q->buscarPaciente($this->consultaPaciente))
            ->when($this->ocultarInscripcionesFuturas, fn ($q) => $q->where(function ($q) {
                $limite = now()->startOfDay()->addWeek();
                $q->whereHas('primerTurno', fn ($sq) => $sq->where('turnos.fecha_hora', '<', $limite))
                    ->orWhereHas('actPacDual.primerTurno', fn ($sq) => $sq->where('turnos.fecha_hora', '<', $limite));
            }))
            ->orderByDesc('ancla_ciclo')
            ->orderByDesc('dual_group_key')
            ->orderByDesc('actividades_pacientes.id')
            ->paginate(10);
    }

    public function verDetalles(int $id): void
    {
        $this->inscripcionSeleccionada = ActividadPaciente::with([
            'actividad',
            'pacienteRegular',
            'pacienteCasual',
            'turnos',
            'actPacDual.actividad',
            'actPacDual.turnos',
        ])->find($id);
        $this->mostrarModal = true;
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->inscripcionSeleccionada = null;
    }

    public function eliminar(int $id): void
    {
        try {
            $inscripcion = ActividadPaciente::withCount('pagos')->findOrFail($id);
            $parDual = $inscripcion->id_act_pac_dual
                ? ActividadPaciente::withCount('pagos')->find($inscripcion->id_act_pac_dual)
                : null;

            if ($inscripcion->perteneceAPacienteFijo() || ($parDual && $parDual->perteneceAPacienteFijo())) {
                session()->flash('error', 'Las inscripciones de pacientes fijos no se pueden eliminar desde aquí. Debe dar de baja al paciente desde el listado de Pacientes fijos.');
                return;
            }

            if ($inscripcion->pagos_count > 0 || ($parDual && $parDual->pagos_count > 0)) {
                session()->flash('error', 'No se puede eliminar la inscripción porque ya tiene pagos registrados.');
                return;
            }

            DB::transaction(function () use ($inscripcion, $parDual) {
                $inscripcion->update(['id_act_pac_dual' => null]);
                $parDual?->update(['id_act_pac_dual' => null]);

                $inscripcion->delete();
                $parDual?->delete();
            });

            session()->flash('exito', 'La inscripción ha sido eliminada correctamente.');
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) actividades-pacientes.inicio@eliminar] Error al eliminar la inscripción.', [
                'id' => $id,
                'excepción' => $ex->getMessage(),
            ]);

            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor-listado w-full">
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

        <div class="flex items-center gap-1 self-end pb-2">
            <input
                id="ocultar-inscripciones-futuras"
                type="checkbox"
                class="checkbox-formulario"
                wire:model.live="ocultarInscripcionesFuturas"
                wire:loading.attr="disabled"
                wire:target="ocultarInscripcionesFuturas"
            >
            <label for="ocultar-inscripciones-futuras" class="etiqueta-formulario">
                Ocultar inscripciones futuras
            </label>
        </div>
    </div>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th colspan="2">Descripción</th>
                <th>Primer turno</th>
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
            @forelse($this->inscripciones as $actPac)
                @php
                    $par = $actPac->esDualCompleto() ? $actPac->actPacDual : null;
                    $cobro = collect([$actPac, $par])
                        ->filter()
                        ->first(fn ($inscripcion) => (float) $inscripcion->total_a_pagar > 0)
                        ?? $actPac;
                    $cantidad = (int) $actPac->cant_sesiones + (int) ($par?->cant_sesiones ?? 0);
                    $esGeneral = $actPac->actividad->esActividadGeneral();
                    $cubiertaOS = !$esGeneral && $actPac->fecha_emision_ord !== null;
                    $primerTurno = collect([$actPac->primerTurno, $par?->primerTurno])
                        ->filter()
                        ->sortBy(fn ($turno) => $turno->fecha_hora->timestamp)
                        ->first();
                    $freqGym = 0;
                    $freqPilates = 0;
                    if ($par) {
                        $porActividad = collect([$actPac, $par])->keyBy('id_actividad');
                        $freqGym = (int) ($porActividad->get(\App\Models\Actividad::GIMNASIO)?->frecuenciaSemanal() ?? 0);
                        $freqPilates = (int) ($porActividad->get(\App\Models\Actividad::PILATES)?->frecuenciaSemanal() ?? 0);
                    }
                @endphp

                <tr class="tabla-listado__fila h-28" wire:key="inscripcion-{{ $actPac->id }}">
                    <td colspan="2">
                        @if ($actPac->esRegular())
                            @if ($par)
                                <span class="badge mr-1 bg-indigo-600">
                                    Dual (x{{ $actPac->frecuencia_total_dual }})
                                </span>
                                {{ $actPac->ap_nom_paciente }}
                            @else
                                {{ $actPac->nombre_actividad }} | {{ $actPac->ap_nom_paciente }}
                            @endif
                        @elseif ($actPac->esGympass())
                            <span class="badge bg-emerald-600">Paciente Gympass</span>
                            {{ $actPac->ap_nom_paciente }}
                        @else
                            <span class="badge bg-purple-600">Prueba de Pilates</span>
                            {{ $actPac->ap_nom_paciente }}
                        @endif
                    </td>
                    <td>
                        {{ $primerTurno?->fecha_hora->format('d/m/Y H:i') ?? '—' }}
                        @if ($actPac->esRegular() && $actPac->esPrimeraInscripcion() && (!$par || $par->esPrimeraInscripcion()))
                            <span class="badge bg-slate-500 text-xs">Primera inscripción</span>
                        @endif
                    </td>
                    <td>
                        @if ($esGeneral)
                            <span class="block font-bold">
                                {{ $cantidad }} {{ $cantidad === 1 ? 'turno' : 'turnos' }}
                            </span>
                            @if ($par)
                                <small>(Gx{{ $freqGym }} | Px{{ $freqPilates }})</small>
                            @elseif ($actPac->esRegular())
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
                            <div class="flex flex-col">
                                <span class="{{ $cobro->fecha_recargo ? 'text-gray-500 text-sm line-through' : 'font-bold' }}">
                                    ${{ number_format($cobro->total_a_pagar, 2, ',', '.') }}
                                </span>
                                @if ($cobro->fecha_recargo)
                                    <div class="flex flex-col text-red-600">
                                        <span class="font-bold">
                                            ${{ number_format($cobro->total_con_recargo, 2, ',', '.') }}
                                        </span>
                                        <div class="flex flex-col text-sm font-semibold">
                                            <span>Recargo: ${{ number_format($cobro->monto_recargo, 2, ',', '.') }}</span>
                                            <span>({{ number_format($cobro->porcentaje_recargo, 2, ',', '.') }}%)</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
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
                        @if($cobro->pago_completado)
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
                        @if($cobro->deuda > 0)
                            <span class="px-3 py-1 inline-flex items-center bg-red-500 rounded text-sm font-semibold">
                                ${{ number_format($cobro->deuda, 2, ',', '.') }}
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
                        @unless ($actPac->perteneceAPacienteFijo())
                            <button
                                type="button"
                                class="text-white hover:text-red-400 transition-colors duration-200"
                                wire:click="eliminar({{ $actPac->id }})"
                                wire:confirm="¿Estás seguro de que deseas eliminar la inscripción? Se eliminará tanto la inscripción como todos los turnos asociados a la misma.">
                                <x-iconos.basura />
                            </button>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="py-10 text-center text-gray-300 italic">
                        {{ $filtroPago !== '' || $ocultarInscripcionesFuturas || $consultaPaciente !== ''
                            ? 'No se encontraron inscripciones con los filtros aplicados.'
                            : 'No hay registros disponibles.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $this->inscripciones->links(data: ['scrollTo' => false]) }}
    </div>

    @if($mostrarModal && $inscripcionSeleccionada)
        @php
            $parSel = $inscripcionSeleccionada->esDualCompleto() ? $inscripcionSeleccionada->actPacDual : null;
            $turnosModal = $inscripcionSeleccionada->turnos;
            if ($parSel) {
                $turnosModal = $turnosModal->concat($parSel->turnos);
            }
            $nroPorOriginal = $turnosModal
                ->whereNull('id_turno_original')
                ->sortBy(fn ($turno) => $turno->fecha_hora->timestamp)
                ->values()
                ->mapWithKeys(fn ($turno, $i) => [$turno->id => $i + 1]);
            $turnosModal = $turnosModal->sortBy(fn ($turno) => $turno->fecha_hora->timestamp)->values();
            $fechaAlta = $inscripcionSeleccionada->created_at;
            if ($parSel?->created_at?->lt($fechaAlta)) {
                $fechaAlta = $parSel->created_at;
            }
        @endphp

        <div class="modal-informativo" wire:keydown.escape.window="cerrarModal">
            <div class="modal-informativo__ventana" wire:click.outside="cerrarModal">
                <button class="modal-informativo__cerrar" wire:click="cerrarModal">
                    <x-iconos.cruz />
                </button>

                <h2 class="modal-informativo__titulo">
                    [{{ $fechaAlta->format('d/m/Y H:i') }}]
                    <div>
                        {{ $parSel ? 'Dual' : $inscripcionSeleccionada->nombre_actividad }} - {{ $inscripcionSeleccionada->ap_nom_paciente }}
                    </div>
                </h2>

                <div class="space-y-3">
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
                            @forelse($turnosModal as $turno)
                                @php
                                    $nroTurno = $nroPorOriginal[$turno->id_turno_original ?? $turno->id] ?? null;
                                    $nombreActividadTurno = $turno->id_act_pac === $inscripcionSeleccionada->id
                                        ? $inscripcionSeleccionada->nombre_actividad
                                        : $parSel?->nombre_actividad;
                                @endphp
                                <div class="modal-informativo__elemento-lista flex justify-between items-center">
                                    <div>
                                        @if($turno->id_turno_original)
                                            <span class="text-blue-500 text-sm font-semibold uppercase">Reprogramado</span>
                                        @endif
                                        <p class="modal-informativo__etiqueta">
                                            Turno #{{ $nroTurno }}{{ $parSel ? ' (' . $nombreActividadTurno . ')' : '' }}
                                        </p>
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
                        @if($turnosModal->count() > 0)
                            <div class="mt-2 flex justify-center">
                                <a href="{{ route('turnos.inicio', [
                                        'paciente' => $inscripcionSeleccionada->paciente->apellido . ' ' . $inscripcionSeleccionada->paciente->nombre
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
