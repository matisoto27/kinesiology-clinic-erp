<?php

use App\Models\ActividadPaciente;
use App\Models\Caja;
use App\Models\Pago;
use App\Models\Profesional;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public $idActPac = '';
    public $idProfesional = '';
    public $montoStr = '';
    public $monto;
    public $metodo = 'Efectivo';

    #[Computed]
    public function actividadesPacientes()
    {
        return ActividadPaciente::query()
            ->with([
                'actividad:id,nombre',
                'pacienteRegular:id,nombre,apellido'
            ])
            ->whereNotNull('fecha_emision_ord')
            ->whereBetween('fecha_comienzo', [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()
            ])
            ->tienePacienteRegular()
            ->latest('fecha_comienzo')
            ->get();
    }

    #[Computed]
    public function profesionales()
    {
        return Profesional::select('id', 'nombre', 'apellido')->get();
    }

    public function almacenar()
    {
        $this->monto = $this->obtenerMontoParaEnviar($this->montoStr);
        $this->validate([
            'idActPac' => 'required|exists:actividades_pacientes,id',
            'idProfesional' => 'required|exists:profesionales,id',
            'monto' => 'required|numeric|gt:0',
            'metodo' => 'required|in:Efectivo,Transferencia'
        ]);

        try {
            DB::transaction(function () {
                $columna = $this->metodo === 'Efectivo' ? 'saldo_efectivo' : 'saldo_transferencia';
                Caja::lockForUpdate()->firstOrFail()->increment($columna, $this->monto);

                Pago::create([
                    'id_act_pac' => $this->idActPac,
                    'id_profesional' => $this->idProfesional,
                    'metodo' => $this->metodo,
                    'monto' => $this->monto,
                    'es_copago' => true
                ]);
            });

            return redirect()->route('movimientos')->with('exito', '¡El copago ha sido registrado con éxito!');

        } catch (\Throwable $th) {
            Log::error('[(Livewire) pagos.copagos.crear@almacenar] Error al registrar copago.', ['excepción' => $th->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function obtenerMontoParaEnviar($montoStr)
    {
        if (!is_string($montoStr) || trim($montoStr) === '') {
            return 0.0;
        }

        $limpio = str_replace(['.', ','], ['', '.'], $montoStr);
        return (float) $limpio;
    }
};
?>

<div class="contenedor max-w-3xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <h2 class="titulo-formulario">Registrar Copago</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="act-pac-select" class="etiqueta-formulario">Sesiones del Paciente</label>
                <select
                    id="act-pac-select"
                    @class([
                        'entrada w-full',
                        'border-red-500 border-2' => $errors->has('idActPac')
                    ])
                    wire:model.live="idActPac"
                    required
                >
                    <option value="" disabled selected>Seleccione un registro de sesiones</option>
                    @foreach($this->actividadesPacientes as $actPac)
                        <option value="{{ $actPac->id }}">
                            [{{ $actPac->fecha_comienzo->format('d/m/Y') }}]
                            {{ $actPac->nombre_actividad }} ({{ $actPac->cant_sesiones === 1 ? '1 sesión' : $actPac->cant_sesiones . ' sesiones' }}) - {{ $actPac->ap_nom_paciente }}
                        </option>
                    @endforeach
                </select>
                @error('idActPac') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="profesional-select" class="etiqueta-formulario">Profesional que lo registra</label>
                <select
                    id="profesional-select"
                    @class([
                        'entrada',
                        'border-red-500 border-2' => $errors->has('idProfesional')
                    ])
                    wire:model.live="idProfesional"
                    required
                >
                    <option value="" disabled selected>Seleccione un profesional</option>
                    @foreach($this->profesionales as $prof)
                        <option value="{{ $prof->id }}">{{ $prof->apellido }}, {{ $prof->nombre }}</option>
                    @endforeach
                </select>
                @error('idProfesional') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="monto-input" class="etiqueta-formulario">Monto del Copago</label>
                <input
                    id="monto-input"
                    type="text"
                    placeholder="Ejemplo: 25000,00"
                    @class([
                        'entrada',
                        'border-red-500 border-2' => $errors->has('monto')
                    ])
                    wire:model="montoStr"
                    x-on:input="$wire.$js.transformarIngresoMonto($el)"
                >
                @error('monto') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="metodo-select" class="etiqueta-formulario">Método de Pago</label>
                <select
                    id="metodo-select"
                    @class([
                        'entrada',
                        'border-red-500 border-2' => $errors->has('metodo')
                    ])
                    wire:model.live="metodo"
                    required
                >
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                </select>
                @error('metodo') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Registrar</button>
    </form>
</div>

<script>
    this.$js.transformarIngresoMonto = (input) => {
        let valorIngresado = input.value;

        // No permite ingresar puntos
        // Solo permite ingresar números o coma
        valorIngresado = valorIngresado.replace(/\./g, '').replace(/[^0-9,]/g, '');

        // Si se ingresa una coma como primer caracter, se agrega un 0 delante
        if (valorIngresado.startsWith(',')) valorIngresado = '0' + valorIngresado;

        // Solo puede haber una única coma
        let partes = valorIngresado.split(',');
        let parteEntera = partes[0];
        let parteDecimal = partes.length > 1 ? partes.slice(1).join('') : null;

        if (parteEntera.length > 0) {
            // Eliminar ceros a la izquierda y limitar a 6 dígitos (máximo 9.999.999)
            parteEntera = parseInt(parteEntera, 10).toString().substring(0, 7);

            // Formatear miles con puntos
            parteEntera = parteEntera.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Máximo 2 decimales
        input.value = partes.length > 1
            ? parteEntera + ',' + parteDecimal.substring(0, 2)
            : parteEntera + (valorIngresado.includes(',') ? ',' : '');
    }
</script>
