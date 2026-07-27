<?php

namespace App\Livewire\Concerns;

use App\Models\Sintoma;
use App\Support\NormalizadorNombreClinico;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

trait ManejaBuscadorSintomas
{
    public string $busquedaSintoma = '';

    /** @var list<array{id: int|null, nombre: string, es_nuevo: bool}> */
    public array $sintomasSeleccionados = [];

    #[Computed]
    public function sugerenciasSintomas(): Collection
    {
        if (mb_strlen(trim($this->busquedaSintoma)) < 2) {
            return collect();
        }

        $termino = trim($this->busquedaSintoma);
        $idsExcluir = collect($this->sintomasSeleccionados)
            ->pluck('id')
            ->filter()
            ->all();

        return Sintoma::query()
            ->where('activo', true)
            ->where('nombre', 'like', '%' . $termino . '%')
            ->when($idsExcluir !== [], fn ($consulta) => $consulta->whereNotIn('id', $idsExcluir))
            ->orderBy('nombre')
            ->limit(10)
            ->get();
    }

    public function seleccionarSintoma(int $id): void
    {
        $sintoma = Sintoma::query()->where('activo', true)->find($id);

        if ($sintoma === null || $this->sintomaYaSeleccionado($sintoma->id, $sintoma->nombre)) {
            return;
        }

        $this->sintomasSeleccionados[] = [
            'id' => $sintoma->id,
            'nombre' => $sintoma->nombre,
            'es_nuevo' => false,
        ];

        $this->busquedaSintoma = '';
        unset($this->sugerenciasSintomas);
    }

    public function agregarSintomaPendiente(): void
    {
        $nombre = trim($this->busquedaSintoma);

        if (mb_strlen($nombre) < 2) {
            return;
        }

        if (mb_strlen($nombre) > 50) {
            $this->addError('busquedaSintoma', 'El nombre del síntoma no puede superar los 50 caracteres.');
            return;
        }

        $nombre = NormalizadorNombreClinico::normalizar($nombre);

        $existente = Sintoma::query()
            ->where('activo', true)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($existente !== null) {
            $this->seleccionarSintoma($existente->id);
            return;
        }

        if ($this->sintomaYaSeleccionado(null, $nombre)) {
            $this->busquedaSintoma = '';
            return;
        }

        $this->sintomasSeleccionados[] = [
            'id' => null,
            'nombre' => $nombre,
            'es_nuevo' => true,
        ];

        $this->busquedaSintoma = '';
        unset($this->sugerenciasSintomas);
    }

    public function quitarSintoma(int $indice): void
    {
        if (!array_key_exists($indice, $this->sintomasSeleccionados)) {
            return;
        }

        unset($this->sintomasSeleccionados[$indice]);
        $this->sintomasSeleccionados = array_values($this->sintomasSeleccionados);
    }

    /**
     * @return list<int>
     */
    protected function persistirSintomasSeleccionados(): array
    {
        $ids = [];

        foreach ($this->sintomasSeleccionados as $item) {
            if ($item['es_nuevo'] || $item['id'] === null) {
                $nombre = NormalizadorNombreClinico::normalizar($item['nombre']);
                $sintoma = Sintoma::query()->firstOrCreate(
                    ['nombre' => $nombre],
                    ['activo' => true]
                );
                $ids[] = $sintoma->id;
            } else {
                $ids[] = (int) $item['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    protected function sintomaYaSeleccionado(?int $id, string $nombre): bool
    {
        $nombreNormalizado = mb_strtolower(NormalizadorNombreClinico::normalizar($nombre));

        foreach ($this->sintomasSeleccionados as $item) {
            if ($id !== null && $item['id'] === $id) {
                return true;
            }

            if (mb_strtolower(NormalizadorNombreClinico::normalizar($item['nombre'])) === $nombreNormalizado) {
                return true;
            }
        }

        return false;
    }

    protected function reglasSintomasSeleccionados(): array
    {
        return [
            'sintomasSeleccionados' => 'nullable|array',
            'sintomasSeleccionados.*.nombre' => 'required|string|max:50',
            'busquedaSintoma' => 'nullable|string|max:50',
        ];
    }
}
