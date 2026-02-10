<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PacienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dni' => $this->dni,
            'nombre_completo'  => $this->nombre_completo,
            'fecha_nac' => $this->fecha_nacimiento,
            'edad' => $this->edad,
            'domicilio' => $this->domicilio,
            'telefono' => $this->telefono,
            'profesion' => $this->profesion,
            'actividad_fisica' => $this->actividad_fisica,
            'es_adulto_mayor' => $this->es_adulto_mayor,
            'vive_con' => $this->vive_con,
            'sesiones_a_favor' => $this->sesiones_a_favor,
            'created_at' => $this->fecha_ingreso,
            'obra_social' => $this->afiliacionVigente?->nombre,
            'patologias' => $this->transformarRelacion($this->whenLoaded('patologias')),
            'sintomas' => $this->transformarRelacion($this->whenLoaded('sintomasActivos'))
        ];
    }

    private function transformarRelacion($coleccion)
    {
        return $coleccion->map(fn($elemento) => [
            'id'          => $elemento->id,
            'nombre'      => $elemento->nombre,
            'fecha_desde' => $elemento->pivot->fecha_desde->format('d-m-Y')
        ]);
    }
}
