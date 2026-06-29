<?php

use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function pacientesFijos()
    {
        return PacienteFijo::query()
            ->select('id', 'id_actividad', 'id_paciente', 'id_pac_fijo_dual')
            ->principales()
            ->with([
                'actividad:id,nombre',
                'paciente:id,nombre,apellido',
                'horarios',
                'pacFijoDual.actividad:id,nombre',
                'pacFijoDual.horarios',
            ])
            ->get();
    }

    public function eliminar($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $pacienteFijo = PacienteFijo::with('pacFijoDual')->findOrFail($id);
                $pareja = $pacienteFijo->pacFijoDual;

                $registros = collect([$pacienteFijo]);

                if ($pareja) {
                    $registros->push($pareja);
                }

                foreach ($registros as $registro) {
                    $registro->update(['id_pac_fijo_dual' => null]);
                }

                foreach ($registros as $registro) {
                    ActividadPaciente::query()
                        ->where([
                            'id_actividad' => $registro->id_actividad,
                            'id_paciente' => $registro->id_paciente,
                            'es_fijo' => true,
                        ])
                        ->where('fecha_comienzo', '>', now())
                        ->whereDoesntHave('turnos', fn ($consulta) => $consulta->where('estado', 'Presente'))
                        ->delete();

                    $registro->delete();
                }
            });

            session()->flash('exito', 'El paciente ha sido eliminado de la lista de pacientes fijos. No se generarán más turnos de forma automática.');
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

        <h2 class="titulo-formulario">Listado de pacientes fijos</h2>

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
                        @php($esDualConPareja = $pacFijo->esDual() && $pacFijo->pacFijoDual)
                        <tr class="group tabla-listado__fila">
                            <td>{{ $pacFijo->paciente->apellido_nombre }}</td>
                            <td>
                                @if($esDualConPareja)
                                    Inscripción Dual (Gym + Pilates)
                                @else
                                    {{ $pacFijo->actividad->nombre }}
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-col gap-3">
                                    <div>
                                        @if($esDualConPareja)
                                            <span class="block text-xs uppercase text-gray-400 mb-1">{{ $pacFijo->actividad->nombre }}</span>
                                        @endif
                                        <div class="flex flex-wrap justify-center gap-2">
                                            @foreach ($pacFijo->horarios as $hor)
                                                <span class="px-2 py-1 inline-flex items-center bg-white/10 group-hover:bg-[#014745]/10 border-white/20 group-hover:border-[#014745]/20 border rounded text-xs">
                                                    <span class="font-black uppercase mr-1.5">{{ $hor->nombre_dia }}</span>
                                                    {{ Carbon::parse($hor->hora_inicio)->format('H:i') }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>

                                    @if($esDualConPareja)
                                        <div>
                                            <span class="block text-xs uppercase text-gray-400 mb-1">{{ $pacFijo->pacFijoDual->actividad->nombre }}</span>
                                            <div class="flex flex-wrap justify-center gap-2">
                                                @foreach ($pacFijo->pacFijoDual->horarios as $hor)
                                                    <span class="px-2 py-1 inline-flex items-center bg-white/10 group-hover:bg-[#014745]/10 border-white/20 group-hover:border-[#014745]/20 border rounded text-xs">
                                                        <span class="font-black uppercase mr-1.5">{{ $hor->nombre_dia }}</span>
                                                        {{ Carbon::parse($hor->hora_inicio)->format('H:i') }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="text-white hover:text-red-400 transition-colors duration-200"
                                    wire:click="eliminar({{ $pacFijo->id }})"
                                    wire:confirm="{{ $esDualConPareja ? '¿Eliminar la inscripción dual recurrente por completo? Esta acción es permanente y no se generarán más turnos automáticos.' : '¿Estás seguro de que deseas eliminar al paciente del registro de pacientes fijos? Esta acción es permanente y no se generarán más turnos.' }}">
                                    <x-iconos.basura />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-gray-300 italic">No hay registros disponibles.</td>
                        </tr>
                    @endforelse
                </tbody>
        </table>
    </div>
</div>
