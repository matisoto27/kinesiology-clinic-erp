<?php

use App\Exceptions\ReglaNegocioException;
use App\Models\Actividad;
use App\Models\ActividadPaciente;
use App\Models\Paciente;
use App\Models\Turno;
use App\Services\ActividadPacienteService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $busqueda = '';
    public ?int $idPacienteSeleccionado = null;
    public ?int $idRegistroSesionesSeleccionado = null;

    public string $cantSesiones = '';
    public string $mesOrden = '';
    public string $diaOrden = '';
    public string $anioOrden = '';

    public bool $proximaSemana = false;
    public string $fechaTurno = '';
    public string $horaTurno = '';

    public string $mensajeExito = '';
    public bool $procesando = false;

    public function mount(): void
    {
        $this->anioOrden = (string) now()->year;

        $idPaciente = request()->integer('id_paciente');

        if ($idPaciente > 0) {
            $paciente = Paciente::query()->select('id', 'nombre', 'apellido')->find($idPaciente);

            if ($paciente) {
                $this->seleccionarSugerencia($paciente->id, $paciente->apellido_nombre);
            }
        }
    }

    protected function rules(): array
    {
        $fechaOrdenCargada = $this->mesOrden !== '' || $this->diaOrden !== '';

        if ($this->modoContinuar()) {
            return [
                'idPacienteSeleccionado' => 'required|integer|exists:pacientes,id',
                'idRegistroSesionesSeleccionado' => 'required|integer|exists:actividades_pacientes,id',
                'fechaTurno' => 'required|date_format:Y-m-d',
                'horaTurno' => 'required|date_format:H:i',
            ];
        }

        return [
            'idPacienteSeleccionado' => 'required|integer|exists:pacientes,id',
            'cantSesiones' => 'required|integer|in:5,10',
            'mesOrden' => [
                Rule::requiredIf($fechaOrdenCargada),
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],
            'diaOrden' => [
                Rule::requiredIf($fechaOrdenCargada),
                'nullable',
                'integer',
                'min:1',
                'max:31',
            ],
            'anioOrden' => [
                Rule::requiredIf($fechaOrdenCargada),
                'nullable',
                'integer',
                Rule::in($this->aniosDisponibles()),
            ],
            'fechaTurno' => 'required|date_format:Y-m-d',
            'horaTurno' => 'required|date_format:H:i',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'idPacienteSeleccionado' => 'paciente',
            'idRegistroSesionesSeleccionado' => 'registro de sesiones',
            'cantSesiones' => 'sesiones que cubre la orden',
            'mesOrden' => 'mes de la orden médica',
            'diaOrden' => 'día de la orden médica',
            'anioOrden' => 'año de la orden médica',
            'fechaTurno' => 'fecha del turno',
            'horaTurno' => 'hora del turno',
        ];
    }

    public function seleccionarSugerencia(int $id, string $apellidoNombre): void
    {
        $this->busqueda = $apellidoNombre;
        $this->idPacienteSeleccionado = $id;
        $this->reset([
            'cantSesiones',
            'mesOrden',
            'diaOrden',
            'fechaTurno',
            'horaTurno',
            'mensajeExito',
        ]);
        $this->anioOrden = (string) now()->year;
        $this->proximaSemana = false;
        $this->resetErrorBag();
        $this->sincronizarRegistroSesionesSeleccionado();
    }

    public function limpiarSeleccion(): void
    {
        $this->reset([
            'busqueda',
            'idPacienteSeleccionado',
            'idRegistroSesionesSeleccionado',
            'cantSesiones',
            'mesOrden',
            'diaOrden',
            'fechaTurno',
            'horaTurno',
            'mensajeExito',
            'proximaSemana',
        ]);
        $this->anioOrden = (string) now()->year;
        $this->resetErrorBag();
    }

    public function updatedProximaSemana(): void
    {
        $this->reset(['fechaTurno', 'horaTurno']);
    }

    public function updatedFechaTurno(): void
    {
        $this->horaTurno = '';
    }

    public function updatedMesOrden(): void
    {
        $this->validarDiaOrden();
    }

    public function updatedAnioOrden(): void
    {
        $this->validarDiaOrden();
    }

    public function almacenar(ActividadPacienteService $service): void
    {
        if ($this->procesando) {
            return;
        }

        $this->procesando = true;
        $this->mensajeExito = '';

        try {
            $this->validate();

            $fechaHora = $this->fechaTurno . ' ' . $this->horaTurno . ':00';

            if ($this->modoContinuar()) {
                $registroSesiones = $this->registroSesionesEnCurso();

                if ($registroSesiones === null || (int) $registroSesiones->id !== (int) $this->idRegistroSesionesSeleccionado) {
                    throw new ReglaNegocioException('El registro de sesiones seleccionado ya no está disponible.');
                }

                $turno = $service->agregarTurnoAKinesioConOrden($registroSesiones, $fechaHora);
                $agendados = $registroSesiones->turnos()->whereNull('id_turno_original')->count();
                $this->mensajeExito = sprintf(
                    'Turno %s registrado · %d/%d%s',
                    $turno->fecha_hora->format('d/m/Y H:i'),
                    $agendados,
                    $registroSesiones->cant_sesiones,
                    $agendados >= (int) $registroSesiones->cant_sesiones ? ' · Registro completo' : ''
                );
            } else {
                $registroSesiones = $service->abrirKinesioConOrden(
                    (int) $this->idPacienteSeleccionado,
                    (int) $this->cantSesiones,
                    $fechaHora,
                    $this->fechaOrdenResuelta(),
                );

                $this->idRegistroSesionesSeleccionado = $registroSesiones->id;
                $this->mensajeExito = sprintf(
                    'Registro iniciado y turno %s registrado · 1/%d',
                    $registroSesiones->turnos->first()->fecha_hora->format('d/m/Y H:i'),
                    $registroSesiones->cant_sesiones
                );
            }

            $this->reset(['fechaTurno', 'horaTurno']);
            $this->sincronizarRegistroSesionesSeleccionado();
            unset($this->kinesioConOrdenEnCurso, $this->turnosDisponibles, $this->diasSemana);

        } catch (ValidationException $ex) {
            throw $ex;
        } catch (ReglaNegocioException $ex) {
            $this->addError('fechaTurno', $ex->getMessage());
        } catch (\Throwable $th) {
            Log::error('[(Livewire) actividades-pacientes.kinesiologia.con-orden.crear@almacenar] Error al registrar turno.', [
                'excepción' => $th->getMessage(),
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        } finally {
            $this->procesando = false;
        }
    }

    public function guardarFechaOrden(ActividadPacienteService $service): void
    {
        $this->validate([
            'idRegistroSesionesSeleccionado' => 'required|integer|exists:actividades_pacientes,id',
            'mesOrden' => 'required|integer|min:1|max:12',
            'diaOrden' => 'required|integer|min:1|max:31',
            'anioOrden' => ['required', 'integer', Rule::in($this->aniosDisponibles())],
        ], [], [
            'mesOrden' => 'mes de la orden médica',
            'diaOrden' => 'día de la orden médica',
            'anioOrden' => 'año de la orden médica',
        ]);

        try {
            $registroSesiones = $this->registroSesionesEnCurso();

            if ($registroSesiones === null) {
                throw new ReglaNegocioException('No hay un registro de sesiones seleccionado.');
            }

            $fecha = $this->fechaOrdenResuelta();

            if ($fecha === null) {
                throw new ReglaNegocioException('Debe ingresar una fecha de orden médica válida.');
            }

            $service->cargarFechaOrdenMedica($registroSesiones, $fecha);
            $this->mensajeExito = 'Fecha de orden médica guardada.';
            unset($this->kinesioConOrdenEnCurso);
            $this->cargarFechaOrdenEnFormulario();
        } catch (ValidationException $ex) {
            throw $ex;
        } catch (ReglaNegocioException $ex) {
            $this->addError('mesOrden', $ex->getMessage());
        } catch (\Throwable $th) {
            Log::error('[(Livewire) actividades-pacientes.kinesiologia.con-orden.crear@guardarFechaOrden] Error al guardar fecha de orden.', [
                'excepción' => $th->getMessage(),
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    #[Computed]
    public function resultadosBusqueda(): Collection
    {
        if (strlen($this->busqueda) < 2) {
            return collect();
        }

        return Paciente::select('id', 'nombre', 'apellido')
            ->buscarPorApNom($this->busqueda)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function kinesioConOrdenEnCurso(): Collection
    {
        if ($this->idPacienteSeleccionado === null) {
            return collect();
        }

        return app(ActividadPacienteService::class)
            ->kinesioConOrdenEnCurso((int) $this->idPacienteSeleccionado);
    }

    #[Computed]
    public function turnosDisponibles(): array
    {
        if ($this->idPacienteSeleccionado === null) {
            return [];
        }

        $inicio = ($this->proximaSemana ? now()->addWeek() : now())->startOfWeek();
        $fin = $inicio->copy()->addDays(4);

        $disponibles = Actividad::findOrFail(Actividad::KINESIOLOGIA_CONVENCIONAL)
            ->turnosDisponibles(
                (int) $this->idPacienteSeleccionado,
                $inicio->copy()->startOfDay(),
                $fin->copy()->endOfDay()
            );

        $diasConKinesio = $this->diasConTurnoKinesioDelPaciente(
            (int) $this->idPacienteSeleccionado,
            $inicio,
            $fin
        );

        return array_values(array_filter(
            $disponibles,
            fn (string $turnoStr) => !isset($diasConKinesio[substr($turnoStr, 0, 10)])
        ));
    }

    /**
     * @return array<string, true>
     */
    private function diasConTurnoKinesioDelPaciente(int $idPaciente, Carbon $inicio, Carbon $fin): array
    {
        return Turno::query()
            ->whereNull('id_turno_original')
            ->whereBetween('fecha_hora', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->whereHas(
                'actividadPaciente',
                fn ($q) => $q
                    ->where('id_paciente', $idPaciente)
                    ->whereHas(
                        'actividad',
                        fn ($actividad) => $actividad->where('id_tipo_actividad', Actividad::TIPO_KINESIOLOGIA)
                    )
            )
            ->get(['fecha_hora'])
            ->mapWithKeys(fn (Turno $turno) => [$turno->fecha_hora->format('Y-m-d') => true])
            ->all();
    }

    #[Computed]
    public function diasSemana(): array
    {
        $fechaBase = ($this->proximaSemana ? now()->addWeek() : now())->startOfWeek();
        $diasConCupo = collect($this->turnosDisponibles)
            ->map(fn (string $t) => substr($t, 0, 10))
            ->unique()
            ->flip();

        $dias = [];

        for ($i = 0; $i < 5; $i++) {
            $fecha = $fechaBase->copy()->addDays($i);
            $fechaStr = $fecha->format('Y-m-d');

            if (isset($diasConCupo[$fechaStr])) {
                $dias[$fechaStr] = $fecha->translatedFormat('l d/m');
            }
        }

        return $dias;
    }

    #[Computed]
    public function diasDelMesOrden(): array
    {
        if ($this->mesOrden === '' || $this->anioOrden === '') {
            return [];
        }

        $cantidadDias = cal_days_in_month(CAL_GREGORIAN, (int) $this->mesOrden, (int) $this->anioOrden);

        return range(1, $cantidadDias);
    }

    public function modoContinuar(): bool
    {
        return $this->idPacienteSeleccionado !== null
            && $this->kinesioConOrdenEnCurso->isNotEmpty();
    }

    public function registroSesionesEnCurso(): ?ActividadPaciente
    {
        if ($this->idRegistroSesionesSeleccionado === null) {
            return null;
        }

        return $this->kinesioConOrdenEnCurso->firstWhere('id', $this->idRegistroSesionesSeleccionado);
    }

    public function registroSesionesCompleto(): bool
    {
        $registroSesiones = $this->registroSesionesEnCurso();

        if ($registroSesiones === null) {
            return false;
        }

        return $registroSesiones->turnos->count() >= (int) $registroSesiones->cant_sesiones;
    }

    public function aniosDisponibles(): array
    {
        $actual = (int) now()->year;

        return [$actual - 1, $actual];
    }

    private function sincronizarRegistroSesionesSeleccionado(): void
    {
        unset($this->kinesioConOrdenEnCurso);
        $registrosSesiones = $this->kinesioConOrdenEnCurso;

        if ($registrosSesiones->isEmpty()) {
            $this->idRegistroSesionesSeleccionado = null;
            $this->mesOrden = '';
            $this->diaOrden = '';
            $this->anioOrden = (string) now()->year;

            return;
        }

        $this->idRegistroSesionesSeleccionado = $registrosSesiones->first()->id;
        $this->cargarFechaOrdenEnFormulario();
    }

    private function cargarFechaOrdenEnFormulario(): void
    {
        $registroSesiones = $this->registroSesionesEnCurso();

        if ($registroSesiones && $registroSesiones->ordenCargada()) {
            $this->anioOrden = (string) $registroSesiones->fecha_emision_ord->year;
            $this->mesOrden = (string) $registroSesiones->fecha_emision_ord->month;
            $this->diaOrden = (string) $registroSesiones->fecha_emision_ord->day;

            return;
        }

        $this->mesOrden = '';
        $this->diaOrden = '';
        $this->anioOrden = (string) now()->year;
    }

    private function fechaOrdenResuelta(): ?string
    {
        if ($this->mesOrden === '' || $this->diaOrden === '' || $this->anioOrden === '') {
            return null;
        }

        $fecha = Carbon::create((int) $this->anioOrden, (int) $this->mesOrden, (int) $this->diaOrden);

        if ($fecha->toDateString() === ActividadPaciente::FECHA_ORDEN_PENDIENTE) {
            return null;
        }

        return $fecha->toDateString();
    }

    private function validarDiaOrden(): void
    {
        if ($this->mesOrden === '' || $this->anioOrden === '' || $this->diaOrden === '') {
            return;
        }

        $maxDias = cal_days_in_month(CAL_GREGORIAN, (int) $this->mesOrden, (int) $this->anioOrden);

        if ((int) $this->diaOrden > $maxDias) {
            $this->diaOrden = (string) $maxDias;
        }
    }
};
?>

<div class="contenedor max-w-3xl">
    <form class="formulario" wire:submit.prevent="almacenar">
        <h2 class="titulo-formulario">Turno kinesiología con orden médica</h2>
        <p class="mb-4 text-sm text-gray-400">Kinesiología Convencional · carga de a un turno</p>

        <x-alerta tipo="exito" />
        <x-alerta tipo="error" />

        @if ($mensajeExito !== '')
            <div class="mb-4 p-4 rounded-lg shadow-md bg-green-100 border-green-500 border-l-4 text-green-700">
                <span class="font-bold">{{ $mensajeExito }}</span>
            </div>
        @endif

        <div class="mb-4">
            <x-buscador-livewire
                :busqueda="$busqueda"
                :idSeleccionado="$idPacienteSeleccionado"
                :sugerencias="$this->resultadosBusqueda"
                etiquetaBuscador="Paciente"
                campoError="idPacienteSeleccionado"
            />
        </div>

        @if ($idPacienteSeleccionado)
            @if ($this->modoContinuar())
                @php $registroSesiones = $this->registroSesionesEnCurso(); @endphp

                <div class="mb-4 p-4 rounded-lg border border-gray-700 bg-gray-800/80">
                    <p class="text-white font-medium">
                        Registro de sesiones en curso
                        @if ($registroSesiones)
                            · {{ $registroSesiones->turnos->count() }}/{{ $registroSesiones->cant_sesiones }}
                        @endif
                    </p>
                    <p class="text-sm text-white/80">
                        Orden:
                        @if ($registroSesiones?->ordenCargada())
                            <span class="text-white">{{ $registroSesiones->fecha_emision_ord->format('d/m/Y') }}</span>
                        @else
                            <span class="text-amber-400">No cargada</span>
                        @endif
                    </p>

                    @if ($registroSesiones && $registroSesiones->ordenPendiente())
                        <div class="mt-3">
                            <h3 class="etiqueta-formulario">Fecha emisión orden médica</h3>
                            <div class="flex gap-2">
                                <select class="entrada flex-1" wire:model.live="mesOrden">
                                    <option value="" disabled selected>Mes</option>
                                    @foreach (range(1, 12) as $numeroMes)
                                        <option value="{{ $numeroMes }}">
                                            {{ ucfirst(Carbon::create(null, $numeroMes, 1)->translatedFormat('F')) }}
                                        </option>
                                    @endforeach
                                </select>

                                <select class="entrada flex-1" wire:model.live="diaOrden" @disabled($mesOrden === '')>
                                    <option value="" disabled selected>Día</option>
                                    @foreach ($this->diasDelMesOrden as $dia)
                                        <option value="{{ $dia }}">{{ $dia }}</option>
                                    @endforeach
                                </select>

                                <select class="entrada flex-1" wire:model.live="anioOrden">
                                    @foreach ($this->aniosDisponibles() as $anio)
                                        <option value="{{ $anio }}">{{ $anio }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('mesOrden') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
                            @error('diaOrden') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
                            @error('anioOrden') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror

                            <button
                                type="button"
                                class="boton-registrar mt-3 !w-auto px-4"
                                wire:click="guardarFechaOrden"
                                wire:loading.attr="disabled"
                            >
                                Guardar orden
                            </button>
                        </div>
                    @endif
                </div>

                @if ($registroSesiones)
                    <div class="mb-6">
                        <h3 class="etiqueta-formulario mb-2">Turnos del registro</h3>
                        <ul class="space-y-1">
                            @forelse ($registroSesiones->turnos as $turno)
                                @php
                                    $esPasado = $turno->fecha_hora->isPast();
                                @endphp
                                <li
                                    @class([
                                        'px-3 py-2 rounded border text-sm',
                                        'border-gray-800 bg-gray-900/40 text-gray-400' => $esPasado,
                                        'border-gray-600 bg-gray-800 text-white' => !$esPasado,
                                    ])
                                >
                                    {{ $turno->fecha_hora->translatedFormat('l d/m/Y H:i') }}
                                    @if ($esPasado)
                                        <span class="ml-2 text-xs uppercase tracking-wide text-gray-500">Pasado</span>
                                    @endif
                                </li>
                            @empty
                                <li class="text-sm text-white/70">Sin turnos registrados.</li>
                            @endforelse
                        </ul>
                    </div>
                @endif

                @if ($this->registroSesionesCompleto())
                    <p class="mb-4 text-amber-300 text-sm">
                        Este registro ya tiene todas las sesiones agendadas.
                    </p>
                @endif
            @else
                <div class="mb-4 p-4 rounded-lg border border-gray-700 bg-gray-800/60">
                    <p class="mb-3 text-white font-medium">Nuevo registro de sesiones con orden médica</p>

                    <div class="fila-formulario">
                        <div class="columna-campo flex-1">
                            <label for="cant-sesiones" class="etiqueta-formulario">Sesiones que cubre la orden</label>
                            <select id="cant-sesiones" class="entrada" wire:model="cantSesiones">
                                <option value="" disabled>Seleccione</option>
                                <option value="5">5</option>
                                <option value="10">10</option>
                            </select>
                            @error('cantSesiones')
                                <span class="text-red-500 text-xs italic">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="columna-campo flex-1">
                            <h3 class="etiqueta-formulario">Fecha emisión orden médica</h3>
                            <div class="flex gap-2">
                                <select class="entrada flex-1" wire:model.live="mesOrden">
                                    <option value="" disabled selected>Mes</option>
                                    @foreach (range(1, 12) as $numeroMes)
                                        <option value="{{ $numeroMes }}">
                                            {{ ucfirst(Carbon::create(null, $numeroMes, 1)->translatedFormat('F')) }}
                                        </option>
                                    @endforeach
                                </select>

                                <select class="entrada flex-1" wire:model.live="diaOrden" @disabled($mesOrden === '')>
                                    <option value="" disabled selected>Día</option>
                                    @foreach ($this->diasDelMesOrden as $dia)
                                        <option value="{{ $dia }}">{{ $dia }}</option>
                                    @endforeach
                                </select>

                                <select class="entrada flex-1" wire:model.live="anioOrden">
                                    @foreach ($this->aniosDisponibles() as $anio)
                                        <option value="{{ $anio }}">{{ $anio }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="mt-1 text-sm text-gray-400 italic">(Opcional, puede cargarla más tarde)</p>
                            @error('mesOrden') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
                            @error('diaOrden') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
                            @error('anioOrden') <span class="text-red-500 text-xs italic">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            @endif

            @unless ($this->modoContinuar() && $this->registroSesionesCompleto())
                <div class="fila-formulario">
                    <div class="columna-campo">
                        <label class="etiqueta-formulario">Semana del turno</label>
                        <x-toggle
                            :activo="$proximaSemana"
                            etiquetaIzquierda="Actual"
                            etiquetaDerecha="Próxima"
                            wire:click="$set('proximaSemana', {{ $proximaSemana ? 'false' : 'true' }})"
                        />
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="columna-campo flex-1">
                        <label for="fecha-turno" class="etiqueta-formulario">Fecha</label>
                        <select id="fecha-turno" class="entrada" wire:model.live="fechaTurno">
                            <option value="" disabled selected>Seleccione una fecha</option>
                            @foreach ($this->diasSemana as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        @error('fechaTurno')
                            <span class="text-red-500 text-xs italic">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="columna-campo flex-1">
                        <label for="hora-turno" class="etiqueta-formulario">Hora</label>
                        <select id="hora-turno" class="entrada" wire:model="horaTurno" @disabled($fechaTurno === '')>
                            <option value="" disabled selected>Seleccione un horario</option>
                            @if ($fechaTurno !== '')
                                @foreach ($this->turnosDisponibles as $turnoStr)
                                    @if (str_starts_with($turnoStr, $fechaTurno))
                                        @php $horaStr = substr($turnoStr, 11, 5); @endphp
                                        <option value="{{ $horaStr }}">{{ $horaStr }} hs</option>
                                    @endif
                                @endforeach
                            @endif
                        </select>
                        @error('horaTurno')
                            <span class="text-red-500 text-xs italic">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <button
                    type="submit"
                    class="boton-registrar"
                    wire:loading.attr="disabled"
                    wire:target="almacenar"
                >
                    {{ $this->modoContinuar() ? 'Agregar turno' : 'Abrir registro y registrar turno' }}
                </button>
            @endunless

            @if ($this->modoContinuar() && $this->registroSesionesEnCurso()?->tieneOrdenMedica())
                <a
                    href="{{ route('copagos.crear') }}"
                    class="mt-3 inline-block text-sm text-emerald-400 hover:text-emerald-300 underline"
                >
                    Registrar copago
                </a>
            @endif
        @endif
    </form>
</div>
