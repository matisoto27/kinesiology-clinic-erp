<?php

use App\Models\Caja;
use App\Models\Egreso;
use App\Models\Profesional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public Collection $profesionales;

    public string $metodo = '';
    public string $montoStr = '';
    public float $monto = 0.0;
    public string $motivo = '';
    public ?int $idProfesional = null;

    protected function rules()
    {
        $reglasMonto = [
            'required',
            'numeric',
            'gt:0',
        ];

        if ($this->metodo === 'Efectivo') {
            $reglasMonto[] = 'lte:' . $this->saldoEfectivo;
        }

        return [
            'metodo' => 'required|in:Efectivo,Transferencia',
            'monto' => $reglasMonto,
            'motivo' => 'required|string|max:255',
            'idProfesional' => 'required|exists:profesionales,id',
        ];
    }

    protected function messages()
    {
        return [
            'monto.lte' => 'El monto supera el saldo disponible en caja.',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'metodo' => 'método',
        ];
    }

    public function mount()
    {
        $this->profesionales = Profesional::query()
            ->where('activo', true)
            ->orderByDesc('nombre')
            ->get(['id', 'nombre', 'apellido']);
    }

    public function updatedMetodo()
    {
        if ($this->monto !== null && $this->monto !== '') {
            $this->validateOnly('monto');
        }
    }

    public function updatedMontoStr($value)
    {
        $this->monto = $this->obtenerMontoParaEnviar($value);
        $this->validateOnly('monto');
    }

    #[Computed]
    public function saldoEfectivo()
    {
        return Caja::first()?->saldo_efectivo ?? 0;
    }

    #[Computed]
    public function saldoTransferencia()
    {
        return Caja::first()?->saldo_transferencia ?? 0;
    }

    public function almacenar()
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $caja = Caja::lockForUpdate()->firstOrFail();
                $columna = $this->metodo === 'Efectivo' ? 'saldo_efectivo' : 'saldo_transferencia';

                if ($this->metodo === 'Efectivo' && $this->monto > $caja->saldo_efectivo) {
                    throw new \Exception('Saldo insuficiente en caja.', 1001);
                }

                $caja->decrement($columna, $this->monto);

                Egreso::create([
                    'metodo' => $this->metodo,
                    'monto' => $this->monto,
                    'motivo' => $this->motivo,
                    'id_profesional' => $this->idProfesional,
                ]);
            });

            return redirect()->route('movimientos')->with('exito', '¡Egreso registrado con éxito!');

        } catch (\Throwable $th) {
            $mensajeError = $th->getCode() === 1001
                ? 'El saldo de la caja fue modificado. Verificá el nuevo monto disponible.'
                : 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).';

            Log::error('[(Livewire) egresos.crear@almacenar] Error al almacenar el egreso.', ['excepción' => $th->getMessage()]);
            session()->flash('error', $mensajeError);
        }
    }

    protected function obtenerMontoParaEnviar($montoStr)
    {
        if (!is_string($montoStr) || trim($montoStr) === '') {
            return 0.0;
        }

        $limpio = str_replace(['.', ','], ['', '.'], $montoStr);
        return (float) $limpio;
    }
};
?>

<div class="contenedor max-w-4xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <h2 class="titulo-formulario">Registrar un nuevo Egreso</h2>

            <div class="flex gap-3">
                <div class="p-4 flex flex-col items-end bg-gray-800 border border-gray-700 rounded-lg shadow-inner">
                    <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Transferencias recibidas</span>
                    <span class="text-emerald-400 text-3xl font-bold">
                        ${{ number_format($this->saldoTransferencia, 2, ',', '.') }}
                    </span>
                </div>

                <div class="p-4 flex flex-col items-end bg-gray-800 border border-gray-700 rounded-lg shadow-inner">
                    <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Saldo Total en Caja</span>
                    <span class="text-emerald-400 text-3xl font-bold">
                        ${{ number_format($this->saldoEfectivo, 2, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="id-profesional" class="etiqueta-formulario">Profesional que lo realiza</label>
                <select
                    id="id-profesional"
                    class="entrada @error('idProfesional') border-red-500 @enderror"
                    wire:model="idProfesional">
                    <option value="">Seleccione un profesional</option>

                    @foreach($profesionales as $prof)
                        <option value="{{ $prof->id }}">
                            {{ $prof->apellido }}, {{ $prof->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('idProfesional') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="motivo" class="etiqueta-formulario">En concepto de</label>
                <input
                    id="motivo"
                    type="text"
                    placeholder="Ejemplo: Compra de insumo"
                    class="entrada @error('motivo') border-red-500 @enderror"
                    wire:model="motivo">
                @error('motivo') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="metodo-select" class="etiqueta-formulario">Método</label>
                <select
                    id="metodo-select"
                    @class([
                        'entrada',
                        'border-red-500 border-2' => $errors->has('metodo'),
                    ])
                    wire:model.live="metodo"
                    required
                >
                    <option value="" disabled @selected($metodo === '')>Seleccione un método</option>
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                </select>
                @error('metodo') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="monto" class="etiqueta-formulario">Monto del egreso</label>
                <input
                    id="monto"
                    type="text"
                    placeholder="Ejemplo: 75000,00"
                    class="entrada @error('monto') border-red-500 @enderror"
                    wire:model.live="montoStr"
                    x-on:input="$wire.$js.transformarIngresoMonto($el)">
                @error('monto') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Registrar Egreso</button>
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
