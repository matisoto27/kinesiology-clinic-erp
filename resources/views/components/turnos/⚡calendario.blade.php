<?php

use App\Models\NotaTurno;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public Collection $tiposActividad;

    #[Url(as: 'tipo')]
    public int $idTipoActividad = 0;

    #[Url(as: 'actividad')]
    public int $idActividad = 0;

    #[Url(as: 'horario')]
    public int $nroHorario = 0;

    #[Url(as: 'semana')]
    public int $cantidadSemanas = 0;

    public ?int $idTurnoSeleccionado = null;
    public bool $mostrarModalNotas = false;
    public bool $mostrarModalAgregar = false;
    public string $contenidoNuevaNota = '';

    protected array $horariosMaestros = [
        '1_1' => ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00'],
        '1_2' => ['16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00'],
        '2_1' => ['08:00', '09:00', '10:00', '11:00'],
        '2_2' => ['16:00', '17:00', '18:00', '19:00'],
        '3_1' => ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30'],
        '3_2' => ['16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30']
    ];

    private const COLORES_ACTIVIDADES = [
        1 => 'bg-emerald-600', // Gimnasio
        2 => 'bg-purple-600' // Pilates
    ];

    private const COLORES_PACIENTES = [
        'bg-red-400',
        'bg-red-500',
        'bg-red-600',
        'bg-red-700',
        'bg-red-800',
        'bg-red-900'
    ];

    public function cambiarSemana($valor) { $this->cantidadSemanas += $valor; }

    public function updatedIdTipoActividad()
    {
        $this->idActividad = 0;
    }

    #[Computed]
    public function diaInicio()
    {
        return Carbon::now()->startOfWeek()->addWeeks($this->cantidadSemanas);
    }

    #[Computed]
    public function actividadesFiltradas()
    {
        if ($this->idTipoActividad === 0) {
            return collect();
        }

        $tipo = $this->tiposActividad->firstWhere('id', $this->idTipoActividad);
        return $tipo ? $tipo->actividades : collect();
    }

    #[Computed]
    public function horarios()
    {
        $horariosFinales = [];

        $idTipo = $this->idTipoActividad;
        $idActividad = $this->idActividad;
        $nroHorario = $this->nroHorario;

        if ($idActividad > 0) {
            if ($idActividad > 2) {
                if ($nroHorario === 0 || $nroHorario === 1) {
                    $horariosFinales = array_merge($horariosFinales, $this->horariosMaestros['3_1']);
                }
                if ($nroHorario === 0 || $nroHorario === 2) {
                    $horariosFinales = array_merge($horariosFinales, $this->horariosMaestros['3_2']);
                }
            } else {
                foreach ($this->horariosMaestros as $clave => $listaHorarios) {
                    if (str_starts_with($clave, "{$idActividad}_")) {
                        if ($nroHorario === 0 || str_ends_with($clave, "_{$nroHorario}")) {
                            $horariosFinales = array_merge($horariosFinales, $listaHorarios);
                        }
                    }
                }
            }            
        } elseif ($idTipo > 0) {
            $idsActividadesPermitidas = ($idTipo === 1) ? [1, 2] : [3];
            foreach ($this->horariosMaestros as $clave => $listaHorarios) {
                $idActClave = (int) explode('_', $clave)[0];
                if (in_array($idActClave, $idsActividadesPermitidas)) {
                    if ($nroHorario === 0 || str_ends_with($clave, "_{$nroHorario}")) {
                        $horariosFinales = array_merge($horariosFinales, $listaHorarios);
                    }
                }
            }
        } else {
            foreach ($this->horariosMaestros as $clave => $listaHorarios) {
                if ($nroHorario === 0 || str_ends_with($clave, "_{$nroHorario}")) {
                    $horariosFinales = array_merge($horariosFinales, $listaHorarios);
                }
            }
        }

        $horariosFinales = array_unique($horariosFinales);
        sort($horariosFinales);

        return $horariosFinales;
    }

    #[Computed]
    public function turnos()
    {
        $diaInicio = $this->diaInicio; // Lunes
        $diaFin = $diaInicio->copy()->addDays(4); // Viernes

        $consulta = Turno::with(['actividadPaciente.actividad', 'actividadPaciente.pacienteRegular', 'actividadPaciente.pacienteCasual'])
            ->whereBetween('fecha_hora', [$diaInicio->startOfDay(), $diaFin->endOfDay()]);

        if ($this->idTipoActividad > 0) {
            $consulta->whereHas('actividadPaciente.actividad', fn($c) => $c->where('id_tipo_actividad', $this->idTipoActividad));
        }

        if ($this->idActividad > 0) {
            $consulta->whereHas('actividadPaciente', fn($c) => $c->where('id_actividad', $this->idActividad));
        }

        if ($this->nroHorario === 1) {
            $consulta->whereTime('fecha_hora', '>=', '08:00')->whereTime('fecha_hora', '<', '12:00');
        } elseif ($this->nroHorario === 2) {
            $consulta->whereTime('fecha_hora', '>=', '16:00')->whereTime('fecha_hora', '<', '20:00');
        }

        $turnos = $consulta->orderByDesc('created_at')->get();
        $turnosAgrupados = [];

        foreach (range(0, 4) as $diaSemana) {
            $fechaSemana = $diaInicio->copy()->addDays($diaSemana)->format('Y-m-d');

            foreach ($this->horarios() as $horaInicio) {
                $turnosAgrupados[$fechaSemana][$horaInicio] = $turnos->filter(fn($t) =>
                    $t->fecha_hora->format('Y-m-d') === $fechaSemana && $t->fecha_hora->format('H:i') === $horaInicio
                );
            }
        }

        return $turnosAgrupados;
    }

    #[Computed]
    public function turnoActual()
    {
        return $this->idTurnoSeleccionado
            ? Turno::with('notas')->find($this->idTurnoSeleccionado)
            : null;
    }

    public function obtenerColorTurno($turno, $indice): string
    {
        if ($this->idActividad === 0) {
            return self::COLORES_ACTIVIDADES[$turno->actividadPaciente->id_actividad] ?? 'bg-yellow-500'; // Color personalizado para Kinesiología
        }

        $colores = self::COLORES_PACIENTES;
        return $colores[$indice % count($colores)];
    }

    public function obtenerNotasDelTurno($idTurno)
    {
        $this->idTurnoSeleccionado = $idTurno;
        $this->mostrarModalNotas = true;
    }

    public function almacenarNota()
    {
        $this->validate(['contenidoNuevaNota' => 'required|min:2']);

        NotaTurno::create([
            'id_turno' => $this->idTurnoSeleccionado,
            'contenido' => $this->contenidoNuevaNota,
            'fecha_realizada' => Carbon::now()
        ]);

        $this->reset(['contenidoNuevaNota', 'mostrarModalAgregar']);
    }

    public function eliminarNota($idNota)
    {
        NotaTurno::where('id', $idNota)
            ->where('id_turno', $this->idTurnoSeleccionado)
            ->delete();
    }

    public function cerrarModales()
    {
        $this->reset(['idTurnoSeleccionado', 'mostrarModalNotas', 'mostrarModalAgregar', 'contenidoNuevaNota']);
    }

    public function render()
    {
        return $this->view([
            'turnosAgrupados' => $this->turnos,
            'diasSemana' => collect(range(0, 4))->map(fn($d) => $this->diaInicio->copy()->addDays($d)->format('Y-m-d'))
        ]);
    }
};
?>

