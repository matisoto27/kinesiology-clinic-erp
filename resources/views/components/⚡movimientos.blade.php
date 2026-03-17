<?php

use App\Models\Caja;
use App\Models\Egreso;
use App\Models\Pago;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;

new class extends Component
{
    use WithPagination;

    public string $filtroMetodo = 'todos';
    public string $filtroTipo = 'todos';

    public function updatedFiltroMetodo($value)
    {
        if ($value === 'transferencia') {
            $this->filtroTipo = 'ingreso';
        }

        $this->resetPage();
    }

    public function updatedFiltroTipo($value)
    {
        if ($value === 'egreso') {
            $this->filtroMetodo = 'efectivo';
        }

        $this->resetPage();
    }

    #[Computed]
    public function movimientos()
    {
        $ingresos = collect();
        $egresos = collect();

        if ($this->filtroTipo === 'todos' || $this->filtroTipo === 'ingreso') {
            $consulta = Pago::with([
                'actividadPaciente.actividad',
                'actividadPaciente.pacienteRegular',
                'actividadPaciente.pacienteCasual',
                'profesional'
            ]);

            if ($this->filtroMetodo !== 'todos') {
                $consulta->where('metodo', $this->filtroMetodo);
            }

            $ingresos = $consulta
                ->latest()
                ->take(500)
                ->get()
                ->each(function ($pago) {
                    $pago->tipo = 'ingreso';
                    $pago->fecha = $pago->created_at;
                });
        }

        if ($this->filtroMetodo !== 'transferencia' && ($this->filtroTipo === 'todos' || $this->filtroTipo === 'egreso')) {
            $egresos = Egreso::with('profesional')
                ->latest()
                ->take(500)
                ->get()
                ->each(function ($egreso) {
                    $egreso->tipo = 'egreso';
                    $egreso->fecha = $egreso->created_at;
                });
        }

        $movimientos = $ingresos
            ->concat($egresos)
            ->sortByDesc('fecha')
            ->values();

        $porPagina = 5;
        $paginaActual = $this->getPage();

        return new LengthAwarePaginator(
            $movimientos->forPage($paginaActual, $porPagina),
            $movimientos->count(),
            $porPagina,
            $paginaActual,
            ['path' => request()->url()]
        );
    }

    #[Computed]
    public function saldoEfectivo()
    {
        return Caja::value('saldo_efectivo') ?? 0;
    }

    #[Computed]
    public function saldoTransferencia()
    {
        return Caja::value('saldo_transferencia') ?? 0;
    }
};
?>

<div class="contenedor-listado max-w-screen-3xl">
    <div class="mb-6 flex justify-between items-center">
        <h2 class="titulo-formulario">Historial de Movimientos</h2>

        <div class="flex gap-3">
            <div class="p-4 flex flex-col items-end bg-gray-800 border border-gray-700 rounded-lg shadow-inner">
                <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Transferencias recibidas</span>
                <span class="text-emerald-400 text-3xl font-bold">
                    ${{ number_format($this->saldoTransferencia, 2, ',', '.') }}
                </span>
            </div>

            <div class="p-4 flex flex-col items-end bg-gray-800 border border-gray-700 rounded-lg shadow-inner">
                <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">Saldo Total en Caja</span>
                <span class="text-emerald-400 text-3xl font-bold">
                    ${{ number_format($this->saldoEfectivo, 2, ',', '.') }}
                </span>
            </div>
        </div>
    </div>

    <div class="fila-formulario">
        <div class="columna-campo">
            <label for="filtro-metodo" class="etiqueta-formulario">Método de pago</label>
            <select
                id="filtro-metodo"
                class="entrada"
                @disabled($filtroTipo === 'egreso')
                wire:model.live="filtroMetodo"
            >
                <option value="todos">Todos los métodos</option>
                <option value="efectivo">Efectivo (Caja)</option>
                <option value="transferencia">Transferencia</option>
            </select>
        </div>

        <div class="columna-campo">
            <label for="filtro-tipo" class="etiqueta-formulario">Tipo de movimiento</label>
            <select
                id="filtro-tipo"
                class="entrada"
                @disabled($filtroMetodo === 'transferencia')
                wire:model.live="filtroTipo"
            >
                <option value="todos">Todos los movimientos</option>
                <option value="ingreso">Ingresos (Pagos de pacientes)</option>
                <option value="egreso">Egresos</option>
            </select>
        </div>
    </div>

    <x-alerta tipo="exito" />
    <x-alerta tipo="error" />

    <table class="tabla-listado">
        <thead>
            <tr class="tabla-listado__cabecera">
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Profesional que lo registró</th>
                <th>Concepto / Detalle</th>
                <th>Monto</th>
            </tr>
        </thead>

        <tbody>
            @php $movimientos = $this->movimientos; @endphp
            @forelse($movimientos as $mov)
                <tr class="tabla-listado__fila group">
                    <td>{{ $mov->fecha->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($mov->tipo === 'ingreso')
                            <span class="badge bg-emerald-500">
                                {{ $mov->metodo === 'Efectivo' ? 'Ingreso de Caja' : 'Transferencia recibida' }}
                            </span>
                        @else
                            <span class="badge bg-red-500">
                                Egreso de Caja
                            </span>
                        @endif
                    </td>
                    <td>{{ $mov->profesional->nombre }} {{ $mov->profesional->apellido}}</td>
                    <td>
                        @if($mov->tipo === 'ingreso')
                            @php
                                $cantidad = (int) $mov->actividadPaciente->cant_sesiones;
                            @endphp

                            <small class="block text-emerald-400 group-hover:text-emerald-900 font-bold tracking-wide uppercase">
                                @if($mov->actividadPaciente->actividad->esActividadGeneral())
                                    @if ($mov->actividadPaciente->esRegular())
                                        {{ $mov->actividadPaciente->nombre_actividad }} ({{ (int)($cantidad / 4) }} {{ (int)($cantidad / 4) === 1 ? 'vez' : 'veces' }} por semana)
                                    @else
                                        Prueba de Pilates
                                    @endif
                                @else
                                    {{ $mov->actividadPaciente->nombre_actividad }} ({{ $cantidad }} {{ $cantidad === 1 ? 'sesión' : 'sesiones' }})
                                @endif
                            </small>

                            <small class="block text-gray-400 group-hover:text-emerald-900">
                                @if ($mov->es_copago)
                                    <span class="font-bold uppercase">Copago</span>
                                @else
                                    Pago #{{ $mov->nro_pago }}
                                @endif
                            </small>

                            <span class="group-hover:text-emerald-900">
                                {{ $mov->actividadPaciente->ap_nom_paciente }}
                            </span>
                        @else
                            <span class="text-gray-300 italic group-hover:text-emerald-900">
                                {{ $mov->motivo }}
                            </span>
                        @endif
                    </td>
                    <td class="{{ $mov->tipo === 'ingreso' ? 'text-emerald-400' : 'text-red-400' }} font-bold">
                        {{ $mov->tipo === 'egreso' ? '-' : '+' }} ${{ number_format($mov->monto, 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-10 text-center text-gray-300 italic">No se encontraron movimientos.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $movimientos->links(data: ['scrollTo' => false]) }}
    </div>
</div>
