<?php

use App\Models\PacienteCasual;
use App\Livewire\Forms\PacienteCasualForm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public PacienteCasualForm $form;

    public function mount(PacienteCasual $paciente)
    {
        $this->form->establecerPaciente($paciente);
    }

    public function actualizar()
    {
        $this->form->validate();
        $datos = $this->form->transformarDatos();

        try {
            DB::transaction(function () use ($datos) {
                $this->form->paciente->update($datos);
            });

            return redirect()->route('pacientes-casuales.inicio')->with('exito', '¡Datos del paciente actualizados con éxito!');
        } catch (\Throwable $th) {
            Log::error('[(Livewire) pacientes-casuales.editar@actualizar] Error al actualizar los datos del paciente.', ['excepción' => $th->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor max-w-xl">
    <form class="formulario" wire:submit.prevent="actualizar">
        <h2 class="titulo-formulario">Editar datos de Paciente Casual</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="mb-5 grid grid-cols-1 gap-y-5">
            <div class="columna-campo">
                <label for="input-nombre" class="etiqueta-formulario">Nombre/s</label>
                <input
                    id="input-nombre"
                    type="text"
                    placeholder="Ingrese nombre del paciente"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('form.nombre')
                    ])
                    wire:model="form.nombre">
                @error('form.nombre') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo">
                <label for="input-apellido" class="etiqueta-formulario">Apellido/s</label>
                <input
                    id="input-apellido"
                    type="text"
                    placeholder="Ingrese apellido del paciente"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('form.apellido')
                    ])
                    wire:model="form.apellido">
                @error('form.apellido') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo">
                <label for="input-telefono" class="etiqueta-formulario">Teléfono</label>
                <input
                    id="input-telefono"
                    type="text"
                    placeholder="Ingrese teléfono del paciente"
                    @class([
                        'entrada-simple',
                        'border-red-500 border-2' => $errors->has('form.telefono')
                    ])
                    wire:model="form.telefono">
                @error('form.telefono') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Guardar Cambios</button>
    </form>
</div>
