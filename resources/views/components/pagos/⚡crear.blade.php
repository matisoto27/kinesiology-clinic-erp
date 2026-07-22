<?php

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Caja;
use App\Models\Pago;
use App\Models\Profesional;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public Collection $profesionales;

    public string $filtroActividad = '';
    public string $busquedaInscripcion = '';
    public string $idActPac = '';
    public string $idProfesional = '';
    public string $montoStr = '';
    public $monto;
    public string $metodo = '';

    protected function rules()
    {
        return [
            'idActPac' => 'required|exists:actividades_pacientes,id',
            'idProfesional' => 'required|exists:profesionales,id',
            'monto' => [
                'required',
                'numeric',
                'gt:0',
                'lte:' . $this->deudaActual,
            ],
            'metodo' => 'required|in:Efectivo,Transferencia',
        ];
    }

    protected function messages()
    {
        return [
            'monto.lte' => 'El monto ingresado no puede superar la deuda total.',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'idActPac' => 'inscripción',
            'idProfesional' => 'profesional',
            'metodo' => 'método de pago',
            'monto' => 'monto',
        ];
    }

    public function mount($id = null)
    {
        $this->profesionales = Profesional::activo()
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellido']);

        if ($id !== null && $this->pendientesDePago->contains('id', (int) $id)) {
            $this->idActPac = (string) $id;
        }
    }

    public function seleccionarInscripcion(int $id): void
    {
        $this->idActPac = (string) $id;
        $this->busquedaInscripcion = '';
        $this->resetMontoYValidacion();
    }

    public function limpiarInscripcion(): void
    {
        $this->idActPac = '';
        $this->busquedaInscripcion = '';
        $this->resetMontoYValidacion();
    }

    private function resetMontoYValidacion(): void
    {
        $this->montoStr = '';
        $this->monto = 0;
        $this->resetValidation(['monto', 'idActPac']);
    }

    public function updatedFiltroActividad(): void
    {
        if ($this->idActPac === '') {
            $this->busquedaInscripcion = '';

            return;
        }

        if (!$this->pendientesDePago->contains('id', (int) $this->idActPac)) {
            $this->limpiarInscripcion();
        }
    }

    public function updatedBusquedaInscripcion(): void
    {
        $this->resetValidation('idActPac');
    }

    public function updatedMontoStr($value)
    {
        $this->monto = $this->obtenerMontoParaEnviar($value);

        if ($this->idActPac !== '') {
            $this->validateOnly('monto');
        }
    }

    #[Computed]
    public function pendientesDePago()
    {
        return ActividadPaciente::with(['actividad', 'pacienteRegular', 'pacienteCasual', 'primerTurno'])
            ->withSum('pagos', 'monto')
            ->sinPagar()
            ->when($this->filtroActividad === 'gimnasio', fn ($consulta) => $consulta
                ->where('id_actividad', Actividad::GIMNASIO))
            ->when($this->filtroActividad === 'pilates', fn ($consulta) => $consulta
                ->where('id_actividad', Actividad::PILATES))
            ->when($this->filtroActividad === 'kinesiologia', fn ($consulta) => $consulta
                ->whereHas('actividad', fn ($subconsulta) => $subconsulta
                    ->where('id_tipo_actividad', Actividad::TIPO_KINESIOLOGIA)))
            ->get()
            ->sortBy(fn ($ap) => $ap->primerTurno?->fecha_hora ?? now()->addYears(100));
    }

    #[Computed]
    public function inscripcionSeleccionada(): ?ActividadPaciente
    {
        if ($this->idActPac === '') {
            return null;
        }

        return $this->pendientesDePago->firstWhere('id', (int) $this->idActPac);
    }

    #[Computed]
    public function etiquetaInscripcionSeleccionada(): string
    {
        if (!$this->inscripcionSeleccionada) {
            return '';
        }

        return $this->formatearEtiquetaInscripcion($this->inscripcionSeleccionada);
    }

    #[Computed]
    public function sugerenciasInscripcion()
    {
        if ($this->idActPac !== '' || strlen(trim($this->busquedaInscripcion)) < 2) {
            return collect();
        }

        $termino = Str::lower(trim($this->busquedaInscripcion));

        return $this->pendientesDePago->filter(
            fn (ActividadPaciente $actPac) => Str::contains(Str::lower($actPac->ap_nom_paciente), $termino)
        );
    }

    #[Computed]
    public function deudaActual(): float
    {
        if ($this->idActPac === '') {
            return 0.0;
        }

        $inscripcion = $this->inscripcionSeleccionada;

        return $inscripcion ? (float) $inscripcion->deuda : 0.0;
    }

    #[Computed]
    public function puedeRegistrar(): bool
    {
        if ($this->idActPac === '' || $this->idProfesional === '' || $this->metodo === '') {
            return false;
        }

        $monto = $this->obtenerMontoParaEnviar($this->montoStr);

        return $monto > 0 && $monto <= $this->deudaActual;
    }

    public function almacenar()
    {
        if (!$this->puedeRegistrar) {
            return;
        }

        $this->monto = $this->obtenerMontoParaEnviar($this->montoStr);
        $this->validate();

        try {
            DB::transaction(function () {
                $inscripcion = ActividadPaciente::lockForUpdate()
                    ->with('actividad')
                    ->withSum('pagos', 'monto')
                    ->findOrFail($this->idActPac);
                $deudaActual = (float) $inscripcion->deuda;

                if ($this->monto > $deudaActual) {
                    throw ValidationException::withMessages([
                        'monto' => ['El monto ingresado ($' . number_format($this->monto, 2) . ') supera la deuda actual ($' . number_format($deudaActual, 2) . ').'],
                    ]);
                }

                $columna = $this->metodo === 'Efectivo' ? 'saldo_efectivo' : 'saldo_transferencia';
                Caja::lockForUpdate()->firstOrFail()->increment($columna, $this->monto);

                Pago::create([
                    'id_act_pac' => $this->idActPac,
                    'id_profesional' => $this->idProfesional,
                    'metodo' => $this->metodo,
                    'monto' => $this->monto,
                ]);

                $inscripcion->loadSum('pagos', 'monto'); // Luego de crear el pago, la deuda va a disminuir

                if ($inscripcion->deuda <= 0) {
                    $inscripcion->update(['pago_completado' => true]);
                }
            });

            return redirect()->route('movimientos')->with('exito', '¡El pago ha sido registrado con éxito!');

        } catch (ValidationException $ex) {
            throw $ex;
        } catch (\Throwable $th) {
            Log::error('[(Livewire) pagos.crear@almacenar] Error al almacenar el pago', [
                'id_act_pac' => $this->idActPac,
                'excepción' => $th->getMessage(),
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
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

    public function formatearEtiquetaInscripcion(ActividadPaciente $actPac): string
    {
        if ($actPac->esPruebaPilates()) {
            return sprintf(
                '[Turno: %s] Prueba de Pilates - %s',
                $actPac->primerTurno->fecha_hora->format('d/m/Y'),
                $actPac->ap_nom_paciente
            );
        }

        return sprintf(
            '[1er Turno: %s] %s - %s',
            $actPac->primerTurno->fecha_hora->format('d/m/Y'),
            $actPac->nombre_actividad,
            $actPac->ap_nom_paciente
        );
    }
};
?>

<div class="contenedor max-w-5xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <h2 class="titulo-formulario">Pago de una actividad</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="filtro-actividad" class="etiqueta-formulario">Filtrar por Actividad</label>
                <select id="filtro-actividad" class="entrada mb-4" wire:model.live="filtroActividad">
                    <option value="">Todas</option>
                    <option value="gimnasio">Gimnasio</option>
                    <option value="pilates">Pilates</option>
                    <option value="kinesiologia">Kinesiología</option>
                </select>

                <div class="flex items-center gap-1">
                    <label for="act-pac-buscar" class="etiqueta-formulario">Actividad contratada</label>
                    @if($idActPac !== '')
                        <button type="button" class="cursor-pointer text-red-600 hover:text-red-900" wire:click="limpiarInscripcion">
                            <x-iconos.cruz />
                        </button>
                    @endif
                </div>

                <div @class([
                    'buscador',
                    'bg-[#6BA9A9]' => $idActPac !== '',
                    'rounded-b-xl' => $idActPac !== '' || strlen($busquedaInscripcion) < 2,
                    'rounded-b-none' => $idActPac === '' && strlen($busquedaInscripcion) >= 2,
                ])>
                    <div class="flex items-center">
                        @if($idActPac === '')
                            <x-iconos.lupa class="ml-3 shrink-0" />
                        @endif
                        <input
                            id="act-pac-buscar"
                            type="text"
                            autocomplete="off"
                            placeholder="Ingrese nombre y/o apellido del paciente"
                            wire:model.live.debounce.300ms="busquedaInscripcion"
                            @disabled($idActPac !== '')
                            @class(['border-red-500 border-2' => $errors->has('idActPac')])
                        >
                    </div>

                    @if(strlen($busquedaInscripcion) >= 2 && $idActPac === '')
                        <ul class="sugerencias" wire:click.outside="$set('busquedaInscripcion', '')">
                            @forelse($this->sugerenciasInscripcion as $indice => $actPac)
                                <li
                                    wire:key="inscripcion-{{ $actPac->id }}"
                                    wire:click="seleccionarInscripcion({{ $actPac->id }})"
                                    @class([
                                        'p-2 bg-white hover:bg-[#F5D500] text-black text-left cursor-pointer text-sm',
                                        'rounded-b-md' => $indice === ($this->sugerenciasInscripcion->count() - 1),
                                    ])
                                >
                                    {{ $this->formatearEtiquetaInscripcion($actPac) }}
                                </li>
                            @empty
                                <li class="p-2 flex items-center bg-white text-gray-500 text-left rounded-b-md text-sm">
                                    <x-iconos.circulo-informacion />
                                    <span class="ml-1">Sin coincidencias</span>
                                </li>
                            @endforelse
                        </ul>
                    @endif
                </div>

                @error('idActPac') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror

                @if($this->inscripcionSeleccionada)
                    <div class="mt-3 p-3 bg-[#014745]/60 border border-white/20 rounded-lg">
                        <p class="text-white text-sm">{{ $this->etiquetaInscripcionSeleccionada }}</p>
                        <div class="flex font-semibold italic text-yellow-300 mt-2">
                            <p class="text-lg">Deuda total del paciente: $</p>
                            <p class="text-xl">{{ number_format($this->deudaActual, 2, ',', '.') }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="columna-campo flex-1">
                <label for="profesional-select" class="etiqueta-formulario">Profesional que lo registra</label>
                <select
                    id="profesional-select"
                    @class([
                        'entrada',
                        'border-red-500 border-2' => $errors->has('idProfesional'),
                    ])
                    wire:model.live="idProfesional"
                    required
                >
                    <option value="" disabled @selected($idProfesional === '')>Seleccione un profesional</option>
                    @foreach($profesionales as $profesional)
                        <option value="{{ $profesional->id }}">{{ $profesional->apellido }}, {{ $profesional->nombre }}</option>
                    @endforeach
                </select>
                @error('idProfesional') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario pb-4">
            <div class="columna-campo flex-1">
                <label for="monto-input" class="etiqueta-formulario">Monto abonado</label>
                <input
                    id="monto-input"
                    type="text"
                    placeholder="Ejemplo: 75000,00"
                    @class([
                        'entrada',
                        'border-red-500 border-2' => $errors->has('monto'),
                    ])
                    wire:model.live="montoStr"
                    x-on:input="$wire.$js.transformarIngresoMonto($el)"
                    @disabled($idActPac === '')
                    required
                >
                @error('monto') <p class="alerta">{{ $message }}</p> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="metodo-select" class="etiqueta-formulario">Método de pago</label>
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
        </div>

        <button
            type="submit"
            class="boton-registrar"
            @disabled(!$this->puedeRegistrar)
            wire:loading.attr="disabled"
        >
            Registrar pago
        </button>
    </form>
</div>

<script>
    this.$js.transformarIngresoMonto = (input) => {
        let valorIngresado = input.value;

        valorIngresado = valorIngresado.replace(/\./g, '').replace(/[^0-9,]/g, '');

        if (valorIngresado.startsWith(',')) valorIngresado = '0' + valorIngresado;

        let partes = valorIngresado.split(',');
        let parteEntera = partes[0];
        let parteDecimal = partes.length > 1 ? partes.slice(1).join('') : null;

        if (parteEntera.length > 0) {
            parteEntera = parseInt(parteEntera, 10).toString().substring(0, 7);
            parteEntera = parteEntera.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        input.value = partes.length > 1
            ? parteEntera + ',' + parteDecimal.substring(0, 2)
            : parteEntera + (valorIngresado.includes(',') ? ',' : '');
    }
</script>
