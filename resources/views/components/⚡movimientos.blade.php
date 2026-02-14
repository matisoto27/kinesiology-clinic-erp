<?php

use App\Models\Caja;
use App\Models\Egreso;
use App\Models\Pago;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;

new class extends Component
{
    use WithPagination;

    public string $filtroTipo = 'todos';

    public function updatingFiltroTipo()
    {
        $this->resetPage();
    }

    public function render()
    {
        $registros = $this->cargarMovimientos();

        $porPagina = 5;
        $paginaActual = $this->getPage();

        $movimientos = new LengthAwarePaginator(
            $registros->forPage($paginaActual, $porPagina),
            $registros->count(),
            $porPagina,
            $paginaActual,
            ['path' => url()->current()]
        );

        $caja = Caja::find(1);
        $saldoActual = $caja ? $caja->saldo_actual : 0;

        return $this->view([
            'movimientos' => $movimientos,
            'saldoActual' => $saldoActual
        ]);
    }

    protected function cargarMovimientos(): Collection
    {
        $ingresos = collect();
        $egresos = collect();

        if ($this->filtroTipo === 'todos' || $this->filtroTipo === 'ingreso') {
            $ingresos = Pago::with(['profesional', 'actividadPaciente.paciente'])
                ->latest()
                ->take(500)
                ->get()
                ->map(function ($pago) {
                    $pago->tipo = 'ingreso';
                    $pago->fecha = $pago->created_at;
                    return $pago;
                });
        }

        if ($this->filtroTipo === 'todos' || $this->filtroTipo === 'egreso') {
            $egresos = Egreso::with('profesional')
                ->get()
                ->map(function ($egreso) {
                    $egreso->tipo = 'egreso';
                    $egreso->metodo = 'Efectivo'; // Regla de negocio
                    $egreso->fecha = $egreso->created_at;
                    return $egreso;
                });
        }

        return $ingresos->concat($egresos)->sortByDesc('fecha');
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <h2 class="titulo-formulario">Historial de movimientos</h2>

        <div class="p-4 flex flex-col items-end bg-gray-800 border border-gray-700 rounded-lg shadow-inner">
            <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Saldo Total en Caja</span>
            <span class="{{ $saldoActual >= 0 ? 'text-emerald-400' : 'text-red-400' }} text-3xl font-bold">
                ${{ number_format($saldoActual, 2, ',', '.') }}
            </span>
        </div>
    </div>

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="filtro-tipo" class="etiqueta-formulario">Filtrar por tipo</label>
            <select id="filtro-tipo" class="entrada" wire:model.live="filtroTipo">
                <option value="todos">Todos los movimientos</option>
                <option value="ingreso">Ingresos (Pagos)</option>
                <option value="egreso">Egresos</option>
            </select>
        </div>
    </div>

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Profesional</th>
                <th>Concepto / Detalle</th>
                <th>Método</th>
                <th>Monto</th>
            </tr>
        </thead>

        <tbody>
            @forelse($movimientos as $mov)
                <tr class="tabla-listado__fila">
                    <td>{{ $mov->fecha->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($mov->tipo === 'ingreso')
                            <span class="px-3 py-1 inline-flex items-center bg-emerald-500 text-white text-sm font-semibold rounded">
                                INGRESO
                            </span>
                        @else
                            <span class="px-3 py-1 inline-flex items-center bg-red-500 text-white text-sm font-semibold rounded">
                                EGRESO
                            </span>
                        @endif
                    </td>
                    <td>{{ $mov->profesional->nombre }} {{ $mov->profesional->apellido}}</td>
                    <td>
                        @if($mov->tipo === 'ingreso')
                            <small class="block text-gray-400">Pago #{{ $mov->nro_pago }}</small>
                            {{ $mov->actividadPaciente->paciente->nombre_completo }}
                        @else
                            <span class="text-gray-300 italic">{{ $mov->motivo }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="capitalize">{{ $mov->metodo }}</span>
                    </td>
                    <td class="{{ $mov->tipo === 'ingreso' ? 'text-emerald-400' : 'text-red-400' }} font-bold">
                        {{ $mov->tipo === 'egreso' ? '-' : '+' }} ${{ number_format($mov->monto, 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-10 text-center text-gray-300 italic">No se encontraron movimientos.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $movimientos->links() }}
    </div>
</div>
