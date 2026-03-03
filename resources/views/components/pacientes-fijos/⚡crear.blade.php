<?php

use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $inscripcionSeleccionada = '';

    public string $fechaUltimoTurno = 'Sin datos';

    public ?int $frecuenciaSemanal;

    public array $diasSeleccionados = [];

    public array $turnos = [];

    public Collection $turnosPorDia;

    #[Computed]
    public function inscripciones()
    {
        return ActividadPaciente::select('id', 'id_actividad', 'id_paciente', 'fecha_comienzo', 'cant_sesiones')
            ->whereHas('actividad', function ($consulta) {
                $consulta->where('id_tipo_actividad', Actividad::TIPO_GENERAL);
            })
            ->with([
                'actividad' => function ($consulta) {
                    $consulta->select('id', 'nombre')->with('horarios:id,hora_inicio');
                },
                'paciente:id,nombre,apellido',
                'ultimoTurno:turnos.id,turnos.id_act_pac,turnos.fecha_hora'
            ])
            ->noFijos()
            ->get();
    }

    public function updatedInscripcionSeleccionada()
    {
        $inscripcion = $this->inscripciones->firstWhere('id', $this->inscripcionSeleccionada);
        $fechaUltimoTurno = $inscripcion->ultimoTurno->fecha_hora;

        $this->fechaUltimoTurno = $fechaUltimoTurno->format('d/m/Y H:i');

        $frecuencia = $inscripcion->cant_sesiones / 4;

        $this->frecuenciaSemanal = $frecuencia;
        $this->turnos = [];
        for ($i = 1; $i <= $frecuencia; $i++) {
            $this->turnos[$i] = [
                'dia_semana' => '',
                'hora_inicio' => ''
            ];
        }

        $turnosDisponibles = Actividad::find($inscripcion->id_actividad)->turnosDisponiblesMes($inscripcion->id_paciente, $fechaUltimoTurno);

        $this->turnosPorDia = $turnosDisponibles->groupBy(function($fechaHora) {
                return Carbon::parse($fechaHora)->dayOfWeekIso;
            })
            ->map(function ($fechasDelDia) {
                return $fechasDelDia->groupBy(function ($fecha) {
                    return Carbon::parse($fecha)->format('H:i');
                })
                ->sortKeys()
                ->map(function ($fechasConEsaHora, $hora) {
                    $disponibles = $fechasConEsaHora->count();

                    return [
                        'hora'  => $hora,
                        'label' => "{$hora}hs {$disponibles}/4 disponibles",
                        'valor' => $hora
                    ];
                })
                ->values();
            });
    }

    public function updatedTurnos($value, $key)
    {
        if (str_contains($key, '.dia_semana')) {
            $indice = explode('.', $key)[0];
            $this->turnos[$indice]['hora_inicio'] = '';
            $this->diasSeleccionados = array_column($this->turnos, 'dia_semana');
        }
    }

    public function almacenar()
    {
        $this->validate([
            'inscripcionSeleccionada' => 'required',
            'turnos.*.dia_semana' => 'required',
            'turnos.*.hora_inicio' => 'required',
        ]);

        $inscripcion = $this->inscripciones->firstWhere('id', $this->inscripcionSeleccionada);
        if (!$inscripcion) {
            session()->flash('error', 'La inscripción seleccionada ya no está disponible o no es válida.');
            return;
        }

        $idPacienteFijo = null;
        try {
            $idPacienteFijo = DB::transaction(function () use ($inscripcion) {
                $pacienteFijo = new PacienteFijo();
                $pacienteFijo->id_actividad = $inscripcion->id_actividad;
                $pacienteFijo->id_paciente = $inscripcion->id_paciente;
                $pacienteFijo->save();
                $pacienteFijo->horarios()->createMany($this->turnos);

                return $pacienteFijo->id;
            });

            Artisan::call('app:generar-turnos-mensuales', [
                '--id_paciente_fijo' => $idPacienteFijo
            ]);

            return redirect()->route('inicio')->with('exito', 'El paciente ha sido marcado como paciente recurrente. A partir de ahora, los turnos mensuales se generarán automáticamente.');

        } catch (\Throwable $ex) {
            Log::error('[(ComponenteLivewire)PacienteFijo@almacenar] Error al marcar el paciente como paciente recurrente.', ['excepcion' => $ex->getMessage()]);
            if (isset($idPacienteFijo)) {
                PacienteFijo::find($idPacienteFijo)->delete();
            }

            session()->flash('error', 'No pudimos procesar la solicitud. Por favor, intente de nuevo más tarde o contacte al equipo de soporte (Matías).');
        }
    }
};
?>

