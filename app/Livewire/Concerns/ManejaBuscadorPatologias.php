<?php

namespace App\Livewire\Concerns;

use App\Models\Patologia;
use App\Support\NormalizadorNombreClinico;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

trait ManejaBuscadorPatologias
{
    public string $busquedaPatologia = '';

    /** @var list<int> IDs de patologías ya vinculadas al paciente (solo edición). */
    #[Locked]
    public array $patologiasPreexistentesIds = [];

    /** @var list<array{id: int|null, nombre: string, es_nuevo: bool, bloqueada: bool}> */
    public array $patologiasSeleccionadas = [];

    #[Computed]
    public function sugerenciasPatologias(): Collection
    {
        if (mb_strlen(trim($this->busquedaPatologia)) < 2) {
            return collect();
        }

        $termino = trim($this->busquedaPatologia);
        $idsExcluir = collect($this->patologiasSeleccionadas)
            ->pluck('id')
            ->filter()
            ->all();

        return Patologia::query()
            ->where('activo', true)
            ->where('nombre', 'like', '%' . $termino . '%')
            ->when($idsExcluir !== [], fn ($consulta) => $consulta->whereNotIn('id', $idsExcluir))
            ->orderBy('nombre')
            ->limit(10)
            ->get();
    }

    public function seleccionarPatologia(int $id): void
    {
        $patologia = Patologia::query()->where('activo', true)->find($id);

        if ($patologia === null || $this->patologiaYaSeleccionada($patologia->id, $patologia->nombre)) {
            return;
        }

        $this->patologiasSeleccionadas[] = [
            'id' => $patologia->id,
            'nombre' => $patologia->nombre,
            'es_nuevo' => false,
            'bloqueada' => false,
        ];

        $this->busquedaPatologia = '';
        unset($this->sugerenciasPatologias);
    }

    public function agregarPatologiaPendiente(): void
    {
        $nombre = trim($this->busquedaPatologia);

        if (mb_strlen($nombre) < 2) {
            return;
        }

        if (mb_strlen($nombre) > 50) {
            $this->addError('busquedaPatologia', 'El nombre de la patología no puede superar los 50 caracteres.');
            return;
        }

        $nombre = NormalizadorNombreClinico::normalizar($nombre);

        $existente = Patologia::query()
            ->where('activo', true)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($existente !== null) {
            $this->seleccionarPatologia($existente->id);
            return;
        }

        if ($this->patologiaYaSeleccionada(null, $nombre)) {
            $this->busquedaPatologia = '';
            return;
        }

        $this->patologiasSeleccionadas[] = [
            'id' => null,
            'nombre' => $nombre,
            'es_nuevo' => true,
            'bloqueada' => false,
        ];

        $this->busquedaPatologia = '';
        unset($this->sugerenciasPatologias);
    }

    public function quitarPatologia(int $indice): void
    {
        if (!array_key_exists($indice, $this->patologiasSeleccionadas)) {
            return;
        }

        if ($this->patologiasSeleccionadas[$indice]['bloqueada'] ?? false) {
            return;
        }

        unset($this->patologiasSeleccionadas[$indice]);
        $this->patologiasSeleccionadas = array_values($this->patologiasSeleccionadas);
    }

    /**
     * @return list<int>
     */
    protected function persistirPatologiasSeleccionadas(): array
    {
        $this->validarPatologiasPreexistentesEnSeleccion();

        $ids = [];

        foreach ($this->patologiasSeleccionadas as $item) {
            if ($item['es_nuevo'] || $item['id'] === null) {
                $nombre = NormalizadorNombreClinico::normalizar($item['nombre']);
                $patologia = Patologia::query()->firstOrCreate(
                    ['nombre' => $nombre],
                    ['activo' => true]
                );
                $ids[] = $patologia->id;
            } else {
                $ids[] = (int) $item['id'];
            }
        }

        $ids = array_values(array_unique($ids));

        $this->validarPatologiasPreexistentesIntactas($ids);

        return $ids;
    }

    protected function validarPatologiasPreexistentesEnSeleccion(): void
    {
        if ($this->patologiasPreexistentesIds === []) {
            return;
        }

        $idsActuales = collect($this->patologiasSeleccionadas)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $faltantes = array_diff($this->patologiasPreexistentesIds, $idsActuales);

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'patologiasSeleccionadas' => 'No se pueden desvincular patologías ya registradas en el paciente.',
            ]);
        }
    }

    protected function validarPatologiasPreexistentesIntactas(array $idsPersistidos): void
    {
        if ($this->patologiasPreexistentesIds === []) {
            return;
        }

        $faltantes = array_diff($this->patologiasPreexistentesIds, $idsPersistidos);

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'patologiasSeleccionadas' => 'No se pueden desvincular patologías ya registradas en el paciente.',
            ]);
        }
    }

    protected function patologiaYaSeleccionada(?int $id, string $nombre): bool
    {
        $nombreNormalizado = mb_strtolower(NormalizadorNombreClinico::normalizar($nombre));

        foreach ($this->patologiasSeleccionadas as $item) {
            if ($id !== null && $item['id'] === $id) {
                return true;
            }

            if (mb_strtolower(NormalizadorNombreClinico::normalizar($item['nombre'])) === $nombreNormalizado) {
                return true;
            }
        }

        return false;
    }

    protected function reglasPatologiasSeleccionadas(): array
    {
        return [
            'patologiasSeleccionadas' => 'nullable|array',
            'patologiasSeleccionadas.*.nombre' => 'required|string|max:50',
            'busquedaPatologia' => 'nullable|string|max:50',
        ];
    }
}
