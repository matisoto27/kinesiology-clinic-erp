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
        $ahora = Carbon::now();

        $limInferior = $ahora->copy()->startOfHour()->subHour();
        $limSuperior = $ahora->copy()->startOfHour()->addHours(2);

        return Turno::query()
            ->with([
                'actividadPaciente.actividad:id,nombre',
                'actividadPaciente.paciente:id,nombre,apellido'
            ])
            ->whereBetween('fecha_hora', [$limInferior, $limSuperior])
            ->when($this->idTipoActividad > 0, function($consulta) {
                $consulta->whereHas('actividadPaciente.actividad', fn($sc) => $sc->where('id_tipo_actividad', $this->idTipoActividad));
            })
            ->when($this->idActividad > 0, function($consulta) {
                $consulta->whereHas('actividadPaciente', fn($sc) => $sc->where('id_actividad', $this->idActividad));
            })
            ->when(!empty($this->consultaPaciente), function($consulta) {
                $consulta->whereHas('actividadPaciente.paciente', function ($subconsulta) {
                    $subconsulta->where(DB::raw("CONCAT(apellido, ' ', nombre)"), 'LIKE', "%{$this->consultaPaciente}%");
                });
            })
            ->orderBy('fecha_hora')
            ->paginate(10);
    }

    public function confirmarAsistenciaTurno(int $idTurno)
    {
        try {
            DB::transaction(function () use ($idTurno) {
                $turno = Turno::lockForUpdate()->findOrFail($idTurno);

                if ($turno->asiste) {
                    throw new \Exception('La asistencia ya se encuentra confirmada.');
                }

                $turno->update(['asiste' => true]);
            });

            session()->flash('exito', '¡Asistencia confirmada con éxito!');
        } catch (\Throwable $ex) {
            Log::error('[(Livewire)principal@confirmarAsistenciaTurno] Error al confirmar la asistencia del turno.', [
                'id' => $idTurno,
                'excepción' => $ex->getMessage()
            ]);
            session()->flash('error', $ex instanceof \Exception ? $ex->getMessage() : 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor my-5 px-8 max-w-screen-lg bg-[#006E6B] rounded-3xl" wire:poll.60s="actualizarReloj">
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
                placeholder="Ingrese nombre o apellido"
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
                <th class="py-3 w-2/11 text-center">Hora de ingreso</th>
                <th class="py-3 w-3/11 text-center">Paciente</th>
                <th class="py-3 w-3/11 text-center">Actividad</th>
                <th class="py-3 w-3/11 text-center">Asistencia</th>
            </tr>
        </thead>

        <tbody class="bg-white">
            @if($this->turnos->count())
                @foreach($this->turnos as $turno)
                    <tr class="border-b last:border-b-0" wire:key="turno-{{ $turno->id }}">
                        <td class="w-2/11 text-center py-3">{{ $turno->fecha_hora->format('H:i') }}</td>
                        <td class="w-3/11 text-center py-3">{{ $turno->actividadPaciente->paciente->apellido . ' ' . $turno->actividadPaciente->paciente->nombre }}</td>
                        <td class="w-3/11 text-center py-3">{{ $turno->actividadPaciente->actividad->nombre }}</td>
                        <td class="w-3/11 text-center py-3">
                            @if($turno->asiste)
                                <button class="bg-green-300 text-black py-2 px-4 rounded-full transition-colors" disabled>Confirmada</button>
                            @else
                                <button
                                    class="turno-button py-2 px-4 rounded-full transition-colors bg-[#F5D500]"
                                    wire:click="confirmarAsistenciaTurno({{ $turno->id }})"
                                    wire:confirm="¿Está seguro de que desea confirmar la asistencia del turno?"
                                    wire:loading.attr="disabled">
                                    Confirmar
                                </button>
                            @endif
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

    {{ $this->turnos->links() }}
</div>
