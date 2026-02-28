<?php

use App\Models\Profesional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public $profesional;

    public $dni, $nombre, $apellido, $codigo_personal, $activo;
    public $valorPorHoraStr = '';

    public function mount(Profesional $profesional)
    {
        $this->profesional = $profesional;

        $this->dni = $profesional->dni;
        $this->nombre = $profesional->nombre;
        $this->apellido = $profesional->apellido;
        $this->valorPorHoraStr = number_format($profesional->valor_por_hora, 0, '', '.');
        $this->codigo_personal = $profesional->codigo_personal;
        $this->activo = $profesional->activo ? 1 : 0;
    }

    public function actualizar()
    {
        $this->validate([
            'nombre' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'apellido' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'valorPorHoraStr' => 'required',
            'codigo_personal' => 'required|numeric|digits:5',
            'activo' => 'required|boolean'
        ]);

        $valorPorHora = $this->obtenerValorNumerico($this->valorPorHoraStr);

        DB::beginTransaction();
        try {
            $this->profesional->update([
                'nombre' => trim($this->nombre),
                'apellido' => trim($this->apellido),
                'codigo_personal' => $this->codigo_personal,
                'valor_por_hora' => $valorPorHora,
                'activo' => $this->activo
            ]);
            DB::commit();

            session()->flash('exito', '¡Profesional actualizado con éxito!');
            return redirect()->route('profesionales.inicio');

        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error('[(Livewire) profesionales.editar@actualizar] Error al actualizar el profesional.', ['excepción' => $ex->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function obtenerValorNumerico($valorStr)
    {
        $limpio = str_replace('.', '', (string) $valorStr);
        return (int) $limpio;
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div class="contenedor max-w-4xl">
    <form class="formulario" wire:submit.prevent="actualizar">
        <h2 class="titulo-formulario">Actualizar datos del profesional</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="input-dni" class="etiqueta-formulario">DNI</label>
                <input id="input-dni" type="text" class="entrada" wire:model="dni" disabled>
            </div>
            <div class="columna-campo flex-1">
                <label for="input-codigo" class="etiqueta-formulario">Código personal (5 dígitos)</label>
                <input id="input-codigo" type="text" maxlength="5" class="entrada" wire:model="codigo_personal">
                @error('codigo_personal') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="input-nombre" class="etiqueta-formulario">Nombre/s</label>
                <input id="input-nombre" type="text" class="entrada" wire:model="nombre">
                @error('nombre') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
            <div class="columna-campo flex-1">
                <label for="input-apellido" class="etiqueta-formulario">Apellido</label>
                <input id="input-apellido" type="text" class="entrada" wire:model="apellido">
                @error('apellido') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="input-valor" class="etiqueta-formulario">Valor por hora</label>
                <input
                    id="input-valor"
                    type="text"
                    class="entrada"
                    wire:model="valorPorHoraStr"
                    x-on:input="$wire.$js.transformarValorPorHora($el)">
                @error('valorPorHoraStr') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
            <div class="columna-campo flex-1">
                <label for="select-estado" class="etiqueta-formulario">Estado</label>
                <select id="select-estado" class="entrada" wire:model.number="activo">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>
        </div>

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Guardar Cambios</button>
    </form>
</div>

<script>
    this.$js.transformarValorPorHora = (input) => {
        let valorIngresado = input.value;

        // Eliminar cualquier cosa que no sea un número
        valorIngresado = valorIngresado.replace(/[^0-9]/g, '');

        if (valorIngresado.length > 0) {
            // Convertir a entero para eliminar ceros a la izquierda y limitar a 7 dígitos
            let numeroLimpio = parseInt(valorIngresado, 10).toString().substring(0, 7);

            // Formatear con puntos de miles
            input.value = numeroLimpio.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        } else {
            input.value = '';
        }
    }
</script>
