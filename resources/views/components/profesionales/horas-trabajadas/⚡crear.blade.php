<?php

use App\Models\Profesional;
use App\Models\RegistroHoras;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

new class extends Component
{
    public Collection $profesionales;

    public $cantidad_horas = 1;
    public $total_a_cobrar = 0;
    public $fecha_trabajada = '';
    public $id_profesional = '';
    public $codigo_personal = '';

    protected $rules = [
        'cantidad_horas' => 'required|integer|min:1|max:8',
        'fecha_trabajada' => 'required|date',
        'id_profesional' => 'required|exists:profesionales,id',
        'codigo_personal' => 'required|numeric|digits:5'
    ];

    public function mount()
    {
        $this->fecha_trabajada = Carbon::now()->toDateString();

        $this->profesionales = Profesional::where('activo', true)
            ->orderByDesc('nombre')
            ->get();
    }

    public function almacenar()
    {
        $this->validate();

        DB::beginTransaction();
        try {
            $profesional = Profesional::where('id', $this->id_profesional)
                ->where('codigo_personal', $this->codigo_personal)
                ->first();

            if (!$profesional) {
                $this->addError('codigo_personal', 'El código ingresado no coincide con el profesional seleccionado.');
                return;
            }

            $valorPorHora = $profesional->valor_por_hora;
            $this->total_a_cobrar = $valorPorHora * $this->cantidad_horas;

            RegistroHoras::create([
                'valor_hora_profesional' => $valorPorHora,
                'cantidad_horas' => $this->cantidad_horas,
                'total_a_cobrar' => $this->total_a_cobrar,
                'fecha_trabajada' => $this->fecha_trabajada,
                'id_profesional' => $this->id_profesional
            ]);

            DB::commit();
            session()->flash('exito', 'Horas registradas con éxito. Monto a cobrar: $' . number_format($this->total_a_cobrar, 2));

            $this->reset(['cantidad_horas', 'total_a_cobrar', 'id_profesional', 'codigo_personal']);
            $this->fecha_trabajada = Carbon::now()->toDateString();

        } catch (\Throwable $ex) {
            if ($ex->errorInfo[1] == 1062) {
                $mensajeError = 'Este profesional ya tiene horas registradas para la fecha seleccionada.';
            } else {
                $mensajeError = 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).';
            }

            DB::rollBack();
            Log::error('[(Livewire) profesionales.horas-trabajadas.crear@almacenar] Error al registrar las horas trabajadas.', ['excepción' => $ex->getMessage()]);

            session()->flash('error', $mensajeError);
        }
    }
};
?>

<div class="contenedor max-w-4xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <h2 class="titulo-formulario">Registrar horas trabajadas</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="id-profesional" class="etiqueta-formulario">Seleccione la opción con su nombre</label>
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
                @error('id_profesional') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="codigo_personal" class="etiqueta-formulario">Código Personal</label>
                <input
                    id="codigo_personal"
                    type="password"
                    maxlength="5"
                    placeholder="Ingrese su código personal"
                    class="entrada @error('codigo_personal') border-red-500 @enderror"
                    wire:model="codigo_personal">
                @error('codigo_personal') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="cantidad-horas" class="etiqueta-formulario">Horas trabajadas</label>
                <input
                    id="cantidad-horas"
                    type="number"
                    min="1"
                    max="8"
                    class="entrada @error('cantidad_horas') border-red-500 @enderror"
                    wire:model="cantidad_horas">
                @error('cantidad_horas') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="fecha-trabajada" class="etiqueta-formulario">Fecha trabajada</label>
                <input
                    id="fecha-trabajada"
                    type="date"
                    class="entrada @error('fecha_trabajada') border-red-500 @enderror"
                    wire:model="fecha_trabajada">
                @error('fecha_trabajada') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Registrar Horas</button>
    </form>
</div>
