<?php

use App\Models\Profesional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public $dni;
    public $nombre;
    public $apellido;
    public $codigo_personal;

    protected $rules = [
        'dni' => 'required|unique:profesionales,dni|numeric|digits_between:7,8',
        'nombre' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
        'apellido' => 'required|regex:/^[A-Za-záéíóúÁÉÍÓÚñÑ\s]+$/|max:30',
        'codigo_personal' => 'required|numeric|digits:5'
    ];

    protected $messages = [
        'dni.required' => 'El DNI es obligatorio.',
        'dni.unique' => 'Ya existe un profesional registrado con este DNI.',
        'nombre.required' => 'El nombre es obligatorio.',
        'apellido.required' => 'El apellido es obligatorio.',
        'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
        'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
        'codigo_personal.required' => 'El código personal es obligatorio.',
        'codigo_personal.digits' => 'El código debe ser de exactamente 5 dígitos.'
    ];

    public function almacenar()
    {
        $this->validate();

        DB::beginTransaction();
        try {
            Profesional::create([
                'dni' => $this->dni,
                'nombre' => $this->nombre,
                'apellido' => $this->apellido,
                'codigo_personal' => $this->codigo_personal
            ]);
            DB::commit();

            return redirect()->route('profesionales.inicio')->with('exito', '¡Profesional registrado con éxito!');

        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error('[(Livewire) profesionales.crear@almacenar] Error al registrar el profesional.', ['excepción' => $ex->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor max-w-4xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <h2 class="titulo-formulario">Registrar un nuevo Profesional</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="dni" class="etiqueta-formulario">DNI (Sin puntos)</label>
                <input
                    id="dni"
                    type="text"
                    placeholder="Ejemplo: 35123456"
                    class="entrada @error('dni') border-red-500 @enderror"
                    wire:model="dni">
                @error('dni') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="codigo_personal" class="etiqueta-formulario">Código Personal (5 dígitos)</label>
                <input
                    id="codigo_personal"
                    type="password"
                    maxlength="5"
                    placeholder="Ejemplo: 12345"
                    class="entrada @error('codigo_personal') border-red-500 @enderror"
                    wire:model="codigo_personal">
                @error('codigo_personal') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="nombre" class="etiqueta-formulario">Nombre/s</label>
                <input
                    id="nombre"
                    type="text"
                    placeholder="Ingrese nombre"
                    class="entrada @error('nombre') border-red-500 @enderror"
                    wire:model="nombre">
                @error('nombre') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="apellido" class="etiqueta-formulario">Apellido</label>
                <input
                    id="apellido"
                    type="text"
                    placeholder="Ingrese apellido"
                    class="entrada @error('apellido') border-red-500 @enderror"
                    wire:model="apellido">
                @error('apellido') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Registrar Profesional</button>
    </form>
</div>
