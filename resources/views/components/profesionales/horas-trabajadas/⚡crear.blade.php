<?php

use App\Enums\RubroHorasProfesional;
use App\Exceptions\ReglaNegocioException;
use App\Models\Profesional;
use App\Services\RegistroHorasService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Collection $profesionales;

    public int $cantidadHoras = 1;
    public string $fechaTrabajada = '';
    public ?int $idProfesional = null;
    public string $rubro = '';

    protected function rules()
    {
        return [
            'cantidadHoras' => 'required|integer|min:1|max:8',
            'fechaTrabajada' => 'required|date',
            'idProfesional' => 'required|exists:profesionales,id',
            'rubro' => ['required', Rule::enum(RubroHorasProfesional::class)],
        ];
    }

    public function mount()
    {
        $this->fechaTrabajada = Carbon::now()->toDateString();

        $this->profesionales = Profesional::where('activo', true)
            ->orderByDesc('nombre')
            ->get();
    }

    #[Computed]
    public function totalEstimado(): int
    {
        $rubro = RubroHorasProfesional::tryFrom($this->rubro);

        if ($rubro === null || $this->cantidadHoras < 1) {
            return 0;
        }

        return app(RegistroHorasService::class)->valorHora($rubro) * (int) $this->cantidadHoras;
    }

    public function almacenar(RegistroHorasService $registroHorasService)
    {
        $this->validate();

        try {
            $profesional = Profesional::find($this->idProfesional);

            if (!$profesional) {
                $this->addError('idProfesional', 'El profesional seleccionado no existe.');
                return;
            }

            $rubro = RubroHorasProfesional::from($this->rubro);

            $registro = $registroHorasService->registrar(
                $profesional,
                $rubro,
                (int) $this->cantidadHoras,
                Carbon::parse($this->fechaTrabajada)
            );

            session()->flash(
                'exito',
                'Horas registradas con éxito. Monto a cobrar: $' . number_format((float) $registro->total_a_cobrar, 2)
            );

            $this->reset(['cantidadHoras', 'idProfesional', 'rubro']);
            $this->cantidadHoras = 1;
            $this->fechaTrabajada = Carbon::now()->toDateString();
        } catch (ReglaNegocioException $ex) {
            session()->flash('error', $ex->getMessage());
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) profesionales.horas-trabajadas.crear@almacenar] Error al registrar las horas trabajadas.', ['excepción' => $ex->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
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
                    class="entrada @error('idProfesional') border-red-500 @enderror"
                    wire:model="idProfesional">
                    <option value="">Seleccione un profesional</option>
                    @foreach($profesionales as $prof)
                        <option value="{{ $prof->id }}">
                            {{ $prof->apellido }}, {{ $prof->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('idProfesional') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="rubro" class="etiqueta-formulario">Actividad</label>
                <select
                    id="rubro"
                    class="entrada @error('rubro') border-red-500 @enderror"
                    wire:model.live="rubro">
                    <option value="">Seleccione una actividad</option>
                    @foreach(RubroHorasProfesional::cases() as $opcion)
                        <option value="{{ $opcion->value }}">{{ $opcion->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('rubro') <span class="text-red-500 italic">{{ $message }}</span> @enderror
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
                    class="entrada @error('cantidadHoras') border-red-500 @enderror"
                    wire:model.live="cantidadHoras">
                @error('cantidadHoras') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="fecha-trabajada" class="etiqueta-formulario">Fecha trabajada</label>
                <input
                    id="fecha-trabajada"
                    type="date"
                    class="entrada @error('fechaTrabajada') border-red-500 @enderror"
                    wire:model="fechaTrabajada">
                @error('fechaTrabajada') <span class="text-red-500 italic">{{ $message }}</span> @enderror
            </div>
        </div>

        @if($rubro !== '' && $cantidadHoras >= 1)
            <div class="mb-5">
                <p class="text-gray-300 text-sm">
                    Total estimado:
                    <span class="text-emerald-400 font-semibold">${{ number_format($this->totalEstimado, 0, ',', '.') }}</span>
                </p>
            </div>
        @endif

        <button type="submit" class="boton-registrar" wire:loading.attr="disabled">Registrar Horas</button>
    </form>
</div>
