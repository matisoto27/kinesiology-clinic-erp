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
    <div class="mx-auto mt-10 mb-5 px-8 py-6 bg-[#006E6B] max-w-screen-3xl w-full">
        <div>
            @if (session()->has('error'))
                <div class="p-4 mb-4 text-md text-red-100 bg-red-600 rounded-lg shadow-md animate-bounce">
                    <span class="font-bold">¡Error!</span> {{ session('error') }}
                </div>
            @endif
        </div>

        <h2 class="titulo-formulario">Listado de pacientes fijos</h2>

        <table class="table-fixed bg-[#014745] text-white text-center overflow-hidden rounded-xl w-full">
                <thead>
                    <tr class="bg-white text-[#014745]">
                        <th class="py-3">Paciente</th>
                        <th class="py-3">Actividad</th>
                        <th class="py-3">Horarios</th>
                        <th class="py-3">Estado</th>
                        <th class="py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pacientesFijos as $pacFijo)
                        <tr class="group hover:bg-[#F5D500] hover:font-bold hover:text-emerald-900 transition-colors duration-100">
                            <td class="py-3">{{ $pacFijo->paciente->nombre_completo }}</td>
                            <td class="py-3">{{ $pacFijo->actividad->nombre }}</td>
                            <td class="py-3">
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
                            <td class="py-3">
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
                            <td class="py-3">
                                <button
                                    type="button"
                                    class="text-white hover:text-red-400 transition-colors duration-200"
                                    wire:click="eliminar({{ $pacFijo->id }})"
                                    wire:confirm="¿Estás seguro de que deseas eliminar al paciente del registro de pacientes fijos? Esta acción es permanente y no se generarán más turnos."
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
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
