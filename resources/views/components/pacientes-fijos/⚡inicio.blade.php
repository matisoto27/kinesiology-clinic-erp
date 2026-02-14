<?php

use App\Models\ActividadPaciente;
use App\Models\PacienteFijo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

new class extends Component
{
    public Collection $pacientesFijos;

    public function actualizarEstado(int $id, bool $nuevoEstado)
    {
        DB::beginTransaction();

        try {
            $pacienteFijo = PacienteFijo::findOrFail($id);
            $pacienteFijo->update(['activo' => $nuevoEstado]);

            if ($nuevoEstado) {
                Artisan::call('app:generar-turnos-mensuales', [
                    '--id_paciente_fijo' => $pacienteFijo->id
                ]);
            } else {
                $this->eliminarTurnosFuturos($pacienteFijo);
            }

            DB::commit();

        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error('[(Livewire) pacientes-fijos.inicio@actualizarEstado] Error al actualizar el estado del paciente fijo.', [
                'excepción' => $ex->getMessage()
            ]);

            $mensaje = $ex instanceof \Exception
                ? $ex->getMessage()
                : 'Error interno del servidor.';
            session()->flash('error', $mensaje);
        }

        $this->actualizarDatos();
    }

    public function eliminar($id)
    {
        try {
            $pacienteFijo = PacienteFijo::findOrFail($id);
            $pacienteFijo->delete();

            $this->eliminarTurnosFuturos($pacienteFijo);

        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error('[(Livewire) pacientes-fijos.inicio@eliminar] Error al eliminar el paciente fijo.', [
                'excepción' => $ex->getMessage()
            ]);

            session()->flash('error', 'Ocurrió un error al intentar eliminar el paciente fijo de los registros.');
        }

        $this->actualizarDatos();
    }

    protected function eliminarTurnosFuturos(PacienteFijo $pacienteFijo)
    {
        $inscAutoGeneradas = ActividadPaciente::where([
            'id_actividad' => $pacienteFijo->id_actividad,
            'id_paciente' => $pacienteFijo->id_paciente
        ])
        ->where('es_fijo', true)
        ->where('fecha_comienzo', '>', now())
        ->get();

        foreach ($inscAutoGeneradas as $inscripcion) {
            $tieneAlgunaAsistencia = $inscripcion->turnos()->where('asiste', true)->exists();

            if (!$tieneAlgunaAsistencia) {
                $inscripcion->delete();
            }
        }
    }

    protected function actualizarDatos()
    {
        $this->pacientesFijos = PacienteFijo::with(['actividad', 'paciente', 'horarios'])->get();
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
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pacientesFijos as $pacFijo)
                        <tr class="group tabla-listado__fila">
                            <td>{{ $pacFijo->paciente->nombre_completo }}</td>
                            <td>{{ $pacFijo->actividad->nombre }}</td>
                            <td>
                                <div class="flex flex-wrap justify-center gap-2">
                                    @foreach ($pacFijo->horarios as $hor)
                                        <span class="px-2 py-1 inline-flex items-center bg-white/10 group-hover:bg-[#014745]/10 border-white/20 group-hover:border-[#014745]/20 border rounded text-xs">
                                            <span class="font-black uppercase mr-1.5">
                                                {{ ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][$hor->dia_semana] }}
                                            </span>
                                            {{ Carbon::parse($hor->hora_inicio)->format('H:i') }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if($pacFijo->activo)
                                    <button
                                        class="indicador-estado indicador-estado--activo"
                                        wire:click="actualizarEstado({{ $pacFijo->id }}, false)"
                                        wire:confirm="¿Estás seguro de que deseas SUSPENDER TEMPORALMENTE a este paciente? Los turnos dejarán de generarse automáticamente."
                                    >
                                        <span class="indicador-estado__punto bg-emerald-300"></span>
                                        Activo
                                    </button>
                                @else
                                    <button
                                        class="indicador-estado indicador-estado--suspendido"
                                        wire:click="actualizarEstado({{ $pacFijo->id }}, true)"
                                        wire:confirm="¿Deseas retomar la actividad del paciente? Se reanudará la generación automática de turnos."
                                    >
                                        <span class="indicador-estado__punto bg-amber-600"></span>
                                        Suspendido Temporalmente
                                    </button>
                                @endif
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="text-white hover:text-red-400 transition-colors duration-200"
                                    wire:click="eliminar({{ $pacFijo->id }})"
                                    wire:confirm="¿Estás seguro de que deseas eliminar al paciente del registro de pacientes fijos? Esta acción es permanente y no se generarán más turnos.">
                                    <x-iconos.basura />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-gray-300 italic">No hay registros disponibles.</td>
                        </tr>
                    @endforelse
                </tbody>
        </table>
    </div>
</div>
