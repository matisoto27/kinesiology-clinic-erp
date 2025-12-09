<?php

namespace App\Http\Controllers;

use App\Models\ActividadPaciente;
use App\Models\Turno;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

const HORA_INICIO_MANANA = 8;
const HORA_FIN_MANANA = 12;
const HORA_INICIO_TARDE = 16;
const HORA_FIN_TARDE = 20;

class ActividadPacienteController extends Controller
{
    public function paginaCrear()
    {
        return view('actividades-pacientes.crear');
    }

    public function crear(Request $request)
    {
        DB::beginTransaction();

        try {

            $autogeneradosInput = $request->input('autogenerados');

            $reglas = [
                'id_actividad' => 'required|integer|exists:actividades,id',
                'id_paciente' => 'required|integer|exists:pacientes,id',
                'cant_sesiones' => 'required|integer|min:4|max:20',
                'total_a_pagar' => 'required|numeric|min:0',
                'autogenerados' => 'required|boolean',
                'desde_actual' => 'required|boolean',
                'turnos' => [
                    'required',
                    'array',
                    Rule::when($autogeneradosInput, [
                        'min:1',
                        'max:5'
                    ]),
                    Rule::when(!$autogeneradosInput, [
                        'min:4',
                        'max:20'
                    ])
                ],
                'turnos.*' => [
                    Rule::when(!$autogeneradosInput, [
                        'string',
                        'date_format:Y-m-d H:i:s'
                    ])
                ]
            ];

            // Turnos autogenerados (Subcampos: dia_semana, hora_inicio)
            $reglas['turnos.*.dia_semana'] = [
                'required_if:autogenerados,true',
                'string',
                Rule::in(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'])
            ];

            $reglas['turnos.*.hora_inicio'] = [
                'required_if:autogenerados,true',
                'string',
                'date_format:H:i:s'
            ];

            $fechaHoy = Carbon::now();

            $datosValidados = $request->validate(
                $reglas,
                [
                    'id_actividad.exists' => 'La actividad ingresada no existe.',
                    'id_paciente.exists' => 'El paciente ingresado no existe.'
                ],
                [
                    'id_actividad' => 'actividad',
                    'id_paciente' => 'paciente',
                    'cant_sesiones' => 'cantidad de sesiones'
                ]
            );
            $datosValidados['fecha_comienzo'] = $fechaHoy;
            $datosValidados['es_fijo'] = false;
            $datosValidados['pago_completado'] = false;

            $actividadPaciente = ActividadPaciente::create($datosValidados);

            $idActividad = $datosValidados['id_actividad'];
            $cantidadSesiones = $datosValidados['cant_sesiones'];
            $turnosAutogenerados = $datosValidados['autogenerados'];
            $desdeActual = $datosValidados['desde_actual'];
            $turnos = $datosValidados['turnos'];
            $diasCarbon = [
                'Lunes' => Carbon::MONDAY,
                'Martes' => Carbon::TUESDAY,
                'Miércoles' => Carbon::WEDNESDAY,
                'Jueves' => Carbon::THURSDAY,
                'Viernes' => Carbon::FRIDAY,
            ];

            $fechaHoraTurnos = [];

            if ($turnosAutogenerados) {

                $fechaBase = Carbon::now();

                if ($desdeActual) {
                    $fechaBase->startOfWeek(Carbon::MONDAY);
                } else {
                    $fechaBase->addWeek()->startOfWeek(Carbon::MONDAY);
                }

                foreach ($turnos as $detalles) {

                    $diaSemana = $detalles['dia_semana']; // EJ: 'Lunes'
                    $horaInicio = $detalles['hora_inicio']; // EJ: '08:00hs'

                    $horaInicioLimpia = str_replace('hs', '', $horaInicio);
                    [$hora, $minuto] = explode(':', $horaInicioLimpia);

                    for ($semana = 0; $semana <= 3; $semana++) {

                        $fechaHoraTurno = $fechaBase->copy();
                        $fechaHoraTurno = $fechaHoraTurno->addWeeks($semana);
                        $fechaHoraTurno = $fechaHoraTurno->dayOfWeek($diasCarbon[$diaSemana]);
                        $fechaHoraTurno->setTime((int)$hora, (int)$minuto, 0);

                        $fechaHoraTurnos[] = $fechaHoraTurno->toDateTimeString();
                    }
                }

                $turnosGenerados = count($fechaHoraTurnos);
                if ($turnosGenerados < $cantidadSesiones) throw new Exception("Se generaron {$turnosGenerados} turnos, pero deberían haberse generado {$cantidadSesiones}.");

                $turnosValidados = [];
                $turnosAInsertar = [];

                foreach($fechaHoraTurnos as $i => $fechaHoraStr) {

                    $fechaHora = Carbon::parse($fechaHoraStr);

                    // Turnos que todavía no han sido validados
                    $turnosRestantes = array_slice($fechaHoraTurnos, $i + 1);

                    $turnosEnConflicto = array_merge($turnosRestantes, $turnosValidados);

                    $turnoOcupado = Turno::estaOcupado($idActividad, $fechaHoraStr);
                    $turnoYaPaso = Turno::yaPaso($fechaHoraStr);

                    if ($turnoOcupado || $turnoYaPaso) {
                        
                        $nuevaFechaHora = Turno::buscarReemplazo($idActividad, $fechaHora, $turnosEnConflicto);

                        if (is_null($nuevaFechaHora)) throw new Exception('No hay suficientes turnos disponibles para cubrir la cantidad de turnos solicitada.');

                        $fechaDefinitiva = $nuevaFechaHora;

                    } else {

                        $fechaDefinitiva = $fechaHora;
                    }

                    $turnosValidados[] = $fechaDefinitiva;

                    $turnosAInsertar[] = [
                        'id_act_pac' => $actividadPaciente->id,
                        'nro_turno' => $i + 1,
                        'fecha_hora' => $fechaDefinitiva,
                        'asiste' => false
                    ];
                }

            } else {

                $nroTurno = 1;
                foreach ($turnos as $fechaTurno) {
                    $turnosAInsertar[] = [
                        'id_act_pac' => $actividadPaciente->id,
                        'nro_turno' => $nroTurno,
                        'fecha_hora' => $fechaTurno,
                        'asiste' => false
                    ];
                    $nroTurno++;
                };
            }

            if (!empty($turnosAInsertar)) {
                Turno::insert($turnosAInsertar);
            }

            DB::commit();

            return response()->noContent();

        } catch (ValidationException $ex) {

            DB::rollBack();
            return response()->json([
                'errores' => $ex->errors()
            ], 422);

        } catch (Throwable $ex) {

            DB::rollBack();
            Log::error('[ActividadPacienteController@crear] Error al registrar los turnos del paciente', ['exception' => $ex->getMessage()]);

            return response()->json([
                'error' => 'Se ha producido un error inesperado al registrar los turnos del paciente.'
            ], 500);
        }
    }
}
