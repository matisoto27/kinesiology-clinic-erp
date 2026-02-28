<?php

use App\Models\ActividadCombo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $filtroEstado = 'todos';

    #[Computed]
    public function registrosFiltrados()
    {
        return ActividadCombo::with(['actividad', 'combo', 'precioVigente'])
            ->join('actividades', 'actividades_combos.id_actividad', '=', 'actividades.id')
            ->join('combos', 'actividades_combos.id_combo', '=', 'combos.id')
            ->select('actividades_combos.*')
            ->when($this->filtroEstado === 'activos', fn($c) => $c->where('actividades_combos.activo', true))
            ->when($this->filtroEstado === 'inactivos', fn($c) => $c->where('actividades_combos.activo', false))
            ->orderBy('actividades.id_tipo_actividad')
            ->orderBy('actividades.nombre')
            ->orderBy('combos.cantidad_sesiones')
            ->get();
    }

    public function alternarEstado(int $id, bool $estadoActual)
    {
        try {
            DB::transaction(function () use ($id, $estadoActual) {
                $actCom = ActividadCombo::findOrFail($id);
                $actCom->activo = !$estadoActual;
                $actCom->save();

                $estadoTexto = $actCom->activo ? 'alta' : 'baja';
                session()->flash('exito', "¡Combo de la actividad dado de {$estadoTexto} con éxito!");
            });
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) actividades-combos.inicio@alternarEstado] Error al actualizar estado.', [
                'id' => $id,
                'excepción' => $ex->getMessage()
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <h2 class="titulo-formulario">Combos de actividades</h2>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="filtro-estado" class="etiqueta-formulario">Filtrar por estado</label>
            <select id="filtro-estado" class="entrada" wire:model.live="filtroEstado">
                <option value="todos">Todos</option>
                <option value="activos">Activos</option>
                <option value="inactivos">Inactivos</option>
            </select>
        </div>
    </div>

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>Actividad</th>
                <th>Combo</th>
                <th>Sesiones</th>
                <th>¿Es mensual?</th>
                <th>Precio vigente</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($this->registrosFiltrados as $actCom)
                <tr class="tabla-listado__fila">
                    <td>{{ $actCom->actividad->nombre }}</td>
                    <td>{{ $actCom->combo->nombre }}</td>
                    <td>{{ $actCom->combo->cantidad_sesiones }}</td>
                    <td>{{ $actCom->combo->es_mensual ? 'Sí' : 'No' }}</td>
                    <td>
                        {{
                            $actCom->precioVigente?->valor === null
                                ? 'Sin asignar'
                                : '$' . number_format($actCom->precioVigente->valor, 2, ',', '.')
                        }}
                    </td>
                    <td>
                        <span class="px-3 py-1 inline-flex items-center {{ $actCom->activo ? 'bg-emerald-500' : 'bg-amber-500' }} text-white text-sm font-semibold rounded">
                            {{ $actCom->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td>
                        <div class="centrado-total space-x-4">
                            <button
                                type="button"
                                class="text-white hover:text-blue-400 transition-colors"
                                wire:click="alternarEstado({{ $actCom->id }}, {{ $actCom->activo ? 1 : 0 }})"
                                wire:confirm="¿Estás seguro de que deseas cambiar el estado de este combo?"
                                wire:loading.attr="disabled">
                                Alternar estado
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-10 text-gray-300 text-center italic">No hay registros disponibles.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
