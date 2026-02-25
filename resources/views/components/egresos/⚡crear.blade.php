<?php

use App\Models\Egreso;
use App\Models\Profesional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public Collection $profesionales;

    public $metodo = '';
    public $montoStr = '';
    public $monto;
    public $motivo;
    public $id_profesional;

    protected $rules = [
        'metodo' => 'required|in:Efectivo,Transferencia',
        'monto' => 'required|numeric|gt:0',
        'motivo' => 'required|string|max:255',
        'id_profesional' => 'required|exists:profesionales,id'
    ];

    protected $messages = [
        'metodo.required' => 'Por favor, seleccione un método de pago.',
        'monto.required' => 'El monto es obligatorio.',
        'monto.numeric' => 'El monto debe ser un valor numérico.',
        'motivo.required' => 'El motivo del egreso es obligatorio.',
        'id_profesional.required' => 'Por favor, seleccione un profesional.'
    ];

    public function mount()
    {
        $this->profesionales = Profesional::where('activo', true)
            ->orderByDesc('nombre')
            ->get();
    }

    public function almacenar()
    {
        DB::beginTransaction();

        try {
            $this->monto = $this->obtenerMontoParaEnviar($this->montoStr);
            $this->validate();

            Egreso::create([
                'metodo' => $this->metodo,
                'monto' => $this->monto,
                'motivo' => $this->motivo,
                'id_profesional' => $this->id_profesional
            ]);

            DB::commit();
            session()->flash('mensaje', '¡Egreso registrado con éxito!');
            return redirect()->route('movimientos');

        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error('[(Livewire) egresos.crear@almacenar] Error al almacenar el egreso.', ['excepción' => $ex->getMessage()]);

            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function obtenerMontoParaEnviar($montoStr)
    {
        if (!is_string($montoStr) || trim($montoStr) === '') {
            return 0.0;
        }

        $limpio = str_replace(['.', ','], ['', '.'], $montoStr);
        $transformado = (float) $limpio;

        return is_nan($transformado) ? 0.0 : $transformado;
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div class="contenedor max-w-4xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <h2 class="titulo-formulario">Registrar un nuevo Egreso</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="id-profesional" class="etiqueta-formulario">Profesional que lo realiza</label>
                <select
                    id="id-profesional"
                    class="entrada @error('id_profesional') border-red-500 @enderror"
                    wire:model="id_profesional">
                    <option value="">Seleccione un profesional</option>

                    @foreach($profesionales as $prof)
                        <option value="{{ $prof->id }}">
                            {{ $prof->apellido }}, {{ $prof->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('id_profesional') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
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
                <label for="monto" class="etiqueta-formulario">Monto del egreso</label>
                <input
                    id="monto"
                    type="text"
                    placeholder="Ejemplo: 75000,00"
                    class="entrada @error('monto') border-red-500 @enderror"
                    wire:model="montoStr"
                    x-on:input="$wire.$js.transformarIngresoMonto($el)">
                @error('monto') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="metodo" class="etiqueta-formulario">Método de pago</label>
                <select
                    id="metodo"
                    class="entrada @error('metodo') border-red-500 @enderror"
                    wire:model="metodo">
                    <option value="">Seleccione un método</option>
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                </select>
                @error('metodo') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
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
