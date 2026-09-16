<?php

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Services\ActividadPacienteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public $cantidadSesiones = '';
    public $dia = '';
    public $mes = '';
    public $anio;
    public $idActPac = '';

    public function mount()
    {
        $this->anio = now()->year;
    }

    public function updatedCantidadSesiones()
    {
        $this->idActPac = '';
    }

    public function updatedMes() { $this->validarDia(); }
    public function updatedAnio() { $this->validarDia(); }

    private function validarDia()
    {
        if ($this->mes === '' || $this->mes === null || empty($this->anio)) {
            return;
        }

        $maxDias = cal_days_in_month(CAL_GREGORIAN, (int) $this->mes, (int) $this->anio);
        if ($this->dia !== '' && (int) $this->dia > $maxDias) {
            $this->dia = $maxDias;
        }
    }

    #[Computed]
    public function inscripcionesFiltradas()
    {
        if (empty($this->cantidadSesiones)) {
            return collect();
        }

        $cupoOrden = (int) $this->cantidadSesiones;

        return ActividadPaciente::select('actividades_pacientes.*')
            ->with([
                'actividad:id,nombre',
                'pacienteRegular:id,nombre,apellido',
                'primerTurno:turnos.id,turnos.id_act_pac,turnos.fecha_hora',
            ])
            ->withCount([
                'turnos as turnos_efectivos_count' => fn ($q) => $q->whereNull('id_turno_original'),
            ])
            ->tienePacienteRegular()
            ->where('id_actividad', Actividad::KINESIOLOGIA_CONVENCIONAL)
            ->whereNull('actividades_pacientes.fecha_emision_ord')
            ->whereHas('pacienteRegular', function ($consulta) {
                $consulta->tieneObraSocial();
            })
            ->doesntHave('pagos')
            ->get()
            ->filter(fn (ActividadPaciente $actPac) => (int) $actPac->turnos_efectivos_count <= $cupoOrden)
            ->values();
    }

    #[Computed]
    public function diasDelMes()
    {
        if (!$this->mes || !$this->anio) return [];

        $cantidadDias = cal_days_in_month(CAL_GREGORIAN, (int) $this->mes, (int) $this->anio);
        return range(1, $cantidadDias);
    }

    public function aplicarOrden(ActividadPacienteService $service)
    {
        $this->validate([
            'idActPac' => 'required|exists:actividades_pacientes,id',
            'dia' => 'required|integer|min:1|max:31',
            'mes' => 'required|integer|min:1|max:12',
            'anio' => 'required|integer',
            'cantidadSesiones'=>'required|in:5,10'
        ]);

        try {
            $particular = ActividadPaciente::findOrFail($this->idActPac);
            $fechaEmision = Carbon::create((int) $this->anio, (int) $this->mes, (int) $this->dia)
                ->toDateString();

            $registro = $service->aplicarOrdenAParticular(
                $particular,
                (int) $this->cantidadSesiones,
                $fechaEmision
            );

            session()->flash('exito', 'Orden médica aplicada. Continuá agendando los turnos que faltan.');

            return $this->redirectRoute(
                'actividades-pacientes.kinesiologia.con-orden.crear',
                ['id_paciente' => $registro->id_paciente],
                navigate: true
            );
        } catch (ReglaNegocioException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) actividades-pacientes.aplicar-orden@aplicarOrden] Error al aplicar la orden médica.', [
                'excepción' => $ex->getMessage(),
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor max-w-3xl">
    <form class="formulario" wire:submit.prevent="aplicarOrden">
        <h2 class="titulo-formulario">Aplicar orden médica</h2>
        <p class="mb-4 text-sm text-gray-400">
            Solo particulares de Kinesiología Convencional sin pagos, con turnos que no excedan el cupo de la orden.
        </p>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="cantidad-select" class="etiqueta-formulario">Sesiones que cubre la orden</label>
                <select
                    id="cantidad-select"
                    class="entrada w-full @error('cantidadSesiones') border-red-500 @enderror"
                    wire:model.live="cantidadSesiones"
                    required>
                    <option value="" disabled selected>Seleccione una cantidad</option>
                    <option value="5">5 sesiones</option>
                    <option value="10">10 sesiones</option>
                </select>
                @error('cantidadSesiones') <span class="text-red-500 italic text-sm">{{ $message }}</span> @enderror
            </div>
            <div class="columna-campo flex-1">
                <h3 class="etiqueta-formulario">Fecha de emisión de la orden médica</h3>
                <div class="flex gap-2">
                    <select id="dia-select" class="entrada flex-1" wire:model.live="dia" required>
                        <option value="" disabled selected>Día</option>
                        @foreach($this->diasDelMes as $dia)
                            <option value="{{ $dia }}">{{ $dia }}</option>
                        @endforeach
                    </select>

                    <select id="mes-select" class="entrada flex-1" wire:model.live="mes" required>
                        <option value="" disabled selected>Mes</option>
                        @foreach(range(1, 12) as $numeroMes)
                            <option value="{{ $numeroMes }}">
                                {{ ucfirst(Carbon::create(null, $numeroMes, 1)->translatedFormat('F')) }}
                            </option>
                        @endforeach
                    </select>

                    <select id="anio-select" class="entrada flex-1" wire:model.live="anio" required>
                        <option value="{{ now()->subYear()->year }}">{{ now()->subYear()->year }}</option>
                        <option value="{{ now()->year }}" selected>{{ now()->year }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo">
                <label for="act-pac-select" class="etiqueta-formulario">Sesiones del paciente</label>
                <p class="mb-1 text-gray-300 italic">Sin pagos · Convencional · turnos ≤ cupo de la orden.</p>
                <select
                    id="act-pac-select"
                    class="entrada @error('idActPac') border-red-500 @enderror"
                    wire:model.live="idActPac"
                    @if($this->inscripcionesFiltradas->isEmpty()) disabled @endif
                    required
                >
                    <option value="" selected>
                        @if(empty($this->cantidadSesiones))
                            Primero seleccione una cantidad
                        @elseif($this->inscripcionesFiltradas->isEmpty())
                            No existe ningún registro que tenga una cantidad de sesiones compatible para una orden médica de {{ $this->cantidadSesiones }} sesiones
                        @else
                            Seleccione un registro de sesiones
                        @endif
                    </option>
                    @foreach($this->inscripcionesFiltradas as $insc)
                        <option value="{{ $insc->id }}">
                            [{{ $insc->primerTurno?->fecha_hora?->format('d/m/Y') ?? 'sin turnos' }}]
                            {{ $insc->ap_nom_paciente }}
                            · {{ $insc->turnos_efectivos_count }}/{{ $insc->cant_sesiones }} turnos
                            → orden {{ $this->cantidadSesiones }}
                        </option>
                    @endforeach
                </select>
                @error('idActPac') <span class="text-red-500 italic text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Aplicar orden médica</button>
    </form>
</div>
