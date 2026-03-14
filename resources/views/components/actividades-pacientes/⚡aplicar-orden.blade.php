<?php

use App\Models\ActividadPaciente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public $cantidad_sesiones = '';
    public $dia;
    public $mes;
    public $anio;
    public $id_act_pac = '';

    public function mount()
    {
        $ahora = now();
        $this->anio = $ahora->year;
        $this->mes = $ahora->month;
        $this->dia = $ahora->day;
    }

    public function updatedCantidadSesiones()
    {
        $this->id_act_pac = '';
    }

    public function updatedMes() { $this->validarDia(); }
    public function updatedAnio() { $this->validarDia(); }

    private function validarDia()
    {
        $maxDias = cal_days_in_month(CAL_GREGORIAN, (int)$this->mes, (int)$this->anio);
        if ((int) $this->dia > $maxDias) {
            $this->dia = $maxDias;
        }
    }

    #[Computed]
    public function inscripcionesFiltradas()
    {
        if (empty($this->cantidad_sesiones)) {
            return collect();
        }

        return ActividadPaciente::select('actividades_pacientes.*')
            ->with([
                'actividad:id,nombre',
                'pacienteRegular:id,nombre,apellido'
            ])
            ->tienePacienteRegular()
            ->conActividad()
            ->deTipo(2)
            ->whereNull('actividades_pacientes.fecha_emision_ord')
            ->where('cant_sesiones', $this->cantidad_sesiones)
            ->whereHas('pacienteRegular', function($consulta) {
                $consulta->tieneObraSocial();
            })
            ->doesntHave('pagos')
            ->get();
    }

    #[Computed]
    public function diasDelMes()
    {
        if (!$this->mes || !$this->anio) return [];

        $cantidadDias = cal_days_in_month(CAL_GREGORIAN, (int) $this->mes, (int) $this->anio);
        return range(1, $cantidadDias);
    }

    public function aplicarOrden()
    {
        $this->validate([
            'id_act_pac' => 'required|exists:actividades_pacientes,id',
            'dia' => 'required|integer|min:1|max:31',
            'mes' => 'required|integer|min:1|max:12',
            'anio' => 'required|integer',
            'cantidad_sesiones'=>'required|in:5,10'
        ]);

        DB::beginTransaction();

        try {
            $inscripcion = ActividadPaciente::findOrFail($this->id_act_pac);
            $inscripcion->update([
                'fecha_emision_ord'  => Carbon::create($this->anio, $this->mes, $this->dia),
                'pago_completado'    => true
            ]);

            DB::commit();

            session()->flash('exito', '¡La orden médica ha sido aplicada con éxito!');
            return redirect()->route('actividades-pacientes.inicio');

        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error('[(Livewire) actividades-pacientes.aplicar-orden@aplicarOrden] Error al aplicar la orden médica.', ['excepción' => $ex->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor max-w-3xl">
    <form class="formulario" wire:submit.prevent="aplicarOrden">
        <h2 class="titulo-formulario">Aplicar orden médica</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="cantidad-select" class="etiqueta-formulario">Sesiones que cubre la orden</label>
                <select
                    id="cantidad-select"
                    class="entrada w-full @error('cantidad_sesiones') border-red-500 @enderror"
                    wire:model.live="cantidad_sesiones"
                    required>
                    <option value="" disabled selected>Seleccione una cantidad</option>
                    <option value="5">5 sesiones</option>
                    <option value="10">10 sesiones</option>
                </select>
                @error('cantidad_sesiones') <span class="text-red-500 italic text-sm">{{ $message }}</span> @enderror
            </div>
            <div class="columna-campo flex-1">
                <h3 class="etiqueta-formulario">Fecha de emisión de la orden médica</h3>
                <div class="flex gap-2">
                    <select id="dia-select" class="entrada flex-1" wire:model.live="dia" required>
                        @foreach($this->diasDelMes as $dia)
                            <option value="{{ $dia }}">{{ $dia }}</option>
                        @endforeach
                    </select>

                    <select id="mes-select" class="entrada flex-1" wire:model.live="mes" required>
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
                <p class="mb-1 text-gray-300 italic">Solo se muestran sesiones sin pagos registrados.</p>
                <select
                    id="act-pac-select"
                    class="entrada @error('id_act_pac') border-red-500 @enderror"
                    wire:model.live="id_act_pac"
                    @if($this->inscripcionesFiltradas->isEmpty()) disabled @endif
                    required
                >
                    <option value="" selected>
                        @if(empty($this->cantidad_sesiones))
                            Primero seleccione una cantidad
                        @elseif($this->inscripcionesFiltradas->isEmpty())
                            No hay inscripciones de {{ $this->cantidad_sesiones }} sesiones
                        @else
                            Seleccione un registro de sesiones
                        @endif
                    </option>
                    @foreach($this->inscripcionesFiltradas as $insc)
                        <option value="{{ $insc->id }}">
                            [{{ $insc->fecha_comienzo->format('d/m/Y') }}]
                            {{ $insc->nombre_actividad }} - {{ $insc->ap_nom_paciente }}
                        </option>
                    @endforeach
                </select>
                @error('id_act_pac') <span class="text-red-500 italic text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Aplicar orden médica</button>
    </form>
</div>
