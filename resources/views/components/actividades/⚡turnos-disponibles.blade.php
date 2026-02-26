<?php

use App\Models\Actividad;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public Collection $actividades;

    public ?int $idActividad = null;
    public string $busquedaPaciente = '';
    public ?int $idPacienteSeleccionado = null;
    public string $nombrePacienteSeleccionado = '';
    public array $sugerencias = [];

    public function updatedBusquedaPaciente()
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

    public function seleccionarPaciente($id, $nombre, $apellido)
    {
        $this->idPacienteSeleccionado = (int) $id;
        $this->nombrePacienteSeleccionado = "$apellido $nombre";
        $this->busquedaPaciente = '';
        $this->sugerencias = [];
    }

    public function deseleccionarPaciente()
    {
        $this->reset(['idPacienteSeleccionado', 'nombrePacienteSeleccionado']);
    }

    public function copiarTurnos()
    {
        if (!$this->idActividad || !$actividad = Actividad::find($this->idActividad)) {
            session()->flash('error', 'Por favor, seleccione una actividad válida.');
            return;
        }

        $lunes = Carbon::now()->startOfWeek();
        $viernesSiguiente = $lunes->copy()->addWeek()->addDays(4);

        $turnos = $actividad->turnosDisponibles($this->idPacienteSeleccionado, $lunes->startOfDay(), $viernesSiguiente->endOfDay());

        if (empty($turnos)) {
            session()->flash('error', 'No hay turnos disponibles cercanos.');
            return;
        }

        $mensaje = $this->estructurarMensajeWhatsApp($turnos, $actividad->nombre);
        $this->dispatch('copiar-portapapeles', texto: $mensaje);

        session()->flash('exito', '¡Turnos copiados al portapapeles!');
    }

    private function estructurarMensajeWhatsApp(array $turnos, string $nombreActividad): string
    {
        $saludo = $this->nombrePacienteSeleccionado ? "Hola {$this->nombrePacienteSeleccionado}! " : "Hola! ";
        $texto = "{$saludo}Te comparto los turnos disponibles para *{$nombreActividad}*:\n\n";

        $agrupados = collect($turnos)->groupBy(fn($t) => Carbon::parse($t)->translatedFormat('l d/m'));

        foreach ($agrupados as $fecha => $horas) {
            $texto .= "📅 *{$fecha}*\n";
            foreach ($horas as $hora) {
                $texto .= "• " . Carbon::parse($hora)->format('H:i') . " hs\n";
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

        <div class="fila-formulario justify-center">
            <div class="columna-campo">
                <label for="actividad-select" class="etiqueta-formulario">Actividad</label>
                <select id="actividad-select" class="entrada" wire:model.live="idActividad">
                    <option value="">Seleccione una actividad</option>
                    @foreach($this->actividades as $act)
                        <option value="{{ $act->id }}">{{ $act->nombre }}</option>
                    @endforeach
                </select>
            </div>

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
        </div>

        <button
            type="button"
            class="boton-registrar"
            wire:click="copiarTurnos"
            wire:loading.attr="disabled"
            wire:target="copiarTurnos"
        >
            <span wire:loading.remove wire:target="copiarTurnos">Copiar turnos disponibles</span>
            <span wire:loading wire:target="copiarTurnos">Generando...</span>
        </button>
    </div>
</div>
