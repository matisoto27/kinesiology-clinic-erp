<?php

use App\Models\ActividadPaciente;
use App\Models\Caja;
use App\Models\Pago;
use App\Models\Profesional;
use Carbon\Carbon;
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
    private const DIAS_PAGOS_RECIENTES = 7;

    #[Locked]
    public Collection $profesionales;

    public string $busquedaInscripcion = '';
    public string $idActPac = '';
    public string $idProfesional = '';
    public string $metodo = '';
    public string $montoStr = '';
    public float $monto = 0.0;

    protected function rules()
    {
        return [
            'idActPac' => 'required|exists:actividades_pacientes,id',
            'idProfesional' => 'required|exists:profesionales,id',
            'metodo' => 'required|in:Efectivo,Transferencia',
            'monto' => [
                'required',
                'numeric',
                'gt:0',
                'lte:' . $this->deudaActual,
            ],
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
        $inscripciones = ActividadPaciente::query()
            ->with(['actividad', 'pacienteRegular', 'pacienteCasual', 'primerTurno', 'ultimoTurno'])
            ->withSum('pagos', 'monto')
            ->sinPagar()
            ->get();

        return ActividadPaciente::filtrarProximasPagables($inscripciones)
            ->sortBy(fn ($ap) => $ap->primerTurno?->fecha_hora);
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
    public function mesCicloSeleccionado(): string
    {
        $primer = $this->inscripcionSeleccionada?->primerTurno?->fecha_hora;

        if (!$primer) {
            return '';
        }

        return Str::upper($primer->translatedFormat('F Y'));
    }

    #[Computed]
    public function rangoCicloSeleccionado(): string
    {
        $inscripcion = $this->inscripcionSeleccionada;

        if (!$inscripcion?->primerTurno) {
            return '';
        }

        $inicio = $inscripcion->primerTurno->fecha_hora->format('d/m/Y');
        $fin = $inscripcion->ultimoTurno?->fecha_hora?->format('d/m/Y');

        return $fin ? "{$inicio} → {$fin}" : $inicio;
    }

    #[Computed]
    public function nombreActividadSeleccionada(): string
    {
        $inscripcion = $this->inscripcionSeleccionada;

        if (!$inscripcion) {
            return '';
        }

        if ($inscripcion->esPrueba()) {
            return 'Prueba de '.$inscripcion->nombre_actividad;
        }

        if ($inscripcion->esPrimeraDual() && $inscripcion->esDualCompleto()) {
            return sprintf('Gym/Pilates (x%d)', (int) $inscripcion->frecuencia_total_dual);
        }

        return (string) $inscripcion->nombre_actividad;
    }

    /**
     * @return Collection<int, Pago>
     */
    #[Computed]
    public function pagosRecientesPaciente(): Collection
    {
        $inscripcion = $this->inscripcionSeleccionada;

        if (!$inscripcion) {
            return collect();
        }

        return Pago::query()
            ->with([
                'profesional:id,nombre,apellido',
                'actividadPaciente:id,id_actividad,id_paciente,id_paciente_casual,frecuencia_total_dual,id_act_pac_dual',
                'actividadPaciente.actividad:id,nombre',
                'actividadPaciente.primerTurno',
            ])
            ->where('created_at', '>=', Carbon::now()->subDays(self::DIAS_PAGOS_RECIENTES))
            ->whereHas('actividadPaciente', function ($consulta) use ($inscripcion) {
                if ($inscripcion->id_paciente !== null) {
                    $consulta->where('id_paciente', $inscripcion->id_paciente);
                } else {
                    $consulta->where('id_paciente_casual', $inscripcion->id_paciente_casual);
                }
            })
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function advertenciaPagoReciente(): ?string
    {
        $pagos = $this->pagosRecientesPaciente;

        if ($pagos->isEmpty()) {
            return null;
        }

        $transferenciasHoy = $pagos->filter(
            fn (Pago $pago) => $pago->metodo === 'Transferencia' && $pago->created_at->isToday()
        );

        if ($transferenciasHoy->isNotEmpty()) {
            $pago = $transferenciasHoy->first();
            $ciclo = $pago->actividadPaciente?->primerTurno?->fecha_hora;

            return sprintf(
                'Ya hay una transferencia de $%s registrada hoy%s. Revisá que este comprobante no sea el mismo.',
                number_format((float) $pago->monto, 2, ',', '.'),
                $ciclo ? ' para el ciclo de '.Str::upper($ciclo->translatedFormat('F Y')) : ''
            );
        }

        $pagosHoy = $pagos->filter(fn (Pago $pago) => $pago->created_at->isToday());

        if ($pagosHoy->isNotEmpty()) {
            $pago = $pagosHoy->first();

            return sprintf(
                'Ya se registró un pago de $%s (%s) hoy para este paciente. Confirmá que no estés duplicando el mismo comprobante.',
                number_format((float) $pago->monto, 2, ',', '.'),
                $pago->metodo
            );
        }

        return null;
    }

    #[Computed]
    public function mensajeConfirmacionPago(): string
    {
        $inscripcion = $this->inscripcionSeleccionada;
        $monto = $this->obtenerMontoParaEnviar($this->montoStr);

        if (!$inscripcion?->primerTurno) {
            return '¿Confirmar el registro del pago?';
        }

        return sprintf(
            'Vas a imputar $%s a la inscripción de %s (inicia %s). ¿Continuar?',
            number_format($monto, 2, ',', '.'),
            $this->mesCicloSeleccionado,
            $inscripcion->primerTurno->fecha_hora->format('d/m/Y')
        );
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

                if (!$inscripcion->esProximaPagable()) {
                    throw ValidationException::withMessages([
                        'idActPac' => ['El paciente cuenta con una inscripción más reciente cuyo pago se encuentra incompleto.'],
                    ]);
                }

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
        $mesCiclo = $actPac->primerTurno
            ? Str::upper($actPac->primerTurno->fecha_hora->translatedFormat('F Y'))
            : '';

        if ($actPac->esPrueba()) {
            return sprintf(
                '%s · Prueba de %s · %s (turno %s)',
                $mesCiclo,
                $actPac->nombre_actividad,
                $actPac->ap_nom_paciente,
                $actPac->primerTurno->fecha_hora->format('d/m/Y')
            );
        }

        $nombreActividad = $actPac->esPrimeraDual() && $actPac->esDualCompleto()
            ? sprintf('Gym/Pilates (x%d)', (int) $actPac->frecuencia_total_dual)
            : $actPac->nombre_actividad;

        return sprintf(
            '%s · %s · %s (inicia %s)',
            $mesCiclo,
            $nombreActividad,
            $actPac->ap_nom_paciente,
            $actPac->primerTurno->fecha_hora->format('d/m/Y')
        );
    }

    public function formatearEtiquetaPagoReciente(Pago $pago): string
    {
        $actPac = $pago->actividadPaciente;
        $cuando = $pago->created_at->isToday()
            ? 'Hoy '.$pago->created_at->format('H:i')
            : $pago->created_at->format('d/m H:i');

        $ciclo = $actPac?->primerTurno
            ? Str::upper($actPac->primerTurno->fecha_hora->translatedFormat('F Y'))
            : 'sin ciclo';

        $actividad = $actPac?->esPrimeraDual() && $actPac->esDualCompleto()
            ? sprintf('Gym/Pilates (x%d)', (int) $actPac->frecuencia_total_dual)
            : ($actPac?->nombre_actividad ?? 'Actividad');

        $profesional = $pago->profesional
            ? trim($pago->profesional->apellido.', '.$pago->profesional->nombre)
            : '—';

        return sprintf(
            '%s · $%s · %s · %s · %s · %s',
            $cuando,
            number_format((float) $pago->monto, 2, ',', '.'),
            $pago->metodo,
            $ciclo,
            $actividad,
            $profesional
        );
    }
};
?>

<div class="contenedor max-w-xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <h2 class="titulo-formulario">Pago de una actividad</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
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
                    <div class="mt-3 p-3 bg-[#014745]/60 border border-white/20 rounded-lg space-y-2">
                        <p class="text-amber-300 text-2xl font-bold tracking-wide leading-tight">
                            {{ $this->mesCicloSeleccionado }}
                        </p>
                        <p class="text-white text-sm">
                            {{ $this->nombreActividadSeleccionada }}
                            · {{ $this->inscripcionSeleccionada->ap_nom_paciente }}
                        </p>
                        <p class="text-gray-300 text-sm">
                            Ciclo: {{ $this->rangoCicloSeleccionado }}
                        </p>
                        <div class="flex font-semibold italic text-yellow-300 pt-1">
                            <p class="text-lg">Saldo pendiente de esta inscripción: $</p>
                            <p class="text-xl">{{ number_format($this->deudaActual, 2, ',', '.') }}</p>
                        </div>
                    </div>

                    @if($this->advertenciaPagoReciente)
                        <div class="mt-3 p-3 bg-amber-950/80 border border-amber-400/50 rounded-lg">
                            <p class="text-amber-200 text-sm font-semibold">
                                {{ $this->advertenciaPagoReciente }}
                            </p>
                        </div>
                    @endif

                    @if($this->pagosRecientesPaciente->isNotEmpty())
                        <div class="mt-3 p-3 bg-black/25 border border-white/15 rounded-lg">
                            <p class="text-white text-sm font-medium mb-2">
                                Pagos de este paciente (últimos 7 días)
                            </p>
                            <ul class="space-y-1.5">
                                @foreach($this->pagosRecientesPaciente as $pagoReciente)
                                    <li
                                        wire:key="pago-reciente-{{ $pagoReciente->id }}"
                                        class="text-xs text-gray-200 leading-snug"
                                    >
                                        {{ $this->formatearEtiquetaPagoReciente($pagoReciente) }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="fila-formulario pb-4">
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
        </div>

        <button
            type="submit"
            class="boton-registrar"
            @disabled(!$this->puedeRegistrar)
            wire:loading.attr="disabled"
            wire:confirm="{{ $this->mensajeConfirmacionPago }}"
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
