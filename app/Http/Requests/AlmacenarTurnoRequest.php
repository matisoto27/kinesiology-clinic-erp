<?php

namespace App\Http\Requests;

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
        $esConOrden = !$this->has('total_a_pagar') || $this->has('mes') || $this->has('dia');

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
            'total_a_pagar' => [Rule::requiredIf(!$esConOrden), 'numeric', 'gt:0'],

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
            'cant_sesiones' => 'cantidad de sesiones'
        ];
    }
}
