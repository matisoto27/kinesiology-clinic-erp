<?php

use App\Models\Profesional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public Profesional $profesional;

    public string $dni = '';
    public string $nombre = '';
    public string $apellido = '';
    public string $activo = '1';

    public function mount(Profesional $profesional)
    {
        $this->profesional = $profesional;

        $this->dni = $profesional->dni;
        $this->nombre = $profesional->nombre;
        $this->apellido = $profesional->apellido;
        $this->activo = $profesional->activo ? '1' : '0';
    }

    public function actualizar()
    {
        $this->validate([
            'nombre' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'apellido' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
            'activo' => 'required|boolean',
        ]);

        try {
            DB::transaction(function () {
                $this->profesional->update([
                    'nombre' => trim($this->nombre),
                    'apellido' => trim($this->apellido),
                    'activo' => (bool) (int) $this->activo,
                ]);
            });

            session()->flash('exito', '¡Profesional actualizado con éxito!');
            return redirect()->route('profesionales.inicio');

        } catch (\Throwable $ex) {
            Log::error('[(Livewire) profesionales.editar@actualizar] Error al actualizar el profesional.', ['excepción' => $ex->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
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
                <label for="select-estado" class="etiqueta-formulario">Estado</label>
                <select id="select-estado" class="entrada" wire:model="activo">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>
        </div>

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Guardar Cambios</button>
    </form>
</div>
