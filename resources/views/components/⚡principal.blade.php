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

    #[Locked]
    public Collection $tiposActividad;

    #[Url(as: 'tipo')]
    public int $idTipoActividad = 0;

    #[Url(as: 'actividad')]
    public int $idActividad = 0;

    #[Url(as: 'paciente')]
    public string $consultaPaciente = '';

    public string $fechaActual = '';
    public string $horaActual = '';

    public function mount(Collection $tiposActividad)
    {
        $this->tiposActividad = $tiposActividad;
        $this->actualizarReloj();
    }

    public function actualizarReloj()
    {
        $ahora = Carbon::now();
        $this->fechaActual = $ahora->format('d/m/Y');
        $this->horaActual = $ahora->format('H:i');
    }

    public function updatedIdTipoActividad() { $this->idActividad = 0; $this->resetPage(); }
    public function updatedIdActividad() { $this->resetPage(); }
    public function updatedConsultaPaciente() { $this->resetPage(); }

    #[Computed]
    public function actividadesFiltradas()
    {
        if ($this->idTipoActividad === 0) {
            return collect();
        }

        $tipo = $this->tiposActividad->firstWhere('id', $this->idTipoActividad);
        return $tipo ? $tipo->actividades : collect();
    }

    #[Computed]
    public function turnos()
    {
        $hoy = Carbon::today();

        $inicioDia = $hoy;
        $finDia = $hoy->copy()->endOfDay();

        return Turno::query()
            ->with([
                'actividadPaciente.actividad',
                'actividadPaciente.pacienteRegular:id,nombre,apellido',
                'actividadPaciente.pacienteCasual:id,nombre,apellido'
            ])
            ->whereBetween('fecha_hora', [$inicioDia, $finDia])
            ->when($this->idTipoActividad > 0, function($consulta) {
                $consulta->whereHas('actividadPaciente.actividad', fn($sc) => $sc->where('id_tipo_actividad', $this->idTipoActividad));
            })
            ->when($this->idActividad > 0, function($consulta) {
                $consulta->whereHas('actividadPaciente', fn($sc) => $sc->where('id_actividad', $this->idActividad));
            })
            ->when(!empty($this->consultaPaciente), function($consulta) {
                $consulta->whereHas('actividadPaciente', fn($sc) => $sc->buscarPaciente($this->consultaPaciente));
            })
            ->orderBy('fecha_hora')
            ->paginate(10);
    }

    public function confirmarAsistencia(int $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $turno = Turno::lockForUpdate()->findOrFail($id);
                if ($turno->esAusenteAviso()) {
                    throw new \Exception('No es posible confirmar la asistencia de un turno con aviso de ausencia previo.');
                }
                if (str_contains($turno->estado, 'Presente')) {
                    throw new \Exception('La asistencia de este turno ya ha sido confirmada previamente.');
                }
                $turno->update(['estado' => 'Presente']);
            });

            session()->flash('exito', '¡Asistencia confirmada con éxito!');

        } catch (\Throwable $th) {
            Log::error('[(Livewire) principal@confirmarAsistencia] Error al actualizar estado del turno.', [
                'id' => $id,
                'excepción' => $th->getMessage()
            ]);

            session()->flash('error', $th instanceof \Exception
                ? $th->getMessage()
                : 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function marcarAusenteAviso($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $turno = Turno::lockForUpdate()->findOrFail($id);

                if (!$turno->actividadPaciente->actividad->esActividadGeneral()) {
                    throw new \Exception("Los turnos de tipo kinesiología solo admiten estados 'Ausente' o 'Presente'.");
                } else if (str_contains($turno->estado, 'Presente')) {
                    throw new \Exception("No puede marcarse como 'Ausente avisó' un turno cuya asistencia ya fue confirmada.");
                }

                $turno->update(['estado' => 'Ausente avisó']);
            });

            session()->flash('exito', "El turno ha sido marcado como 'Ausente avisó'.");

        } catch (\Throwable $th) {
            Log::error('[(Livewire) principal@marcarAusenteAviso] Error al actualizar estado del turno.', [
                'id' => $id,
                'excepción' => $th->getMessage()
            ]);

            session()->flash('error', $th instanceof \Exception
                ? $th->getMessage()
                : 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor my-5 px-8 max-w-screen-xl bg-[#006E6B] rounded-3xl" wire:poll.60s="actualizarReloj">
    <div class="mb-4 flex justify-between text-white">
        <div class="text-3xl font-bold">
            <h2>Asistencia de hoy</h2>
            <p>{{ $fechaActual }}</p>
        </div>
        <p class="text-6xl">{{ $horaActual }}</p>
    </div>

    <div class="mb-4 flex gap-3">
        <div class="columna-campo">
            <label for="filtro-tipo" class="etiqueta-formulario">Tipo de actividad</label>
            <select
                id="filtro-tipo"
                class="entrada"
                wire:model.live="idTipoActividad">
                <option value="0">Todos los tipos</option>
                @foreach($tiposActividad as $tipo)
                    <option value="{{ $tipo->id }}">{{ $tipo->descripcion }}</option>
                @endforeach
            </select>
        </div>

        <div class="columna-campo w-xs">
            <label for="filtro-actividad" class="etiqueta-formulario">Actividad</label>
            <select
                id="filtro-actividad"
                class="entrada"
                @if($idTipoActividad === 0) disabled @endif
                wire:model.live="idActividad">
                <option value="0">Todas las actividades</option>
                @foreach($this->actividadesFiltradas as $act)
                    <option value="{{ $act->id }}">{{ $act->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="columna-campo w-xs">
            <div class="flex items-center gap-1">
                <x-iconos.lupa />
                <label for="buscar-paciente" class="etiqueta-formulario">Buscar Paciente</label>
            </div>
            <input
                id="buscar-paciente"
                type="text"
                placeholder="Ingrese nombre y/o apellido"
                class="entrada"
                wire:model.live.debounce.350ms="consultaPaciente"
            >
        </div>
    </div>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <table class="my-5 w-full overflow-hidden rounded-xl">
        <thead class="bg-[#014745] text-white">
            <tr>
                <th class="py-3 text-center">Hora de ingreso</th>
                <th colspan="2" class="py-3 text-center">Descripción</th>
                <th colspan="2" class="py-3 text-center">Acciones / Estado</th>
            </tr>
        </thead>

        <tbody class="bg-white">
            @if($this->turnos->count())
                @foreach($this->turnos as $turno)
                    <tr class="h-24 border-b last:border-b-0" wire:key="turno-{{ $turno->id }}">
                        <td class="text-center">{{ $turno->fecha_hora->format('H:i') }}</td>
                        <td colspan="2" class="text-center">
                            @if ($turno->actividadPaciente->esRegular())
                                {{ $turno->actividadPaciente->nombre_actividad }} |
                                {{ $turno->ap_nom_paciente }} |
                                Turno: {{ $turno->nro_turno }} / {{ $turno->actividadPaciente->cant_sesiones }}
                            @elseif ($turno->actividadPaciente->esGympass())
                                <span class="badge bg-emerald-600">Paciente Gympass</span>
                                {{ $turno->ap_nom_paciente }} |
                                Turno: {{ $turno->nro_turno }} / {{ $turno->actividadPaciente->cant_sesiones }}
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
                        <td colspan="2" class="text-center">
                            <div class="w-fit mx-auto grid grid-cols-2 gap-2">
                                @if ($turno->id_turno_original === null)
                                    @if ($turno->actividadPaciente->actividad->esActividadGeneral())
                                        @if ($turno->esAusenteAviso())
                                            <div class="col-span-2 flex justify-center">
                                                <span class="px-4 py-2 bg-red-600 text-white font-semibold rounded-md cursor-not-allowed">
                                                    Ausente avisó (AA)
                                                </span>
                                            </div>
                                        @elseif (str_contains($turno->estado, 'Presente'))
                                            <div class="col-span-2 flex justify-center">
                                                <span class="px-4 py-2 bg-green-600 text-white font-semibold rounded-md cursor-not-allowed">
                                                    {{ $turno->estado }}
                                                </span>
                                            </div>
                                        @else
                                            <button
                                                class="px-4 py-2 bg-[#F5D500] hover:bg-green-600 hover:text-white text-lg font-medium rounded-full transition-all duration-100 active:scale-95 hover:scale-105"
                                                wire:click="confirmarAsistencia({{ $turno->id }})"
                                                wire:confirm="¿Estás seguro de que deseas confirmar la asistencia del turno?"
                                                wire:loading.attr="disabled">
                                                Confirmar asistencia
                                            </button>
                                            <button
                                                class="px-4 py-2 bg-orange-400 hover:bg-red-600 text-white text-lg font-medium rounded-md transition-all duration-100 active:scale-95 hover:scale-110"
                                                wire:click="marcarAusenteAviso({{ $turno->id }})"
                                                wire:confirm="¿Estás seguro de que deseas actualizar el estado del turno a 'Ausente avisó'?"
                                                wire:loading.attr="disabled">
                                                No viene pero avisó
                                            </button>
                                        @endif
                                    @else
                                        @if ($turno->estado === 'Ausente')
                                            <button
                                                class="px-4 py-2 bg-[#F5D500] hover:bg-green-600 hover:text-white text-lg font-medium rounded-full transition-all duration-100 active:scale-95 hover:scale-105"
                                                wire:click="confirmarAsistencia({{ $turno->id }})"
                                                wire:confirm="¿Estás seguro de que deseas confirmar la asistencia del turno?"
                                                wire:loading.attr="disabled">
                                                Confirmar asistencia
                                            </button>
                                            <div></div>
                                        @else
                                            <div class="col-span-2 flex justify-center">
                                                <span class="px-4 py-2 bg-green-600 text-white font-semibold rounded-md cursor-not-allowed">Presente</span>
                                            </div>
                                        @endif
                                    @endif
                                @else
                                    @if ($turno->estado === 'Ausente')
                                        <button
                                            class="px-4 py-2 bg-[#F5D500] hover:bg-green-600 hover:text-white text-lg font-medium rounded-full transition-all duration-100 active:scale-95 hover:scale-105"
                                            wire:click="confirmarAsistencia({{ $turno->id }})"
                                            wire:confirm="¿Estás seguro de que deseas confirmar la asistencia del turno?"
                                            wire:loading.attr="disabled">
                                            Confirmar asistencia
                                        </button>
                                        <div></div>
                                    @else
                                        <div class="col-span-2 flex justify-center">
                                            <span class="px-4 py-2 bg-green-600 text-white font-semibold rounded-md cursor-not-allowed">
                                                {{ $turno->estado }}
                                            </span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="4" class="py-4 text-lg text-center italic">No encontramos turnos para los filtros ingresados.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="mt-4">
        {{ $this->turnos->links(data: ['scrollTo' => false]) }}
    </div>
</div>