<div class="contenedor max-w-screen-sm">
    <form class="formulario" wire:submit.prevent="almacenar">
        <x-alerta tipo="error" />

        <h1 class="titulo-formulario">Registrar nuevo paciente fijo</h1>

        <div class="mb-4 columna-campo">
            <label for="select-inscripcion" class="etiqueta-formulario">Inscripción</label>
            <select id="select-inscripcion" class="entrada" wire:model.live="inscripcionSeleccionada" required>
                <option value="" disabled selected>Seleccione una inscripción</option>
                @foreach ($this->inscripciones as $insc)
                    <option value="{{ $insc->id }}">{{ $insc->paciente->nombre_completo }} - {{ $insc->actividad->nombre }} (Inicio: {{ $insc->fecha_comienzo->format('d-m-Y') }})</option>
                @endforeach
            </select>
        </div>

        <div class="mb-4 columna-campo">
            <h3 class="etiqueta-formulario">Fecha último turno</h3>
            <p class="entrada-info">{{ $fechaUltimoTurno }}</p>
        </div>

        <div class="mb-4 columna-campo">
            <h3 class="etiqueta-formulario">Frecuencia semanal</h3>
            <p class="entrada-info">{{ $frecuenciaSemanal ?? 'Sin datos' }}</p>
        </div>

        @for ($i = 1; $i <= (int) $frecuenciaSemanal; $i++)
            <div class="fila-formulario" wire:key="turno-{{ $i }}">
                <h2 class="text-white text-lg font-medium">Turno {{ $i }}</h2>

                <div class="columna-campo">
                    <label for="dia-select-{{ $i }}" class="etiqueta-formulario">Día de la semana</label>
                    <select id="dia-select-{{ $i }}" class="entrada" wire:model.live="turnos.{{ $i }}.dia_semana" required>
                        <option value="" disabled selected>Seleccione un día</option>
                        <option value="1" {{ in_array('1', $diasSeleccionados) ? 'disabled' : '' }}>Lunes</option>
                        <option value="2" {{ in_array('2', $diasSeleccionados) ? 'disabled' : '' }}>Martes</option>
                        <option value="3" {{ in_array('3', $diasSeleccionados) ? 'disabled' : '' }}>Miércoles</option>
                        <option value="4" {{ in_array('4', $diasSeleccionados) ? 'disabled' : '' }}>Jueves</option>
                        <option value="5" {{ in_array('5', $diasSeleccionados) ? 'disabled' : '' }}>Viernes</option>
                    </select>
                </div>

                <div class="columna-campo">
                    <label for="hora-select-{{ $i }}" class="etiqueta-formulario">Hora de inicio</label>
                    <select
                        id="hora-select-{{ $i }}"
                        class="entrada"
                        wire:model="turnos.{{ $i }}.hora_inicio"
                        {{ empty($turnos[$i]['dia_semana']) ? 'disabled' : '' }}
                        required
                    >
                        <option value="" disabled selected>Seleccione hora de inicio</option>
                        @php
                            $diaSeleccionado = $turnos[$i]['dia_semana'];
                        @endphp
                        @if($diaSeleccionado && isset($turnosPorDia[$diaSeleccionado]))
                            @foreach($turnosPorDia[$diaSeleccionado] as $opcion)
                                <option value="{{ $opcion['valor'] }}">{{ $opcion['label'] }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
        @endfor

        <button type="submit" class="boton-registrar">Registrar</button>
    </form>
</div>
