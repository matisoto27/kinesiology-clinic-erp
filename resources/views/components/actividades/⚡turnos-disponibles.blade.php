<?php

use App\Models\Actividad;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public Collection $actividades;

    public string $modo = 'mensual';

    public ?int $idActividad = null;

    public bool $soloEnPunto = false;

    public string $fechaDesde = '';

    public string $fechaHasta = '';

    public string $busquedaPaciente = '';

    public ?int $idPacienteSeleccionado = null;

    public string $nombrePacienteSeleccionado = '';

    public array $sugerencias = [];

    public function mount(Collection $actividades): void
    {
        $this->actividades = $actividades;

        $hoy = Carbon::now();
        $this->fechaDesde = $hoy->toDateString();
        $this->fechaHasta = $hoy->copy()->startOfWeek(Carbon::MONDAY)->addWeek()->addDays(4)->toDateString();
    }

    public function updatedModo(): void
    {
        $this->resetErrorBag();

        if ($this->modo === 'mensual') {
            $this->reset(['idPacienteSeleccionado', 'nombrePacienteSeleccionado', 'busquedaPaciente', 'sugerencias']);

            if ($this->idActividad && !in_array($this->idActividad, [Actividad::GIMNASIO, Actividad::PILATES], true)) {
                $this->idActividad = null;
                $this->soloEnPunto = false;
            }
        }
    }

    public function updatedIdActividad($value): void
    {
        if ((int) $value !== Actividad::GIMNASIO) {
            $this->soloEnPunto = false;
        }
    }

    public function updatedSoloEnPunto(): void
    {
        if ((int) $this->idActividad !== Actividad::GIMNASIO) {
            $this->soloEnPunto = false;
        }
    }

    public function updatedBusquedaPaciente(): void
    {
        $apellidoNombrePaciente = trim($this->busquedaPaciente);

        if (strlen($apellidoNombrePaciente) < 2) {
            $this->sugerencias = [];

            return;
        }

        $this->sugerencias = Paciente::buscarPorApNom($apellidoNombrePaciente)
            ->limit(5)
            ->get(['id', 'nombre', 'apellido'])
            ->toArray();
    }

    public function seleccionarPaciente($id, $nombre, $apellido): void
    {
        $this->idPacienteSeleccionado = (int) $id;
        $this->nombrePacienteSeleccionado = "$apellido $nombre";
        $this->busquedaPaciente = '';
        $this->sugerencias = [];
    }

    public function deseleccionarPaciente(): void
    {
        $this->reset(['idPacienteSeleccionado', 'nombrePacienteSeleccionado']);
    }

    #[Computed]
    public function actividadesOpciones(): Collection
    {
        if ($this->modo === 'mensual') {
            return $this->actividades
                ->whereIn('id', [Actividad::GIMNASIO, Actividad::PILATES])
                ->values();
        }

        return $this->actividades;
    }

    public function copiarTurnos(): void
    {
        if (!$this->idActividad || !$actividad = Actividad::find($this->idActividad)) {
            session()->flash('error', 'Por favor, seleccione una actividad válida.');

            return;
        }

        if ($this->modo === 'mensual') {
            $this->copiarDisponibilidadMensual($actividad);

            return;
        }

        $this->copiarDisponibilidadFechas($actividad);
    }

    private function copiarDisponibilidadMensual(Actividad $actividad): void
    {
        if (!$actividad->esActividadGeneral()) {
            session()->flash('error', 'La disponibilidad mensual solo aplica a Gimnasio y Pilates.');

            return;
        }

        $slots = collect($actividad->slotsEstructurales());

        if ($this->soloEnPunto) {
            $slots = $slots->filter(fn (array $slot) => str_ends_with($slot['hora'], '00'));
        }

        if ($slots->isEmpty()) {
            session()->flash('error', 'No hay horarios configurados para esta actividad.');

            return;
        }

        $mensaje = $this->estructurarMensajeMensual($slots, $actividad->nombre);
        $this->dispatch('copiar-portapapeles', texto: $mensaje);

        session()->flash('exito', '¡Turnos copiados al portapapeles!');
    }

    private function copiarDisponibilidadFechas(Actividad $actividad): void
    {
        if ($this->fechaDesde === '' || $this->fechaHasta === '') {
            session()->flash('error', 'Seleccione el rango de fechas.');

            return;
        }

        $desde = Carbon::parse($this->fechaDesde)->startOfDay();
        $hasta = Carbon::parse($this->fechaHasta)->endOfDay();

        if ($desde->gt($hasta)) {
            session()->flash('error', 'La fecha desde no puede ser posterior a la fecha hasta.');

            return;
        }

        if ($desde->diffInDays($hasta) > 28) {
            session()->flash('error', 'El rango no puede superar las 4 semanas.');

            return;
        }

        $desdeEfectivo = $desde->lt(Carbon::now()->addHour())
            ? Carbon::now()->addHour()
            : $desde;

        $turnos = $actividad->turnosDisponibles($this->idPacienteSeleccionado, $desdeEfectivo, $hasta);

        if ($this->soloEnPunto) {
            $turnos = array_values(array_filter(
                $turnos,
                fn ($turno) => Carbon::parse($turno)->format('i') === '00'
            ));
        }

        if ($turnos === []) {
            session()->flash('error', 'No hay turnos disponibles en el rango seleccionado.');

            return;
        }

        $mensaje = $this->estructurarMensajeFechas($turnos, $actividad->nombre);
        $this->dispatch('copiar-portapapeles', texto: $mensaje);

        session()->flash('exito', '¡Turnos copiados al portapapeles!');
    }

    /**
     * @param  Collection<int, array{dia_semana: string, hora: string, libres: int, cupo: int}>  $slots
     */
    private function estructurarMensajeMensual(Collection $slots, string $nombreActividad): string
    {
        $saludo = $this->nombrePacienteSeleccionado
            ? "Hola {$this->nombrePacienteSeleccionado}! "
            : 'Hola! ';
        $texto = "{$saludo}Te comparto los horarios de *{$nombreActividad}* y el cupo de cada uno:\n\n";

        foreach ($slots->groupBy('dia_semana') as $dia => $horas) {
            $texto .= "📅 *{$dia}*\n";

            foreach ($horas as $slot) {
                $texto .= "• {$slot['hora']} hs {$this->etiquetaCupoMensual((int) $slot['libres'])}\n";
            }

            $texto .= "\n";
        }

        return $texto;
    }

    private function etiquetaCupoMensual(int $libres): string
    {
        if ($libres < 1) {
            return '🔴 Sin cupo';
        }

        $cantidad = $libres === 1 ? '1 lugar disponible' : "{$libres} lugares disponibles";

        return $libres <= 2
            ? "🟡 {$cantidad}"
            : "🟢 {$cantidad}";
    }

    private function estructurarMensajeFechas(array $turnos, string $nombreActividad): string
    {
        $saludo = $this->nombrePacienteSeleccionado
            ? "Hola {$this->nombrePacienteSeleccionado}! "
            : 'Hola! ';
        $texto = "{$saludo}Te comparto los turnos disponibles para *{$nombreActividad}*:\n\n";

        $agrupados = collect($turnos)->groupBy(fn ($t) => Carbon::parse($t)->translatedFormat('l d/m'));

        foreach ($agrupados as $fecha => $horas) {
            $texto .= "📅 *{$fecha}*\n";

            foreach ($horas as $hora) {
                $texto .= '• '.Carbon::parse($hora)->format('H:i')." hs\n";
            }

            $texto .= "\n";
        }

        return $texto;
    }
};
?>

