<?php

use App\Exceptions\ReglaNegocioException;
use App\Livewire\Concerns\ManejaGrillaHorariosGenerales;
use App\Models\PacienteFijo;
use App\Services\HorarioPacienteFijoService;
use App\Support\Registros\ResultadoActualizacionHorarioFijo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use ManejaGrillaHorariosGenerales;

    public PacienteFijo $pacienteFijo;

    public string $aplicarDesde = 'ya';

    public ?array $vistaPrevia = null;

    public function mount(int $id): void
    {
        $this->pacienteFijo = PacienteFijo::with(['paciente', 'horarios'])->findOrFail($id);

        if (!$this->pacienteFijo->estaCursandoInscripcion()) {
            session()->flash(
                'error',
                'No se pueden editar los horarios fijos porque el paciente no está cursando una inscripción. Eliminalo de fijos y registrá la inscripción nuevamente.'
            );
            $this->redirectRoute('pacientes-fijos.inicio', navigate: true);
            return;
        }

        $this->prellenarHorariosDesdeFijos($this->pacienteFijo->horarios);
        $this->aplicarAplicarDesdePorDefecto();
    }

    #[Computed]
    public function debeForzarSemanaSiguiente(): bool
    {
        return app(HorarioPacienteFijoService::class)->debeForzarSemanaSiguiente(
            $this->pacienteFijo->id,
            $this->totalCompleto ? $this->slotsSeleccionados->all() : []
        );
    }

    #[Computed]
    public function inicioSemanaSiguiente(): Carbon
    {
        return Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
    }

    private function aplicarAplicarDesdePorDefecto(): void
    {
        if ($this->debeForzarSemanaSiguiente) {
            $this->aplicarDesde = 'siguiente';
        }
    }

    #[Computed]
    public function nombrePaciente(): string
    {
        return $this->pacienteFijo->paciente->apellido_nombre;
    }

    #[Computed]
    public function fechaCorte(): Carbon
    {
        if ($this->aplicarDesde === 'siguiente') {
            return Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek()->startOfDay();
        }

        return Carbon::now();
    }

    #[Computed]
    public function hayCambiosEfectivos(): bool
    {
        if (!$this->totalCompleto) {
            return false;
        }

        try {
            return app(HorarioPacienteFijoService::class)->tieneCambiosEfectivos(
                $this->pacienteFijo->id,
                $this->slotsSeleccionados->all(),
                $this->fechaCorte
            );
        } catch (\Throwable) {
            return false;
        }
    }

    #[Computed]
    public function puedePrevisualizar(): bool
    {
        return $this->totalCompleto && $this->hayCambiosEfectivos;
    }

    public function updatedFrecuenciaSemanal(): void
    {
        $this->vistaPrevia = null;
        $this->resetErrorBag();
        unset($this->totalCompleto, $this->slotsSeleccionados, $this->debeForzarSemanaSiguiente, $this->hayCambiosEfectivos, $this->puedePrevisualizar);
        $this->aplicarAplicarDesdePorDefecto();
    }

    public function updatedHorarios(): void
    {
        $this->vistaPrevia = null;
        $this->resetErrorBag();
        unset($this->diasSeleccionados, $this->slotsSeleccionados, $this->totalCompleto, $this->debeForzarSemanaSiguiente, $this->hayCambiosEfectivos, $this->puedePrevisualizar);
        $this->aplicarAplicarDesdePorDefecto();
    }

    public function updatedAplicarDesde(): void
    {
        if ($this->debeForzarSemanaSiguiente && $this->aplicarDesde === 'ya') {
            $this->aplicarDesde = 'siguiente';
        }

        $this->vistaPrevia = null;
        unset($this->fechaCorte, $this->hayCambiosEfectivos, $this->puedePrevisualizar);
    }

    public function previsualizar(): void
    {
        if ($this->debeForzarSemanaSiguiente) {
            $this->aplicarDesde = 'siguiente';
        }

        $this->validate([
            'frecuenciaSemanal' => 'required|integer|min:1|max:5',
            'aplicarDesde' => 'required|in:ya,siguiente',
        ], [], [
            'frecuenciaSemanal' => 'frecuencia semanal',
            'aplicarDesde' => 'aplicación del cambio',
        ]);

        if (!$this->totalCompleto) {
            $this->addError(
                'frecuenciaSemanal',
                "Debe completar exactamente {$this->frecuenciaSemanal} horario(s) (seleccionó {$this->slotsSeleccionados->count()})."
            );
            return;
        }

        if ($diasSolape = $this->diasConSolapeEntreActividades()) {
            $this->addError(
                'horarios',
                'No puede asignar Gimnasio y Pilates en horarios que se solapan el mismo día ('.implode(', ', $diasSolape).'). Cada clase dura 1 hora.'
            );
            return;
        }

        if (!$this->hayCambiosEfectivos) {
            $this->vistaPrevia = null;
            return;
        }

        try {
            $resultado = app(HorarioPacienteFijoService::class)->previsualizar(
                $this->pacienteFijo->id,
                $this->slotsSeleccionados->all(),
                $this->fechaCorte
            );

            $this->vistaPrevia = $this->mapearVistaPrevia($resultado);
        } catch (ReglaNegocioException $ex) {
            session()->flash('error', $ex->getMessage());
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) pacientes-fijos.editar@previsualizar] Error al previsualizar cambios.', [
                'excepcion' => $ex->getMessage(),
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function confirmar()
    {
        if ($this->vistaPrevia === null) {
            $this->previsualizar();

            if ($this->vistaPrevia === null) {
                return;
            }
        }

        if ($this->debeForzarSemanaSiguiente) {
            $this->aplicarDesde = 'siguiente';
        }

        try {
            $resultado = app(HorarioPacienteFijoService::class)->actualizar(
                $this->pacienteFijo->id,
                $this->slotsSeleccionados->all(),
                $this->fechaCorte
            );

            if ($resultado->idInscripcionParaCobro && $resultado->cargoExtra > 0) {
                return redirect()
                    ->route('actividades-pacientes.pagos.crear', ['id' => $resultado->idInscripcionParaCobro])
                    ->with('exito', 'Horarios actualizados. Hay un cargo adicional por el aumento de frecuencia.');
            }

            return redirect()
                ->route('pacientes-fijos.inicio')
                ->with('exito', 'Los horarios fijos y los turnos fueron actualizados correctamente.');
        } catch (ReglaNegocioException $ex) {
            session()->flash('error', $ex->getMessage());
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) pacientes-fijos.editar@confirmar] Error al confirmar cambios.', [
                'excepcion' => $ex->getMessage(),
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    private function mapearVistaPrevia(ResultadoActualizacionHorarioFijo $resultado): array
    {
        return [
            'frecuencia_anterior' => $resultado->frecuenciaAnterior,
            'frecuencia_nueva' => $resultado->frecuenciaNueva,
            'total_anterior' => $resultado->totalAnterior,
            'total_nuevo' => $resultado->totalNuevo,
            'cargo_extra' => $resultado->cargoExtra,
            'hubo_primer_turno' => $resultado->huboPrimerTurno,
            'fecha_corte' => $this->fechaCorte->toDateTimeString(),
            'turnos_crear' => $resultado->turnosACrear,
            'turnos_eliminar' => $resultado->turnosAEliminar,
            'sin_cambios' => $resultado->sinCambiosEfectivos,
        ];
    }
};
?>