<div class="contenedor max-w-screen-xl">
    <div class="h-26 px-5 flex items-center bg-[#014745] text-white">
        <div class="w-4/6 flex gap-2">
            <div class="flex flex-col">
                <label for="filtro-tipo" class="etiqueta-formulario">Tipo de actividad</label>
                <select
                    id="filtro-tipo"
                    class="entrada"
                    wire:model.live="idTipoActividad">
                    <option value="0">Todos los tipos</option>
                    @foreach($tiposActividad as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->descripcion }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col">
                <label for="filtro-actividad" class="etiqueta-formulario">Actividad</label>
                <select
                    id="filtro-actividad"
                    class="entrada"
                    @if($idTipoActividad === 0) disabled @endif
                    wire:model.live="idActividad">
                    <option value="0">Todas las actividades</option>
                    @foreach($this->actividadesFiltradas as $act)
                        <option value="{{ $act->id }}">{{ $act->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col">
                <label for="filtro-horario" class="etiqueta-formulario">Franja horaria</label>
                <select id="filtro-horario" class="entrada" wire:model.live="nroHorario">
                    <option value="0">Cualquier horario</option>
                    <option value="1">Turno mañana</option>
                    <option value="2">Turno tarde</option>
                </select>
            </div>
        </div>

        <div class="w-1/6 flex justify-center">
            <p class="font-bold text-3xl">{{ ucwords($this->diaInicio->isoFormat('MMMM YYYY')) }}</p>
        </div>

        <div class="w-1/6 flex justify-end gap-5">
            <button class="px-5 py-2 bg-[#3A8F8E] hover:bg-[#F5D500] rounded-lg transition duration-300" wire:click="cambiarSemana(-1)">
                <x-iconos.flecha-izquierda />
            </button>
            <button class="px-5 py-2 bg-[#3A8F8E] hover:bg-[#F5D500] rounded-lg transition duration-300" wire:click="cambiarSemana(1)">
                <x-iconos.flecha-derecha />
            </button>
        </div>
    </div>

    <div class="grid grid-cols-[128px_repeat(5,1fr)] border-black border divide-black divide-x divide-y">
        <div class="centrado-total h-14 bg-[#3A8F8E] text-white font-bold">Hora Ingreso</div>
        @foreach ($diasSemana as $diaSemana)
            <div class="centrado-total h-14 bg-[#3A8F8E] text-white font-bold">
                {{ ucwords(Carbon::parse($diaSemana)->translatedFormat('l d')) }}
            </div>
        @endforeach

        @foreach($this->horarios as $horaInicio)
            <div class="centrado-total min-h-[176px] py-4 bg-white text-gray-500 text-sm font-medium italic">
                {{ $horaInicio }} hs
            </div>

            @foreach ($diasSemana as $diaSemana)
                @php $turnos = $turnosAgrupados[$diaSemana][$horaInicio] ?? []; @endphp

                <div class="min-h-[176px] p-2 flex flex-col justify-center gap-1 bg-gray-50">
                    @foreach($turnos as $turno)
                        <button
                            class="{{ $this->obtenerColorTurno($turno, $loop->index) }} p-2 w-full flex flex-col items-start text-white text-xs leading-tight rounded shadow-sm hover:brightness-110 transition-all"
                            wire:click="obtenerNotasDelTurno({{ $turno->id }})"
                        >
                            <div class="flex items-center gap-1">
                                <span class="font-bold uppercase">
                                    {{ $turno->ap_nom_paciente }}
                                </span>
                                @if ($turno->actividadPaciente->esGympass())
                                    <span class="badge bg-white text-emerald-600">Gympass</span>
                                @endif
                                @if ($turno->actividadPaciente->esPruebaPilates())
                                    <span class="badge bg-white text-purple-600">Prueba</span>
                                @endif
                            </div>

                            @if ($turno->notas->count() > 0)
                                <div class="px-1.5 py-0.5 flex items-center gap-1 self-end bg-black/20 rounded">
                                    <x-iconos.comentario />
                                    <span class="text-xs font-bold">{{ $turno->notas->count() }}</span>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endforeach
        @endforeach
    </div>

    @if($mostrarModalNotas && $this->turnoActual)
        <div class="centrado-total fixed inset-0 bg-black/30 backdrop-blur-sm z-50">
            <div class="p-6 relative max-w-lg w-full bg-white rounded-2xl shadow-lg">
                <button
                    class="absolute top-3 right-3 text-gray-500 hover:text-gray-700"
                    wire:click="cerrarModales">
                    <x-iconos.cruz />
                </button>

                <h3 class="mb-4 text-gray-800 text-2xl font-semibold">Notas del turno</h3>

                <div class="p-6 max-h-[60vh] space-y-4 overflow-y-auto">
                    @forelse($this->turnoActual->notas as $nota)
                        <div class="p-3 flex justify-between items-start bg-gray-50 border-[#3A8F8E] border-l-4 rounded shadow-sm group">
                            <div>
                                <p class="text-gray-400 text-[10px] font-bold uppercase">{{ $nota->created_at->diffForHumans() }}</p>
                                <p class="text-gray-700 text-sm">{{ $nota->contenido }}</p>
                            </div>
                            <button
                                class="text-gray-300 group-hover:text-red-500 transition-colors"
                                wire:click="eliminarNota({{ $nota->id }})"
                                wire:confirm="¿Desea eliminar esta nota?">
                                <x-iconos.basura />
                            </button>
                        </div>
                    @empty
                        <p class="py-10 text-gray-400 text-center italic">Sin notas registradas.</p>
                    @endforelse
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        class="px-4 py-2 bg-[#3A8F8E] hover:bg-[#014745] text-white rounded-lg transition"
                        wire:click="$set('mostrarModalAgregar', true)">
                        Agregar nueva nota
                    </button>
                    <button
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition"
                        wire:click="cerrarModales">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($mostrarModalAgregar)
        <div class="centrado-total fixed inset-0 bg-black/30 backdrop-blur-sm z-60">
            <div class="relative p-6 max-w-lg w-full bg-white rounded-2xl shadow-lg">
                <h3 class="mb-4 text-gray-800 text-2xl font-semibold">Agregar nueva nota</h3>

                <div class="max-h-80 space-y-3 overflow-y-auto">
                    <textarea
                        rows="5"
                        placeholder="Ingrese el contenido de la nueva nota"
                        class="p-3 w-full border-gray-300 border text-sm rounded-lg resize-none"
                        wire:model="contenidoNuevaNota">
                    </textarea>
                    @error('contenidoNuevaNota')
                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <div class="p-4 bg-gray-50 flex justify-end gap-2 rounded-b-xl">
                    <button
                        class="px-4 py-2 bg-[#3A8F8E] hover:bg-[#014745] text-white rounded-lg transition"
                        wire:click="almacenarNota">
                        Registrar
                    </button>
                    <button
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition"
                        wire:click="$set('mostrarModalAgregar', false); $set('contenidoNuevaNota', '')">
                        Volver
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
