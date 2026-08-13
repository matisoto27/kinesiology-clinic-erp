<?php

use App\Livewire\Concerns\ManejaGrillaHorariosGenerales;
use App\Models\Actividad;
use App\Models\Paciente;
use App\Services\ActividadPacienteService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use ManejaGrillaHorariosGenerales;

    public string $busqueda = '';

    public ?int $idPacienteSeleccionado = null;

    public ?string $semanaInicio = null;

    public string $fechaAncla = '';

    public function mount(): void
    {
        $this->inicializarGrillaHorarios();
    }

    #[Computed]
    public function resultadosBusqueda(): Collection
    {
        if (strlen($this->busqueda) < 2) {
            return collect();
        }

        return Paciente::select('id', 'nombre', 'apellido')
            ->buscarPorApNom($this->busqueda)
            ->whereDoesntHave('pacienteFijo')
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function debeForzarSemanaSiguiente(): bool
    {
        $dias = $this->diasSeleccionados;

        if ($dias === []) {
            return false;
        }

        $ahora = Carbon::now();
        $diaActualIso = $ahora->dayOfWeekIso;

        if ($diaActualIso >= 6) {
            return true;
        }

        if ($diaActualIso === 5 && $ahora->hour >= 19) {
            return true;
        }

        foreach ($dias as $dia) {
            if (Actividad::diaSemanaAEntero($dia) >= $diaActualIso) {
                return false;
            }
        }

        return true;
    }

    #[Computed]
    public function fechasCandidatasPrimeraClase(): array
    {
        if (!$this->semanaInicio || $this->diasSeleccionados === []) {
            return [];
        }

        $candidatas = [];

        foreach ($this->diasSeleccionados as $dia) {
            $fecha = $this->fechaDeSemana($dia, $this->semanaInicio);

            if ($this->semanaInicio === 'actual' && !$this->esFechaValidaSemanaActual($fecha)) {
                continue;
            }

            $candidatas[] = $fecha->toDateString();
        }

        return $candidatas;
    }

    #[Computed]
    public function puedeRegistrar(): bool
    {
        return $this->idPacienteSeleccionado !== null
            && $this->totalCompleto
            && $this->fechaAncla !== '';
    }

    public function seleccionarSugerencia(int $id, string $apellidoNombre): void
    {
        $this->busqueda = $apellidoNombre;
        $this->idPacienteSeleccionado = $id;
        $this->resetErrorBag('idPacienteSeleccionado');
    }

    public function limpiarSeleccion(): void
    {
        $this->reset(['busqueda', 'idPacienteSeleccionado']);
    }

    public function updatedFrecuenciaSemanal(): void
    {
        $this->resetErrorBag();
        $this->semanaInicio = null;
        $this->fechaAncla = '';

        unset($this->totalCompleto);

        if ($this->totalCompleto) {
            unset($this->debeForzarSemanaSiguiente);

            if ($this->debeForzarSemanaSiguiente) {
                $this->semanaInicio = 'siguiente';
            }

            $this->sincronizarFechaAncla();
        }
    }

    public function updatedHorarios(): void
    {
        $this->resetErrorBag();
        $this->semanaInicio = null;
        $this->fechaAncla = '';

        unset($this->diasSeleccionados, $this->slotsSeleccionados, $this->totalCompleto, $this->debeForzarSemanaSiguiente);

        if (!$this->totalCompleto) {
            return;
        }

        if ($this->debeForzarSemanaSiguiente) {
            $this->semanaInicio = 'siguiente';
        }

        $this->sincronizarFechaAncla();
    }

    public function updatedSemanaInicio(): void
    {
        $this->fechaAncla = '';
        $this->sincronizarFechaAncla();
    }

    private function sincronizarFechaAncla(): void
    {
        unset($this->fechasCandidatasPrimeraClase);
        $candidatas = $this->fechasCandidatasPrimeraClase;

        if (count($candidatas) === 1) {
            $this->fechaAncla = $candidatas[0];
        }
    }

    private function fechaDeSemana(string $diaNombre, string $tipoSemana): Carbon
    {
        $lunes = Carbon::now()->startOfWeek(Carbon::MONDAY);

        if ($tipoSemana === 'siguiente') {
            $lunes->addWeek();
        }

        return $lunes->copy()->addDays(Actividad::diaSemanaAEntero($diaNombre) - 1);
    }

    private function esFechaValidaSemanaActual(Carbon $fecha): bool
    {
        $ahora = Carbon::now();

        if ($fecha->lt($ahora->copy()->startOfDay())) {
            return false;
        }

        return !($fecha->isSameDay($ahora) && $ahora->hour >= 19);
    }

    public function almacenar()
    {
        $this->validate([
            'idPacienteSeleccionado' => 'required|integer|exists:pacientes,id',
            'frecuenciaSemanal' => 'required|integer|min:1|max:5',
        ], [], ['idPacienteSeleccionado' => 'paciente', 'frecuenciaSemanal' => 'frecuencia semanal']);

        $slots = $this->slotsSeleccionados;

        if ($slots->count() !== $this->frecuenciaSemanal) {
            $this->addError(
                'frecuenciaSemanal',
                "Debe completar exactamente {$this->frecuenciaSemanal} horario(s) entre los días (seleccionó {$slots->count()})."
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

        if ($this->fechaAncla === '') {
            $this->addError('fechaAncla', 'Seleccione la fecha de la primera clase.');
            return;
        }

        try {
            $resultado = app(ActividadPacienteService::class)->registrarInscripcionesGenerales([
                'id_paciente' => $this->idPacienteSeleccionado,
                'fecha_ancla' => $this->fechaAncla,
                'horarios' => $slots->all(),
            ]);
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) actividades-pacientes.general.crear@almacenar] Error al registrar la inscripción general.', [
                'excepcion' => $ex->getMessage(),
            ]);

            session()->flash('error', $ex->getMessage());
            return;
        }

        return redirect()
            ->route('actividades-pacientes.pagos.crear', ['id' => $resultado->inscripcionParaCobro->id])
            ->with('exito', 'La inscripción fue registrada correctamente.');
    }
};
?>

