<?php

namespace App\Http\Requests;

use App\Models\Actividad;
use App\Models\ActividadCombo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlmacenarTurnoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $turnosAutogenerados = $this->boolean('autogenerados');
        $esConOrden = $this->esConOrden();

        return [
            'id_actividad' => 'required|integer|exists:actividades,id',
            'id_paciente' => 'required|integer|exists:pacientes,id',
            'autogenerados' => 'required|boolean',
            'desde_actual' => 'required|boolean',
            'frecuencia_semanal' => 'required|integer|min:1|max:5',

            'sesiones_cubiertas' => [Rule::requiredIf($esConOrden), 'integer', 'in:5,10'],
            'mes' => [Rule::requiredIf($esConOrden), 'integer', 'min:1', 'max:12'],
            'dia' => [Rule::requiredIf($esConOrden), 'integer', 'min:1', 'max:31'],

            'cant_sesiones' => [Rule::requiredIf(!$esConOrden), 'integer', 'min:1', 'max:20'],
            'id_actividad_combo' => [
                Rule::requiredIf($this->esActividadGeneral()),
                'nullable',
                'integer',
                'exists:actividades_combos,id',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value === null) {
                        return;
                    }

                    $pertenece = ActividadCombo::activo()
                        ->where('id', $value)
                        ->where('id_actividad', $this->input('id_actividad'))
                        ->exists();

                    if (!$pertenece) {
                        $fail('El combo seleccionado no pertenece a la actividad indicada.');
                    }
                },
            ],

            'turnos' => [
                'required',
                'array',
                function (string $attribute, mixed $value, Closure $fail) use ($turnosAutogenerados, $esConOrden) {
                    $mensajeError = "Cantidad de turnos no válida.";
                    $cantidad = count($value);

                    if ($turnosAutogenerados) {
                        if ($cantidad < 1 || $cantidad > 5) {
                            $fail($mensajeError);
                        }
                    } else {
                        $valorEsperado = $esConOrden
                            ? ($this->sesiones_cubiertas ?? 0)
                            : ($this->cant_sesiones ?? 0);
                        if ($cantidad != $valorEsperado) {
                            $fail($mensajeError);
                        }
                    }
                }
            ],

            'turnos.*' => Rule::unless($turnosAutogenerados, 'date_format:Y-m-d H:i:s'),
            'turnos.*.dia_semana' => 'required_if:autogenerados,true|string|in:Lunes,Martes,Miércoles,Jueves,Viernes',
            'turnos.*.hora_inicio' => 'required_if:autogenerados,true|string|date_format:H:i:s'
        ];
    }

    public function messages(): array
    {
        return [
            'id_actividad.exists' => 'La actividad ingresada no existe.',
            'id_paciente.exists' => 'El paciente ingresado no existe.'
        ];
    }

    public function attributes(): array
    {
        return [
            'id_actividad' => 'actividad',
            'id_paciente' => 'paciente',
            'cant_sesiones' => 'cantidad de sesiones',
            'id_actividad_combo' => 'combo de la actividad'
        ];
    }

    private function esConOrden(): bool
    {
        return $this->filled('sesiones_cubiertas')
            || $this->filled('mes')
            || $this->filled('dia');
    }

    private function esActividadGeneral(): bool
    {
        $idActividad = $this->input('id_actividad');

        if (!$idActividad) {
            return false;
        }

        return Actividad::find($idActividad)?->esActividadGeneral() ?? false;
    }
}
