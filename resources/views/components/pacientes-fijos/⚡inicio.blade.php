<?php

use App\Models\Actividad;
use App\Models\PacienteFijo;
use App\Services\PacienteFijoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'paciente')]
    public string $consultaPaciente = '';

    #[Url(as: 'actividad')]
    public string $filtroActividad = '';

    public function updatingConsultaPaciente(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroActividad(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function pacientesFijos()
    {
        return PacienteFijo::query()
            ->select(['pacientes_fijos.id', 'pacientes_fijos.id_paciente', 'pacientes_fijos.created_at'])
            ->with([
                'paciente:id,nombre,apellido',
                'horarios.actividad:id,nombre',
            ])
            ->when(!empty($this->consultaPaciente), fn ($consulta) => $consulta->whereHas(
                'paciente',
                fn ($subconsulta) => $subconsulta->buscarPorApNom($this->consultaPaciente)
            ))
            ->when($this->filtroActividad === 'gimnasio', fn ($consulta) => $consulta
                ->whereHas('horarios', fn ($sc) => $sc->where('id_actividad', Actividad::GIMNASIO))
                ->whereDoesntHave('horarios', fn ($sc) => $sc->where('id_actividad', Actividad::PILATES)))
            ->when($this->filtroActividad === 'pilates', fn ($consulta) => $consulta
                ->whereHas('horarios', fn ($sc) => $sc->where('id_actividad', Actividad::PILATES))
                ->whereDoesntHave('horarios', fn ($sc) => $sc->where('id_actividad', Actividad::GIMNASIO)))
            ->when($this->filtroActividad === 'dual', fn ($consulta) => $consulta
                ->whereHas('horarios', fn ($sc) => $sc->where('id_actividad', Actividad::GIMNASIO))
                ->whereHas('horarios', fn ($sc) => $sc->where('id_actividad', Actividad::PILATES)))
            ->orderByDesc('pacientes_fijos.created_at')
            ->paginate(10);
    }

    public function eliminar($id)
    {
        try {
            $pacienteFijo = PacienteFijo::findOrFail($id);
            $resultado = app(PacienteFijoService::class)->eliminar($pacienteFijo);

            $mensaje = 'El paciente fue eliminado de fijos. No se generarán más turnos automáticos.';

            if ($resultado['conservadas'] > 0) {
                $mensaje .= ' Quedaron inscripciones con pagos o asistencia (Presente) que no se borraron.';
            }

            session()->flash('exito', $mensaje);
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) pacientes-fijos.inicio@eliminar] Error al eliminar el paciente fijo.', ['excepción' => $ex->getMessage()]);
            session()->flash('error', 'Ocurrió un error al intentar eliminar el paciente fijo de los registros.');
        }
    }
};
?>

<div>
    <div class="contenedor-listado max-w-screen-3xl">
        <x-alerta tipo="error" />

        <h2 class="titulo-formulario">Inscripciones mensuales</h2>

        <div class="fila-formulario">
            <div class="columna-campo">
                <label for="buscar-paciente" class="etiqueta-formulario">Buscar Paciente</label>
                <input
                    id="buscar-paciente"
                    type="text"
                    class="entrada w-[28ch]"
                    placeholder="Ingrese nombre y/o apellido"
                    wire:model.live.debounce.300ms="consultaPaciente"
                >
            </div>

            <div class="columna-campo">
                <label for="filtro-actividad" class="etiqueta-formulario">Filtrar por Actividad</label>
                <select id="filtro-actividad" class="entrada" wire:model.live="filtroActividad">
                    <option value="">Todas</option>
                    <option value="gimnasio">Gimnasio</option>
                    <option value="pilates">Pilates</option>
                    <option value="dual">Dual</option>
                </select>
            </div>
        </div>

        <table class="tabla-listado">
                <thead>
                    <tr class="tabla-listado__cabecera">
                        <th>Paciente</th>
                        <th>Actividad</th>
                        <th>Horarios</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->pacientesFijos as $pacFijo)
                        @php($actividadesAgrupadas = $pacFijo->horarios->groupBy(fn ($hor) => $hor->actividad->nombre))
                        @php($esDualConPareja = $actividadesAgrupadas->count() > 1)
                        @php($puedeEditar = $pacFijo->estaCursandoInscripcion())
                        <tr class="group tabla-listado__fila" wire:key="paciente-fijo-{{ $pacFijo->id }}">
                            <td>{{ $pacFijo->paciente->apellido_nombre }}</td>
                            <td>
                                @if($esDualConPareja)
                                    Inscripción Dual (Gym + Pilates)
                                @else
                                    {{ $actividadesAgrupadas->keys()->first() }}
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-col gap-3">
                                    @foreach ($actividadesAgrupadas as $nombreActividad => $horariosDeActividad)
                                        <div>
                                            @if($esDualConPareja)
                                                <span class="block text-xs uppercase text-gray-400 mb-1">{{ $nombreActividad }}</span>
                                            @endif
                                            <div class="flex flex-wrap justify-center gap-2">
                                                @foreach ($horariosDeActividad as $hor)
                                                    <span class="px-2 py-1 inline-flex items-center bg-white/10 group-hover:bg-[#014745]/10 border-white/20 group-hover:border-[#014745]/20 border rounded text-xs">
                                                        <span class="font-black uppercase mr-1.5">{{ $hor->nombre_dia }}</span>
                                                        {{ Carbon::parse($hor->hora_inicio)->format('H:i') }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="flex items-center justify-center gap-3">
                                @if ($puedeEditar)
                                    <a
                                        href="{{ route('pacientes-fijos.editar', ['id' => $pacFijo->id]) }}"
                                        class="text-white hover:text-emerald-300 transition-colors duration-200"
                                        title="Editar horarios"
                                    >
                                        <x-iconos.lapiz />
                                    </a>
                                @else
                                    <span
                                        class="text-white/30 cursor-not-allowed"
                                        title="No se puede editar: el paciente no está cursando una inscripción. Eliminalo y registralo de nuevo."
                                    >
                                        <x-iconos.lapiz />
                                    </span>
                                @endif
                                <button
                                    type="button"
                                    class="text-white hover:text-red-400 transition-colors duration-200"
                                    wire:click="eliminar({{ $pacFijo->id }})"
                                    wire:confirm="{{ $esDualConPareja ? '¿Dar de baja la inscripción dual fija? Se eliminarán turnos futuros sin pagos ni asistencia Presente. El historial se conserva.' : '¿Dar de baja al paciente fijo? Se eliminarán turnos futuros sin pagos ni asistencia Presente. El historial se conserva.' }}">
                                    <x-iconos.basura />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-gray-300 italic">
                                {{ $this->consultaPaciente !== '' || $this->filtroActividad !== '' ? 'No se encontraron pacientes fijos.' : 'No hay registros disponibles.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
        </table>

        <div class="mt-4">
            {{ $this->pacientesFijos->links(data: ['scrollTo' => false]) }}
        </div>
    </div>
</div>