<div class="contenedor max-w-4xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <x-alerta tipo="error" />

        <h1 class="titulo-formulario">Nueva inscripción Gimnasio/Pilates</h1>

        <div class="mb-4">
            <x-buscador-livewire
                :busqueda="$busqueda"
                :idSeleccionado="$idPacienteSeleccionado"
                :sugerencias="$this->resultadosBusqueda"
                etiquetaBuscador="Buscar Paciente"
                campoError="idPacienteSeleccionado"
            />
        </div>

        <x-actividades-pacientes.general.grilla-horarios
            :frecuencia-semanal="$frecuenciaSemanal"
            :slots-count="$this->slotsSeleccionados->count()"
            :precio="$this->precio"
            :horarios="$horarios"
        />

        @if ($this->totalCompleto)
            <div class="mt-6 fila-formulario">
                <div class="columna-campo flex-1">
                    <p class="text-white text-lg font-medium">¿Arranca esta semana o la que viene?</p>

                    <label class="flex items-center gap-2">
                        <input
                            type="radio"
                            wire:model.live="semanaInicio"
                            value="actual"
                            @disabled($this->debeForzarSemanaSiguiente)
                        >
                        Semana actual
                    </label>

                    <label class="flex items-center gap-2">
                        <input type="radio" wire:model.live="semanaInicio" value="siguiente">
                        Semana que viene
                    </label>
                </div>

                <div class="columna-campo flex-1">
                    <label class="etiqueta-formulario">Primera clase</label>
                    <select class="entrada" wire:model.live="fechaAncla" @disabled(!$semanaInicio)>
                        <option value="" disabled selected>Seleccione una fecha</option>
                        @foreach ($this->fechasCandidatasPrimeraClase as $fecha)
                            <option value="{{ $fecha }}">
                                {{ Carbon::parse($fecha)->translatedFormat('l d/m/Y') }}
                            </option>
                        @endforeach
                    </select>
                    @error('fechaAncla')
                        <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        @endif

        <button type="submit" class="boton-registrar" @disabled(!$this->puedeRegistrar)>Registrar</button>
    </form>
</div>
