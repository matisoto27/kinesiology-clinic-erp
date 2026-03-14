<?php

use App\Models\ObraSocial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(as: 'estado')]
    public string $filtroEstado = 'todos';

    #[Computed]
    public function registrosFiltrados()
    {
        return ObraSocial::query()
            ->withCount(['historialAfiliados as afiliados_count' => function ($consulta) {
                $consulta->whereNull('fecha_hasta');
            }])
            ->when($this->filtroEstado === 'activa', fn($c) => $c->where('activo', true))
            ->when($this->filtroEstado === 'inactiva', fn($c) => $c->where('activo', false))
            ->orderBy('nombre')
            ->get();
    }

    public function alternarEstado(int $id, bool $estadoActual)
    {
        try {
            DB::transaction(function () use ($id, $estadoActual) {
                $os = ObraSocial::findOrFail($id);
                $os->activo = !$estadoActual;
                $os->save();

                $estadoTexto = $os->activo ? 'alta' : 'baja';
                session()->flash('exito', "¡Obra social dada de {$estadoTexto} con éxito!");
            });
        } catch (\Throwable $ex) {
            Log::error('[(Livewire) obras-sociales.inicio@alternarEstado] Error al actualizar estado.', [
                'id' => $id,
                'excepción' => $ex->getMessage()
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <h2 class="titulo-formulario">Listado de Obras Sociales</h2>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="filtro-estado" class="etiqueta-formulario">Filtrar por estado</label>
            <select id="filtro-estado" class="entrada" wire:model.live="filtroEstado">
                <option value="todos">Todas</option>
                <option value="activa">Activas</option>
                <option value="inactiva">Inactivas</option>
            </select>
        </div>
    </div>

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>Nombre</th>
                <th>Afiliados Activos</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($this->registrosFiltrados as $os)
                <tr class="tabla-listado__fila" wire:key="os-{{ $os->id }}">
                    <td>{{ $os->nombre }}</td>
                    <td>{{ $os->afiliados_count }}</td>
                    <td>
                        <span class="px-3 py-1 inline-flex items-center {{ $os->activo ? 'bg-emerald-500' : 'bg-amber-500' }} text-white text-sm font-semibold rounded">
                            {{ $os->activo ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td>
                        <div class="centrado-total space-x-4">
                            <button
                                type="button"
                                class="accion-editar"
                                wire:click="alternarEstado({{ $os->id }}, {{ $os->activo ? 1 : 0 }})"
                                wire:confirm="¿Estás seguro de que deseas cambiar el estado de esta obra social?"
                                wire:loading.attr="disabled">
                                Alternar estado
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-10 text-gray-300 text-center italic">No se encontraron obras sociales para el filtro seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
