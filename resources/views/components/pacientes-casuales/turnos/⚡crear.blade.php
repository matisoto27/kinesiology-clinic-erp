<?php

use App\Models\Actividad;
use App\Models\ActividadCombo;
use App\Models\ActividadPaciente;
use App\Models\PacienteCasual;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public string $busqueda = '';
    public ?int $idPacienteSeleccionado = null;
    public bool $esClasePrueba = false;
    public bool $proximaSemana = false;
    public array $turnosSeleccionados = [];

    #[Url]
    public ?string $tipo = null;

    public function mount()
    {
        $this->esClasePrueba = $this->tipo === 'PruebaPilates';
        $this->agregarNuevoTurno();
    }

    public function seleccionarSugerencia(int $id, string $apellidoNombre)
    {
        $this->busqueda = $apellidoNombre;
        $this->idPacienteSeleccionado = $id;
        $this->limpiarTurnos();
    }

    public function limpiarSeleccion()
    {
        $this->reset(['busqueda', 'idPacienteSeleccionado']);
    }

    public function updated($nombrePropiedad)
    {
        if ($nombrePropiedad === 'busqueda') {
            $this->resetErrorBag('idPacienteSeleccionado');
        }

        if (str_contains($nombrePropiedad, 'turnosSeleccionados')) {
            $this->resetErrorBag($nombrePropiedad);
            $this->resetErrorBag('turnosSeleccionados');
        }

        if ($nombrePropiedad === 'esClasePrueba' || $nombrePropiedad === 'proximaSemana') {
            $this->limpiarTurnos();
        }
    }

    public function limpiarTurnos()
    {
        $this->turnosSeleccionados = [];
        $this->agregarNuevoTurno();
    }

    public function removerFilaTurno($indice)
    {
        unset($this->turnosSeleccionados[$indice]);
        $this->turnosSeleccionados = array_values($this->turnosSeleccionados);

        if (empty($this->turnosSeleccionados)) {
            $this->agregarNuevoTurno();
        }
    }

    public function agregarNuevoTurno()
    {
        if (count($this->turnosSeleccionados) < $this->maximoTurnos()) {
            $this->turnosSeleccionados[] = ['fecha' => '', 'hora' => ''];
        }
    }

    #[Computed]
    public function resultadosBusqueda()
    {
        if (strlen($this->busqueda) < 2) return collect();
        return PacienteCasual::buscarPorApNom($this->busqueda)->take(5)->get();
    }

    #[Computed]
    public function diasSemana()
    {
        $fechaBase = ($this->proximaSemana ? now()->addWeek() : now())->startOfWeek();

        $diasDisponibles = collect($this->turnosDisponibles())
            ->map(fn($t) => substr($t, 0, 10))
            ->unique()
            ->flip();

        $dias = [];
        for ($i = 0; $i < 5; $i++) {
            $fecha = (clone $fechaBase)->addDays($i);
            $fechaStr = $fecha->format('Y-m-d');

            if (isset($diasDisponibles[$fechaStr])) {
                $dias[$fechaStr] = $fecha->translatedFormat('l d/m');
            }
        }
        return $dias;
    }

    #[Computed]
    public function turnosDisponibles()
    {
        if (!$this->idPacienteSeleccionado) return [];

        $inicio = ($this->proximaSemana ? now()->addWeek() : now())->startOfWeek();
        $fin = $inicio->copy()->addDays(4);

        if (!$this->actividadActual) return [];

        return $this->actividadActual->turnosDisponibles($this->idPacienteSeleccionado, $inicio->startOfDay(), $fin->endOfDay(), false);
    }

    #[Computed]
    public function actividadActual()
    {
        static $cache = [];
        $id = $this->esClasePrueba ? 2 : 1;
        return $cache[$id] ??= Actividad::findOrFail($id);
    }

    public function almacenarTurnos()
    {
        $this->validate([
            'idPacienteSeleccionado' => 'required|exists:pacientes_casuales,id',
            'turnosSeleccionados' => "required|array|min:1|max:{$this->maximoTurnos()}",
            'turnosSeleccionados.*.fecha' => 'required|date',
            'turnosSeleccionados.*.hora' => 'required',
        ], [], [
            'idPacienteSeleccionado' => 'paciente',
            'turnosSeleccionados' => 'turnos',
            'turnosSeleccionados.*.fecha' => 'fecha turno',
            'turnosSeleccionados.*.hora' => 'hora turno'
        ]);

        try {
            DB::transaction(function () {
                $idActividad = (int) $this->actividadActual->id;
                $esGympass = $idActividad === 1;

                $totalAPagar = $esGympass ? 0 : ActividadCombo::obtenerPrecioPruebaPilates();

                $turnosOrdenados = collect($this->turnosSeleccionados)
                    ->sortBy([
                        ['fecha', 'asc'],
                        ['hora', 'asc']
                    ])
                    ->values();

                $actividadPaciente = ActividadPaciente::create([
                    'fecha_comienzo' => now(),
                    'cant_sesiones' => count($this->turnosSeleccionados),
                    'es_fijo' => false,
                    'total_a_pagar' => $totalAPagar,
                    'pago_completado' => $esGympass,
                    'id_actividad' => $idActividad,
                    'id_paciente_casual' => $this->idPacienteSeleccionado
                ]);

                foreach ($turnosOrdenados as $indice => $datosTurno) {
                    $actividadPaciente->turnos()->create([
                        'nro_turno' => $indice + 1,
                        'fecha_hora' => Carbon::parse($datosTurno['fecha'] . ' ' . $datosTurno['hora'])
                    ]);
                }
            });

            return redirect()->route('actividades-pacientes.inicio')->with('exito', '¡Turnos registrados con éxito!');
        } catch (\Throwable $th) {
            Log::error('[(Livewire) pacientes-casuales.turnos.crear@almacenarTurnos] Error al registrar los turnos del paciente.', ['excepción' => $th->getMessage()]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    private function maximoTurnos(): int
    {
        $max = $this->esClasePrueba ? 1 : count($this->diasSemana);
        return max($max, 1);
    }
};
?>

<div class="contenedor max-w-3xl">
    <form class="formulario" wire:submit.prevent="almacenarTurnos">
        <h2 class="titulo-formulario">Registro directo de Turnos</h2>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        <div class="mb-4">
            <x-buscador-livewire
                :busqueda="$busqueda"
                :idSeleccionado="$idPacienteSeleccionado"
                :sugerencias="$this->resultadosBusqueda"
                etiquetaBuscador="Buscar Paciente"
                campoError="idPacienteSeleccionado"
            />
        </div>

        <div class="fila-formulario">
            <div class="columna-campo">
                <label class="etiqueta-formulario">Modalidad</label>
                <x-toggle
                    :activo="$esClasePrueba"
                    etiquetaIzquierda="Gympass"
                    etiquetaDerecha="Clase de prueba Pilates"
                    :colorActivo="$esClasePrueba ? 'bg-purple-600' : 'bg-emerald-600'"
                    :colorTextoActivo="$esClasePrueba ? 'text-purple-400' : 'text-emerald-400'"
                    wire:click="$set('esClasePrueba', {{ !$esClasePrueba ? 'true' : 'false' }})"
                />
            </div>

            <div class="columna-campo">
                <label class="etiqueta-formulario">Semana</label>
                <x-toggle
                    :activo="$proximaSemana"
                    etiquetaIzquierda="Actual"
                    etiquetaDerecha="Próxima"
                    wire:click="$set('proximaSemana', {{ !$proximaSemana ? 'true' : 'false' }})"
                />
            </div>
        </div>

        <div class="mt-6 relative">
            <h3 class="etiqueta-formulario">
                {{ $esClasePrueba ? 'Seleccione fecha y hora para la clase de prueba' : 'Seleccione fecha y hora para los turnos' }}
                <span class="ml-2 text-xs text-blue-400 animate-pulse" wire:loading wire:target="proximaSemana, esClasePrueba">Actualizando...</span>
            </h3>

            @foreach($turnosSeleccionados as $indice => $turno)
                <div class="fila-formulario" wire:key="turno-{{ $indice }}">
                    <div class="columna-campo">
                        <select class="entrada" @disabled(!$idPacienteSeleccionado) wire:model.live="turnosSeleccionados.{{ $indice }}.fecha">
                            <option value="" disabled selected>Seleccione una fecha</option>
                            @foreach($this->diasSemana as $valor => $dia)
                                @php
                                    $estaSeleccionada = collect($turnosSeleccionados)
                                        ->filter(fn($t, $i) => $i !== $indice)
                                        ->pluck('fecha')
                                        ->contains($valor);
                                @endphp
                                <option value="{{ $valor }}" @if($estaSeleccionada) disabled @endif>
                                    {{ $dia }}
                                </option>
                            @endforeach
                        </select>
                        @error('turnosSeleccionados.' . $indice . '.fecha')
                            <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="columna-campo">
                        <select class="entrada" @disabled(!$idPacienteSeleccionado || empty($turnosSeleccionados[$indice]['fecha'])) wire:model.live="turnosSeleccionados.{{ $indice }}.hora">
                            <option value="" disabled selected>Seleccione un horario</option>
                            @if(!empty($turnosSeleccionados[$indice]['fecha']))
                                @foreach($this->turnosDisponibles as $turnoStr)
                                    @if(str_starts_with($turnoStr, $turnosSeleccionados[$indice]['fecha']))
                                        @php $horaStr = substr($turnoStr, 11, 5); @endphp
                                        <option value="{{ $horaStr }}">
                                            {{ $horaStr }} hs
                                        </option>
                                    @endif
                                @endforeach
                            @endif
                        </select>
                        @error('turnosSeleccionados.' . $indice . '.hora')
                            <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
                        @enderror
                    </div>

                    @if(count($turnosSeleccionados) > 1)
                        <button type="button" class="px-2 text-red-500" wire:click="removerFilaTurno({{ $indice }})">✕</button>
                    @endif
                </div>
            @endforeach
            @error('turnosSeleccionados')
                <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
            @enderror

            @if(count($turnosSeleccionados) < $this->maximoTurnos())
                <button
                    type="button"
                    class="mt-2 flex items-center gap-1 text-blue-400 hover:text-blue-300 font-bold transition-colors disabled:opacity-50"
                    @disabled(!$idPacienteSeleccionado)
                    wire:click="agregarNuevoTurno"
                    wire:loading.attr="disabled"
                >
                    <span class="text-xl">+</span> Agregar otro turno
                </button>
            @endif
        </div>

        <button type="submit" class="boton-registrar" @disabled(!$idPacienteSeleccionado)>Registrar turnos</button>
    </form>
</div>