<div class="contenedor max-w-4xl">
    <form class="formulario" wire:submit.prevent="previsualizar">
        <x-alerta tipo="error" />

        <h1 class="titulo-formulario">Editar horarios fijos</h1>

        <div class="mb-4">
            <label class="etiqueta-formulario">Paciente</label>
            <p class="entrada-info">{{ $this->nombrePaciente }}</p>
        </div>

        <x-actividades-pacientes.general.grilla-horarios
            :frecuencia-semanal="$frecuenciaSemanal"
            :slots-count="$this->slotsSeleccionados->count()"
            :precio="$this->precio"
            :horarios="$horarios"
        />

        @if ($this->totalCompleto)
            <div class="mt-6 columna-campo">
                <p class="text-white text-lg font-medium mb-2">¿Desde cuándo aplicar el cambio?</p>

                <label class="flex items-center gap-2 mb-2 @if($this->debeForzarSemanaSiguiente) opacity-50 cursor-not-allowed @endif">
                    <input
                        type="radio"
                        wire:model.live="aplicarDesde"
                        value="ya"
                        @disabled($this->debeForzarSemanaSiguiente)
                    >
                    Desde ya
                </label>

                <label class="flex items-center gap-2">
                    <input type="radio" wire:model.live="aplicarDesde" value="siguiente">
                    Desde la semana que viene
                    <span class="text-sm text-gray-400">
                        ({{ $this->inicioSemanaSiguiente->translatedFormat('l d/m/Y') }})
                    </span>
                </label>

                @if ($this->debeForzarSemanaSiguiente)
                    <p class="mt-2 text-sm text-amber-300">
                        El cambio no puede aplicarse desde ya sin romper la semana en curso
                        (superaría la frecuencia semanal, o incluye un horario ya pasado).
                        Aplica desde el lunes siguiente.
                    </p>
                @endif
            </div>
        @endif

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('pacientes-fijos.inicio') }}" class="boton-registrar opacity-70">Cancelar</a>
            <button type="submit" class="boton-registrar" @disabled(!$this->puedePrevisualizar)>
                Ver cambios
            </button>
        </div>

        @if ($this->totalCompleto && !$this->hayCambiosEfectivos)
            <p class="mt-3 text-sm text-gray-400 italic">
                No hay cambios respecto a los horarios actuales para la fecha de corte seleccionada.
            </p>
        @endif
    </form>

    @if ($vistaPrevia && !($vistaPrevia['sin_cambios'] ?? false))
        <div class="mt-8 p-4 border border-white/15 rounded-lg bg-[#006E6B]">
            <h2 class="text-white text-xl font-semibold mb-4">Vista previa de cambios</h2>

            <p class="text-gray-300 mb-2">
                Aplicar desde:
                <span class="text-white">
                    {{ Carbon::parse($vistaPrevia['fecha_corte'])->translatedFormat('l d/m/Y H:i') }}
                </span>
            </p>

            <p class="text-gray-300 mb-4">
                Frecuencia:
                <span class="text-white">{{ $vistaPrevia['frecuencia_anterior'] }} → {{ $vistaPrevia['frecuencia_nueva'] }}</span>
            </p>

            <div class="mb-4">
                <h3 class="etiqueta-formulario mb-2">Turnos a dar de alta ({{ count($vistaPrevia['turnos_crear']) }})</h3>
                @forelse ($vistaPrevia['turnos_crear'] as $turno)
                    <p class="text-sm text-emerald-300">
                        + {{ Carbon::parse($turno['fecha_hora'])->translatedFormat('D d/m H:i') }}
                        — {{ $turno['nombre_actividad'] }}
                    </p>
                @empty
                    <p class="text-sm text-gray-400 italic">Ninguno</p>
                @endforelse
            </div>

            <div class="mb-4">
                <h3 class="etiqueta-formulario mb-2">Turnos a eliminar ({{ count($vistaPrevia['turnos_eliminar']) }})</h3>
                @forelse ($vistaPrevia['turnos_eliminar'] as $turno)
                    <p class="text-sm text-red-300">
                        − {{ Carbon::parse($turno['fecha_hora'])->translatedFormat('D d/m H:i') }}
                        — {{ $turno['nombre_actividad'] }}
                    </p>
                @empty
                    <p class="text-sm text-gray-400 italic">Ninguno</p>
                @endforelse
            </div>

            <div class="mb-6">
                <h3 class="etiqueta-formulario mb-2">Total a pagar (inscripción actual)</h3>
                <p class="text-white">
                    ${{ number_format($vistaPrevia['total_anterior'], 2, ',', '.') }}
                    →
                    ${{ number_format($vistaPrevia['total_nuevo'], 2, ',', '.') }}
                </p>
                @if ($vistaPrevia['cargo_extra'] > 0)
                    <p class="text-amber-300 text-sm mt-1">
                        Cargo adicional: ${{ number_format($vistaPrevia['cargo_extra'], 2, ',', '.') }}
                        @if ($vistaPrevia['hubo_primer_turno'])
                            (prorrateo del salto de tarifa por los turnos nuevos generados)
                        @endif
                    </p>
                @else
                    <p class="text-gray-400 text-sm mt-1 italic">Sin cargo adicional</p>
                @endif
            </div>

            <button type="button" class="boton-registrar" wire:click="confirmar" wire:confirm="¿Confirmar la actualización de horarios y turnos?">
                Confirmar cambios
            </button>
        </div>
    @endif
</div>
