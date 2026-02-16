<?php

use App\Models\Profesional;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public string $filtroEstado = 'todos';
    public Collection $profesionales;

    public function mount()
    {
        $this->cargarDatos();
    }

    public function updatedFiltroEstado()
    {
        $this->cargarDatos();
    }

    protected function cargarDatos()
    {
        $consulta = Profesional::query();

        if ($this->filtroEstado === 'activos') {
            $consulta->where('activo', true);
        } elseif ($this->filtroEstado === 'inactivos') {
            $consulta->where('activo', false);
        }

        $this->profesionales = $consulta->orderByDesc('nombre')->get();
    }

    public function eliminar(int $id)
    {
        DB::beginTransaction();

        try {
            $profesional = Profesional::findOrFail($id);
            $profesional->delete();

            DB::commit();
            session()->flash('exito', 'El profesional ha sido eliminado correctamente.');

            $this->cargarDatos();
        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error('[(Livewire) profesionales.inicio@eliminar] Error al eliminar el profesional.', [
                'id' => $id,
                'excepción' => $ex->getMessage()
            ]);
            session()->flash('error', 'Error interno del servidor. Si el error persiste contactar con el Equipo de Soporte (Matías).');
        }
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <h2 class="titulo-formulario">Lista de profesionales</h2>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="filtro-estado" class="etiqueta-formulario">Filtrar por estado</label>
            <select id="filtro-estado" class="entrada" wire:model.live="filtroEstado">
                <option value="todos">Todos</option>
                <option value="activos">Solo Activos</option>
                <option value="inactivos">Solo Inactivos</option>
            </select>
        </div>
    </div>

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>DNI</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Valor por hora</th>
                <th>Código personal</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($profesionales as $prof)
                <tr class="tabla-listado__fila">
                    <td>{{ $prof->dni }}</td>
                    <td>{{ $prof->nombre }}</td>
                    <td>{{ $prof->apellido }}</td>
                    <td>${{ number_format($prof->valor_por_hora, 2, ',', '.') }}</td>
                    <td>{{ $prof->codigo_personal }}</td>
                    <td>
                        <span class="px-3 py-1 inline-flex items-center {{ $prof->activo ? 'bg-emerald-500' : 'bg-amber-500' }} text-white text-sm font-semibold rounded">
                            {{ $prof->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex justify-center items-center space-x-4">
                            <a href="{{ route('profesionales.editar', ['profesional' => $prof['id']]) }}" class="text-white hover:text-blue-400 transition-colors">
                                <x-iconos.lapiz />
                            </a>
                            <button
                                type="button"
                                class="text-white hover:text-red-400 transition-colors duration-200"
                                wire:click="eliminar({{ $prof->id }})"
                                wire:confirm="¿Estás seguro de que deseas eliminar a este profesional?">
                                <x-iconos.basura />
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-10 text-center text-gray-300 italic">No hay profesionales registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
