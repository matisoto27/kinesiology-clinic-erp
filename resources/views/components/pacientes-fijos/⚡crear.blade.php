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

    public bool $esDual = false;

    public string $fechaUltimoTurno = 'Sin datos';

    public ?string $fechaUltimoTurnoPilates = null;

    public ?int $frecuenciaSemanal = null;

    public ?int $frecuenciaSemanalPilates = null;

    public ?int $frecuenciaTotalDual = null;

    public array $turnos = [];

    public array $turnosPilates = [];

    public array $parametrosDisponibles = ['id_actividad' => null, 'id_paciente' => null, 'fecha' => null];

    public array $parametrosDisponiblesDual = ['id_actividad' => null, 'id_paciente' => null, 'fecha' => null];

    #[Computed]
    public function turnosPorDia(): array
    {
        if (!$this->parametrosDisponibles['id_actividad'] || !$this->parametrosDisponibles['id_paciente'] || !$this->parametrosDisponibles['fecha']) {
            return [];
        }

        return $this->agruparTurnosPorDia(
            Actividad::find($this->parametrosDisponibles['id_actividad']),
            $this->parametrosDisponibles['id_paciente'],
            Carbon::parse($this->parametrosDisponibles['fecha'])
        );
    }

    #[Computed]
    public function turnosPorDiaPilates(): array
    {
        if (!$this->parametrosDisponiblesDual['id_actividad'] || !$this->parametrosDisponiblesDual['id_paciente'] || !$this->parametrosDisponiblesDual['fecha']) {
            return [];
        }

        return $this->agruparTurnosPorDia(
            Actividad::find($this->parametrosDisponiblesDual['id_actividad']),
            $this->parametrosDisponiblesDual['id_paciente'],
            Carbon::parse($this->parametrosDisponiblesDual['fecha'])
        );
    }

    #[Computed]
    public function inscripcionesElegibles()
    {
        return ActividadPaciente::query()
            ->select('id', 'id_actividad', 'id_paciente', 'fecha_comienzo', 'cant_sesiones', 'frecuencia_total_dual', 'id_act_pac_dual', 'plan_dual_pendiente')
            ->with([
                'actividad' => fn ($consulta) => $consulta->select('id', 'nombre')->with('horarios:id,hora_inicio'),
                'pacienteRegular:id,nombre,apellido',
                'primerTurno:turnos.id,turnos.id_act_pac,turnos.fecha_hora',
                'ultimoTurno:turnos.id,turnos.id_act_pac,turnos.fecha_hora'
            ])
            ->whereHas('actividad', fn ($consulta) => $consulta->where('id_tipo_actividad', Actividad::TIPO_GENERAL))
            ->tienePacienteRegular()
            ->noFijos()
            ->conUltimoTurnoVigente()
            ->get();
    }

    #[Computed]
    public function opcionesInscripcion(): Collection
    {
        $inscripciones = $this->inscripcionesElegibles;
        $opciones = collect();
        $inscripcionesDualesProcesadas = [];

        foreach ($inscripciones as $inscripcion) {
            if ($inscripcion->esDualCompleto()) {
                if (in_array($inscripcion->id, $inscripcionesDualesProcesadas, true)) {
                    continue;
                }

                $pareja = $inscripciones->firstWhere('id', $inscripcion->id_act_pac_dual);

                if (!$pareja) {
                    continue;
                }

                $inscripcionesDualesProcesadas[] = $inscripcion->id;
                $inscripcionesDualesProcesadas[] = $pareja->id;

                $idsOrdenados = collect([$inscripcion->id, $pareja->id])->sort()->values();
                $inscripcionGym = (int) $inscripcion->id_actividad === Actividad::GIMNASIO ? $inscripcion : $pareja;
                $inscripcionPilates = (int) $inscripcionGym->id === (int) $inscripcion->id ? $pareja : $inscripcion;
                $fechaInicio = collect([
                    $inscripcionGym->primerTurno?->fecha_hora,
                    $inscripcionPilates->primerTurno?->fecha_hora,
                ])
                ->filter()
                ->min()
                ->format('d-m-Y');

                $opciones->push([
                    'valor' => 'dual:' . $idsOrdenados->implode(':'),
                    'etiqueta' => sprintf(
                        '%s - Dual Gym+Pilates (x%d) - 1er Turno: %s',
                        $inscripcion->ap_nom_paciente,
                        $inscripcion->frecuencia_total_dual,
                        $fechaInicio
                    ),
                    'es_dual' => true,
                    'inscripcion_gym' => $inscripcionGym,
                    'inscripcion_pilates' => $inscripcionPilates,
                ]);

                continue;
            }

            if ($inscripcion->plan_dual_pendiente) {
                continue;
            }

            $opciones->push([
                'valor' => (string) $inscripcion->id,
                'etiqueta' => sprintf(
                    '%s - %s - 1er Turno: %s',
                    $inscripcion->ap_nom_paciente,
                    $inscripcion->nombre_actividad,
                    $inscripcion->primerTurno?->fecha_hora->format('d-m-Y') ?? $inscripcion->fecha_comienzo->format('d-m-Y')
                ),
                'es_dual' => false,
                'inscripcion' => $inscripcion,
            ]);
        }

        return $opciones;
    }

    public function updatedInscripcionSeleccionada(): void
    {
        $this->resetErrorBag();
        $this->turnos = [];
        $this->turnosPilates = [];
        $this->frecuenciaSemanalPilates = null;
        $this->frecuenciaTotalDual = null;
        $this->fechaUltimoTurnoPilates = null;
        $this->limpiarParametrosDisponibles();

        if (str_starts_with($this->inscripcionSeleccionada, 'dual:')) {
            $this->esDual = true;
            $this->configurarSeleccionDual();
            return;
        }

        $this->esDual = false;
        $this->configurarSeleccionSimple();
    }

    public function updatedTurnos($value, $key): void
    {
        if (str_contains($key, '.dia_semana')) {
            $indice = explode('.', $key)[0];
            $this->turnos[$indice]['hora_inicio'] = '';

            if ($this->diaYaOcupado('turnos', $indice, $this->turnos[$indice]['dia_semana'] ?? '')) {
                $this->turnos[$indice]['dia_semana'] = '';
                $this->addError(
                    "turnos.{$indice}.dia_semana",
                    'Este día ya está asignado en otro turno.'
                );
            }
        }
    }

    public function updatedTurnosPilates($value, $key): void
    {
        if (str_contains($key, '.dia_semana')) {
            $indice = explode('.', $key)[0];
            $this->turnosPilates[$indice]['hora_inicio'] = '';

            if ($this->diaYaOcupado('turnosPilates', $indice, $this->turnosPilates[$indice]['dia_semana'] ?? '')) {
                $this->turnosPilates[$indice]['dia_semana'] = '';
                $this->addError(
                    "turnosPilates.{$indice}.dia_semana",
                    'Este día ya está asignado en otro turno.'
                );
            }
        }
    }

    public function almacenar()
    {
        if ($this->esDual) {
            return $this->almacenarDual();
        }

        return $this->almacenarSimple();
    }

    private function almacenarSimple()
    {
        $this->validate([
            'inscripcionSeleccionada' => 'required',
            'turnos.*.dia_semana' => 'required',
            'turnos.*.hora_inicio' => 'required',
        ]);

        $opcion = $this->opcionesInscripcion->firstWhere('valor', $this->inscripcionSeleccionada);
        $inscripcion = $opcion['inscripcion'] ?? null;

        if (!$inscripcion || !$this->inscripcionTieneTurnosVigentes($inscripcion)) {
            session()->flash('error', 'La inscripción seleccionada ya no está disponible o no es válida.');
            return;
        }

        $idPacienteFijo = null;

        try {
            $idPacienteFijo = DB::transaction(function () use ($inscripcion) {
                $pacienteFijo = PacienteFijo::create([
                    'id_actividad' => $inscripcion->id_actividad,
                    'id_paciente' => $inscripcion->id_paciente,
                ]);
                $pacienteFijo->horarios()->createMany($this->turnos);

                return $pacienteFijo->id;
            });

            Artisan::call('app:generar-turnos-mensuales', [
                '--id_paciente_fijo' => $idPacienteFijo,
            ]);

            return redirect()->route('inicio')->with('exito', 'El paciente ha sido marcado como paciente fijo. A partir de ahora, los turnos mensuales se generarán automáticamente.');
        } catch (\Throwable $ex) {
            Log::error('[(ComponenteLivewire)PacienteFijo@almacenarSimple] Error al marcar el paciente como paciente fijo.', ['excepcion' => $ex->getMessage()]);

            if ($idPacienteFijo) {
                PacienteFijo::find($idPacienteFijo)?->delete();
            }

            session()->flash('error', 'No pudimos procesar la solicitud. Por favor, intente de nuevo más tarde o contacte al equipo de soporte (Matías).');
        }
    }

    private function almacenarDual()
    {
        $this->validate([
            'inscripcionSeleccionada' => 'required',
            'turnos.*.dia_semana' => 'required',
            'turnos.*.hora_inicio' => 'required',
            'turnosPilates.*.dia_semana' => 'required',
            'turnosPilates.*.hora_inicio' => 'required',
        ]);

        if ($this->validarDiasRepetidosEnSubmit()) {
            return;
        }

        $opcion = $this->opcionesInscripcion->firstWhere('valor', $this->inscripcionSeleccionada);
        $inscripcionGym = $opcion['inscripcion_gym'] ?? null;
        $inscripcionPilates = $opcion['inscripcion_pilates'] ?? null;

        if (
            !$inscripcionGym
            || !$inscripcionPilates
            || !$this->inscripcionTieneTurnosVigentes($inscripcionGym)
            || !$this->inscripcionTieneTurnosVigentes($inscripcionPilates)
        ) {
            session()->flash('error', 'El plan dual seleccionado ya no está disponible o no es válido.');
            return;
        }

        $idsPacientesFijos = [];

        try {
            $idLider = DB::transaction(function () use ($inscripcionGym, $inscripcionPilates, &$idsPacientesFijos) {
                $pacienteFijoGym = PacienteFijo::create([
                    'id_actividad' => $inscripcionGym->id_actividad,
                    'id_paciente' => $inscripcionGym->id_paciente,
                ]);
                $pacienteFijoGym->horarios()->createMany($this->turnos);

                $pacienteFijoPilates = PacienteFijo::create([
                    'id_actividad' => $inscripcionPilates->id_actividad,
                    'id_paciente' => $inscripcionPilates->id_paciente,
                ]);
                $pacienteFijoPilates->horarios()->createMany($this->turnosPilates);

                $pacienteFijoGym->update(['id_pac_fijo_dual' => $pacienteFijoPilates->id]);
                $pacienteFijoPilates->update(['id_pac_fijo_dual' => $pacienteFijoGym->id]);

                $idsPacientesFijos = [$pacienteFijoGym->id, $pacienteFijoPilates->id];

                return min($pacienteFijoGym->id, $pacienteFijoPilates->id);
            });

            Artisan::call('app:generar-turnos-mensuales', [
                '--id_paciente_fijo' => $idLider,
            ]);

            return redirect()->route('inicio')->with('exito', 'La inscripción dual del paciente ha sido registrada como recurrente. A partir de ahora, los turnos mensuales se generarán automáticamente.');
        } catch (\Throwable $ex) {
            Log::error('[(ComponenteLivewire)PacienteFijo@almacenarDual] Error al marcar el plan dual como paciente fijo.', ['excepcion' => $ex->getMessage()]);

            DB::transaction(function () use ($idsPacientesFijos) {
                PacienteFijo::whereIn('id', $idsPacientesFijos)->delete();
            });

            session()->flash('error', 'No pudimos procesar la solicitud. Por favor, intente de nuevo más tarde o contacte al equipo de soporte (Matías).');
        }
    }

    private function configurarSeleccionSimple(): void
    {
        $opcion = $this->opcionesInscripcion->firstWhere('valor', $this->inscripcionSeleccionada);
        $inscripcion = $opcion['inscripcion'] ?? null;

        if (!$inscripcion?->ultimoTurno) {
            return;
        }

        $this->fechaUltimoTurno = $inscripcion->ultimoTurno->fecha_hora->format('d/m/Y H:i');
        $this->frecuenciaSemanal = (int) ($inscripcion->cant_sesiones / 4);
        $this->turnos = $this->inicializarTurnos($this->frecuenciaSemanal);
        $this->parametrosDisponibles = [
            'id_actividad' => $inscripcion->id_actividad,
            'id_paciente' => $inscripcion->id_paciente,
            'fecha' => $inscripcion->ultimoTurno->fecha_hora->toDateTimeString(),
        ];
        $this->parametrosDisponiblesDual = ['id_actividad' => null, 'id_paciente' => null, 'fecha' => null];
        unset($this->turnosPorDia, $this->turnosPorDiaPilates);
    }

    private function configurarSeleccionDual(): void
    {
        $opcion = $this->opcionesInscripcion->firstWhere('valor', $this->inscripcionSeleccionada);

        if (!$opcion) {
            return;
        }

        $inscripcionGym = $opcion['inscripcion_gym'];
        $inscripcionPilates = $opcion['inscripcion_pilates'];

        $this->frecuenciaTotalDual = $inscripcionGym->frecuencia_total_dual;
        $this->frecuenciaSemanal = $inscripcionGym->frecuenciaSemanal();
        $this->frecuenciaSemanalPilates = $inscripcionPilates->frecuenciaSemanal();

        $this->fechaUltimoTurno = $inscripcionGym->ultimoTurno->fecha_hora->format('d/m/Y H:i');
        $this->fechaUltimoTurnoPilates = $inscripcionPilates->ultimoTurno->fecha_hora->format('d/m/Y H:i');

        $this->turnos = $this->inicializarTurnos($this->frecuenciaSemanal);
        $this->turnosPilates = $this->inicializarTurnos($this->frecuenciaSemanalPilates);

        $this->parametrosDisponibles = [
            'id_actividad' => $inscripcionGym->id_actividad,
            'id_paciente' => $inscripcionGym->id_paciente,
            'fecha' => $inscripcionGym->ultimoTurno->fecha_hora->toDateTimeString(),
        ];
        $this->parametrosDisponiblesDual = [
            'id_actividad' => $inscripcionPilates->id_actividad,
            'id_paciente' => $inscripcionPilates->id_paciente,
            'fecha' => $inscripcionPilates->ultimoTurno->fecha_hora->toDateTimeString(),
        ];

        unset($this->turnosPorDia, $this->turnosPorDiaPilates);
    }

    private function inicializarTurnos(int $frecuenciaSemanal): array
    {
        $turnos = [];

        for ($i = 1; $i <= $frecuenciaSemanal; $i++) {
            $turnos[$i] = [
                'dia_semana' => '',
                'hora_inicio' => '',
            ];
        }

        return $turnos;
    }

    private function agruparTurnosPorDia(Actividad $actividad, int $idPaciente, Carbon $fechaUltimoTurno): array
    {
        return $actividad->turnosDisponiblesMes($idPaciente, $fechaUltimoTurno)
            ->groupBy(fn ($fechaHora) => (string) Carbon::parse($fechaHora)->dayOfWeekIso)
            ->map(function ($fechasDelDia) {
                return $fechasDelDia->groupBy(fn ($fecha) => Carbon::parse($fecha)->format('H:i'))
                    ->sortKeys()
                    ->map(function ($fechasConEsaHora, $hora) {
                        $disponibles = $fechasConEsaHora->count();

                        return [
                            'hora' => $hora,
                            'label' => "{$hora}hs {$disponibles}/4 disponibles",
                            'valor' => $hora,
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->all();
    }

    private function limpiarParametrosDisponibles(): void
    {
        $this->parametrosDisponibles = ['id_actividad' => null, 'id_paciente' => null, 'fecha' => null];
        $this->parametrosDisponiblesDual = ['id_actividad' => null, 'id_paciente' => null, 'fecha' => null];
        unset($this->turnosPorDia, $this->turnosPorDiaPilates);
    }

    private function inscripcionTieneTurnosVigentes(ActividadPaciente $inscripcion): bool
    {
        return $inscripcion->ultimoTurno && $inscripcion->ultimoTurno->fecha_hora->gt(now());
    }

    public function diasOcupados(string $prefijoModelo, int|string $indice): array
    {
        $turnosGym = $prefijoModelo === 'turnos'
            ? collect($this->turnos)->except($indice)
            : collect($this->turnos);

        $turnosPilates = $prefijoModelo === 'turnosPilates'
            ? collect($this->turnosPilates)->except($indice)
            : collect($this->turnosPilates);

        return $turnosGym
            ->merge($turnosPilates)
            ->pluck('dia_semana')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function validarDiasRepetidosEnSubmit(): bool
    {
        $diasVistos = [];
        $tieneErrores = false;

        foreach ($this->turnos as $indice => $turno) {
            $dia = $turno['dia_semana'] ?? '';

            if ($dia === '') {
                continue;
            }

            if (isset($diasVistos[$dia])) {
                $this->addError("turnos.{$indice}.dia_semana", 'Este día ya está asignado en otro turno.');
                $tieneErrores = true;
                continue;
            }

            $diasVistos[$dia] = true;
        }

        foreach ($this->turnosPilates as $indice => $turno) {
            $dia = $turno['dia_semana'] ?? '';

            if ($dia === '') {
                continue;
            }

            if (isset($diasVistos[$dia])) {
                $this->addError("turnosPilates.{$indice}.dia_semana", 'Este día ya está asignado en otro turno.');
                $tieneErrores = true;
                continue;
            }

            $diasVistos[$dia] = true;
        }

        return $tieneErrores;
    }

    private function diaYaOcupado(string $prefijoModelo, int|string $indice, string $dia): bool
    {
        return $dia !== '' && in_array($dia, $this->diasOcupados($prefijoModelo, $indice), true);
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
                @foreach ($this->opcionesInscripcion as $opcion)
                    <option value="{{ $opcion['valor'] }}">{{ $opcion['etiqueta'] }}</option>
                @endforeach
            </select>
        </div>

        @if ($esDual)
            <div class="mb-4 columna-campo">
                <h3 class="etiqueta-formulario">Plan dual</h3>
                <p class="entrada-info">Frecuencia total: {{ $frecuenciaTotalDual ?? 'Sin datos' }}</p>
            </div>

            <div class="mb-6 p-4 border border-white/10 rounded-lg">
                <h3 class="etiqueta-formulario mb-2">Gimnasio</h3>
                <p class="entrada-info mb-2">Último turno: {{ $fechaUltimoTurno }}</p>
                <p class="entrada-info mb-4">Frecuencia semanal: {{ $frecuenciaSemanal ?? 'Sin datos' }}</p>

                @for ($i = 1; $i <= (int) $frecuenciaSemanal; $i++)
                    @include('components.pacientes-fijos.partials.fila-horario-fijo', [
                        'indice' => $i,
                        'prefijoModelo' => 'turnos',
                        'diasOcupados' => $this->diasOcupados('turnos', $i),
                        'diaSeleccionado' => (string) ($turnos[$i]['dia_semana'] ?? ''),
                        'turnosPorDia' => $this->turnosPorDia,
                    ])
                @endfor
            </div>

            <div class="mb-6 p-4 border border-white/10 rounded-lg">
                <h3 class="etiqueta-formulario mb-2">Pilates</h3>
                <p class="entrada-info mb-2">Último turno: {{ $fechaUltimoTurnoPilates }}</p>
                <p class="entrada-info mb-4">Frecuencia semanal: {{ $frecuenciaSemanalPilates ?? 'Sin datos' }}</p>

                @for ($i = 1; $i <= (int) $frecuenciaSemanalPilates; $i++)
                    @include('components.pacientes-fijos.partials.fila-horario-fijo', [
                        'indice' => $i,
                        'prefijoModelo' => 'turnosPilates',
                        'diasOcupados' => $this->diasOcupados('turnosPilates', $i),
                        'diaSeleccionado' => (string) ($turnosPilates[$i]['dia_semana'] ?? ''),
                        'turnosPorDia' => $this->turnosPorDiaPilates,
                    ])
                @endfor

                @error('turnosPilates')
                    <span class="mt-1 text-red-500 text-sm italic">{{ $message }}</span>
                @enderror
            </div>
        @else
            <div class="mb-4 columna-campo">
                <h3 class="etiqueta-formulario">Fecha último turno</h3>
                <p class="entrada-info">{{ $fechaUltimoTurno }}</p>
            </div>

            <div class="mb-4 columna-campo">
                <h3 class="etiqueta-formulario">Frecuencia semanal</h3>
                <p class="entrada-info">{{ $frecuenciaSemanal ?? 'Sin datos' }}</p>
            </div>

            @for ($i = 1; $i <= (int) $frecuenciaSemanal; $i++)
                @include('components.pacientes-fijos.partials.fila-horario-fijo', [
                    'indice' => $i,
                    'prefijoModelo' => 'turnos',
                    'diasOcupados' => $this->diasOcupados('turnos', $i),
                    'diaSeleccionado' => (string) ($turnos[$i]['dia_semana'] ?? ''),
                    'turnosPorDia' => $this->turnosPorDia,
                ])
            @endfor
        @endif

        <button type="submit" class="boton-registrar">Registrar</button>
    </form>
</div>