<div class="contenedor max-w-2xl" @copiar-portapapeles.window="navigator.clipboard.writeText($event.detail.texto);">
    <div class="formulario">
        <h2 class="titulo-formulario">Consultar turnos disponibles</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="mb-4 space-y-2 text-white">
            <p class="etiqueta-formulario">Tipo de disponibilidad</p>

            <label class="flex items-center gap-2">
                <input
                    type="radio"
                    name="modo"
                    wire:model.live="modo"
                    value="mensual"
                    @checked($modo === 'mensual')
                >
                Disponibilidad mensual (cupo inscripciones gym/pilates)
            </label>

            <label class="flex items-center gap-2">
                <input
                    type="radio"
                    name="modo"
                    wire:model.live="modo"
                    value="fechas"
                    @checked($modo === 'fechas')
                >
                Disponibilidad por fechas (turnos por fechas específicas)
            </label>
        </div>

        <div class="fila-formulario">
            <div class="columna-campo">
                <label for="actividad-select" class="etiqueta-formulario">Actividad</label>
                <select id="actividad-select" class="entrada" wire:model.live="idActividad">
                    <option value="">Seleccione una actividad</option>
                    @foreach($this->actividadesOpciones as $act)
                        <option value="{{ $act->id }}">{{ $act->nombre }}</option>
                    @endforeach
                </select>
            </div>

            @if ($modo === 'fechas')
                <div class="columna-campo relative">
                    <label for="buscar-paciente" class="etiqueta-formulario">Paciente (Opcional)</label>

                    @if($idPacienteSeleccionado)
                        <div class="flex items-center gap-2">
                            <div class="entrada w-[28ch] bg-emerald-900/20 border-emerald-500/50 flex justify-between items-center">
                                <span class="text-emerald-400 font-medium truncate">{{ $nombrePacienteSeleccionado }}</span>
                                <button
                                    type="button"
                                    class="text-red-400 hover:text-red-300"
                                    wire:click="deseleccionarPaciente">
                                    <x-iconos.cruz />
                                </button>
                            </div>
                        </div>
                    @else
                        <input
                            id="buscar-paciente"
                            type="text"
                            class="entrada w-[28ch]"
                            placeholder="Ingrese nombre y/o apellido"
                            wire:model.live.debounce.300ms="busquedaPaciente"
                            autocomplete="off"
                        >
                        @if(!empty($sugerencias))
                            <div
                                class="mt-1 absolute w-full bg-[#1e293b] border-gray-700 border rounded-md shadow-lg overflow-hidden z-50"
                                wire:click.outside="$set('sugerencias', [])">
                                @foreach($sugerencias as $sug)
                                    <button
                                        type="button"
                                        class="px-4 py-2 w-full text-gray-200 text-sm text-left hover:bg-emerald-600 transition-colors"
                                        wire:click="seleccionarPaciente({{ $sug['id'] }}, '{{ $sug['nombre'] }}', '{{ $sug['apellido'] }}')">
                                        {{ $sug['apellido'] }},
                                        {{ $sug['nombre'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        @if ($modo === 'fechas')
            <div class="fila-formulario">
                <div class="columna-campo">
                    <label for="fecha-desde" class="etiqueta-formulario">Desde</label>
                    <input id="fecha-desde" type="date" class="entrada" wire:model="fechaDesde">
                </div>
                <div class="columna-campo">
                    <label for="fecha-hasta" class="etiqueta-formulario">Hasta</label>
                    <input id="fecha-hasta" type="date" class="entrada" wire:model="fechaHasta">
                </div>
            </div>
        @endif

        <div class="mb-4 flex items-center gap-1">
            <input
                id="en-punto-checkbox"
                type="checkbox"
                class="checkbox-formulario"
                wire:model.live="soloEnPunto"
                @if((int) $idActividad !== \App\Models\Actividad::GIMNASIO) disabled @endif
            >
            <label for="en-punto-checkbox" class="etiqueta-formulario {{ (int) $idActividad !== \App\Models\Actividad::GIMNASIO ? 'opacity-50' : '' }}">
                Incluir solo turnos en punto
            </label>
        </div>

        <button
            type="button"
            class="boton-registrar"
            wire:click="copiarTurnos"
            wire:loading.attr="disabled"
            wire:target="copiarTurnos"
            @if(!$idActividad) disabled @endif
        >
            <span wire:loading.remove wire:target="copiarTurnos">Copiar turnos disponibles</span>
            <span wire:loading wire:target="copiarTurnos">Generando...</span>
        </button>
    </div>
</div>
