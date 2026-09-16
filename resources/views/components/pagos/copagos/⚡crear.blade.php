<?php

use App\Exceptions\ReglaNegocioException;
use App\Models\ActividadPaciente;
use App\Models\Caja;
use App\Models\Pago;
use App\Models\Profesional;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

    public string $idActPac = '';
    public string $idProfesional = '';
    public string $montoStr = '';
    public float $monto = 0.0;
    public string $metodo = 'Efectivo';
    public bool $procesando = false;

    public function mount(): void
    {
        $this->profesionales = Profesional::select('id', 'nombre', 'apellido')->get();
        $this->montoStr = $this->formatearMonto((float) config('precios.copago'));
        $this->monto = (float) config('precios.copago');
    }

    #[Computed]
    public function actividadesPacientes()
    {
        $desde = Carbon::now()->subMonth()->startOfMonth();
        $hasta = Carbon::now()->addWeeks(2)->endOfDay();

        return ActividadPaciente::query()
            ->with([
                'actividad:id,nombre',
                'pacienteRegular:id,nombre,apellido',
                'primerTurno:turnos.id,turnos.id_act_pac,turnos.fecha_hora',
                'pagos:id,id_act_pac,es_copago',
            ])
            ->conOrdenMedica()
            ->whereHas('primerTurno', fn ($consulta) => $consulta->whereBetween('fecha_hora', [$desde, $hasta]))
            ->tienePacienteRegular()
            ->get()
            ->filter(fn (ActividadPaciente $actPac) => $actPac->puedeRegistrarCopago())
            ->sortByDesc(fn (ActividadPaciente $actPac) => $actPac->primerTurno->fecha_hora)
            ->values();
    }

    public function almacenar()
    {
        if ($this->procesando) {
            return;
        }

        $this->procesando = true;

        try {
            $this->monto = $this->obtenerMontoParaEnviar($this->montoStr);
            $this->validate([
                'idActPac' => 'required|exists:actividades_pacientes,id',
                'idProfesional' => 'required|exists:profesionales,id',
                'monto' => 'required|numeric|gt:0',
                'metodo' => 'required|in:Efectivo,Transferencia'
            ]);

            DB::transaction(function () {
                $registro = ActividadPaciente::query()
                    ->whereKey($this->idActPac)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$registro->puedeRegistrarCopago()) {
                    throw new ReglaNegocioException(sprintf(
                        'Ya se registraron todos los copagos permitidos para este registro (%d).',
                        $registro->cant_sesiones
                    ));
                }

                $columna = $this->metodo === 'Efectivo' ? 'saldo_efectivo' : 'saldo_transferencia';
                Caja::lockForUpdate()->firstOrFail()->increment($columna, $this->monto);

                Pago::create([
                    'id_act_pac' => $this->idActPac,
                    'id_profesional' => $this->idProfesional,
                    'metodo' => $this->metodo,
                    'monto' => $this->monto,
                    'es_copago' => true
                ]);
            });

            return redirect()->route('movimientos')->with('exito', '¡El copago ha sido registrado con éxito!');

        } catch (ValidationException $ex) {
            throw $ex;
        } catch (ReglaNegocioException $ex) {
            $this->addError('idActPac', $ex->getMessage());
        } catch (\Throwable $th) {
            Log::error('[(Livewire) pagos.copagos.crear@almacenar] Error al registrar copago.', ['excepción' => $th->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        } finally {
            $this->procesando = false;
        }
    }

    public function obtenerMontoParaEnviar($montoStr)
    {
        if (!is_string($montoStr) || trim($montoStr) === '') {
            return 0.0;
        }

        $limpio = str_replace(['.', ','], ['', '.'], $montoStr);
        return (float) $limpio;
    }

    private function formatearMonto(float $monto): string
    {
        return number_format($monto, 2, ',', '.');
    }
};
?>

<div class="contenedor max-w-3xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <h2 class="titulo-formulario">Registrar Copago</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="act-pac-select" class="etiqueta-formulario">Sesiones del Paciente</label>
                <select
                    id="act-pac-select"
                    @class([
                        'entrada w-full',
                        'border-red-500 border-2' => $errors->has('idActPac')
                    ])
                    wire:model="idActPac"
                    required
                >
                    <option value="" disabled selected>Seleccione un registro de sesiones</option>
                    @foreach($this->actividadesPacientes as $actPac)
                        <option value="{{ $actPac->id }}">
                            [{{ $actPac->primerTurno->fecha_hora->format('d/m/Y') }}]
                            {{ $actPac->nombre_actividad }}
                            ({{ $actPac->cantidadCopagos() }}/{{ $actPac->cant_sesiones }} copagos)
                            - {{ $actPac->ap_nom_paciente }}
                        </option>
                    @endforeach
                </select>
                @error('idActPac') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="profesional-select" class="etiqueta-formulario">Profesional que lo registra</label>
                <select
                    id="profesional-select"
                    @class([
                        'entrada',
                        'border-red-500 border-2' => $errors->has('idProfesional')
                    ])
                    wire:model="idProfesional"
                    required
                >
                    <option value="" disabled selected>Seleccione un profesional</option>
                    @foreach($this->profesionales as $prof)
                        <option value="{{ $prof->id }}">{{ $prof->apellido }}, {{ $prof->nombre }}</option>
                    @endforeach
                </select>
                @error('idProfesional') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo flex-1">
                <label for="monto-input" class="etiqueta-formulario">Monto del Copago</label>
                <input
                    id="monto-input"
                    type="text"
                    placeholder="Ejemplo: 7000,00"
                    @class([
                        'entrada',
                        'border-red-500 border-2' => $errors->has('monto')
                    ])
                    wire:model="montoStr"
                    x-on:input="$wire.$js.transformarIngresoMonto($el)"
                >
                @error('monto') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>

            <div class="columna-campo flex-1">
                <label for="metodo-select" class="etiqueta-formulario">Método de Pago</label>
                <select
                    id="metodo-select"
                    @class([
                        'entrada',
                        'border-red-500 border-2' => $errors->has('metodo')
                    ])
                    wire:model="metodo"
                    required
                >
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                </select>
                @error('metodo') <span class="text-red-500 text-sm italic">{{ $message }}</span> @enderror
            </div>
        </div>

        <button
            type="submit"
            class="boton-registrar"
            wire:loading.attr="disabled"
            wire:target="almacenar"
        >
            <span wire:loading.remove wire:target="almacenar">Registrar</span>
            <span wire:loading wire:target="almacenar">Registrando...</span>
        </button>
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
