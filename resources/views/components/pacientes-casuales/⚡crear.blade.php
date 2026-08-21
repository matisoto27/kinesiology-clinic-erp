<?php

use App\Exceptions\ReglaNegocioException;
use App\Livewire\Forms\PacienteCasualForm;
use App\Models\PacienteCasual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public PacienteCasualForm $form;

    public function almacenar()
    {
        $this->form->validate();
        $datos = $this->form->transformarDatos();

        try {
            $paciente = $this->registrarORestaurar($datos);

            $mensaje = $paciente->wasRecentlyCreated
                ? '¡Paciente registrado con éxito!'
                : 'Se restauró el paciente casual eliminado previamente con ese teléfono.';

            return redirect()->route('pacientes-casuales.inicio')->with('exito', $mensaje);
        } catch (ReglaNegocioException $ex) {
            $this->addError('form.telefono', $ex->getMessage());
        } catch (\Throwable $th) {
            Log::error('[(Livewire) pacientes-casuales.crear@almacenar] Error al registrar el paciente.', ['excepción' => $th->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    /**
     * @param  array{nombre: string, apellido: string, telefono: string}  $datos
     */
    private function registrarORestaurar(array $datos): PacienteCasual
    {
        return DB::transaction(function () use ($datos) {
            $existente = PacienteCasual::withTrashed()
                ->where('telefono', $datos['telefono'])
                ->lockForUpdate()
                ->first();

            if ($existente === null) {
                return PacienteCasual::create($datos);
            }

            if (!$existente->trashed()) {
                throw new ReglaNegocioException('Ya existe un paciente casual con ese teléfono.');
            }

            $existente->restore();
            $existente->update([
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
            ]);

            return $existente->fresh();
        });
    }
};
?>

<div class="contenedor max-w-xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <h2 class="titulo-formulario">Registrar nuevo Paciente Casual</h2>

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

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Registrar</button>
    </form>
</div>
