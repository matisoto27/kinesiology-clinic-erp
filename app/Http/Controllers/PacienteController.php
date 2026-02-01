<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlmacenarPacienteRequest;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PacienteController extends Controller
{
    public function crear()
    {
        return view('pacientes.crear');
    }

    public function almacenar(AlmacenarPacienteRequest $request)
    {
        $validados = $request->validated();

        DB::beginTransaction();

        try {
            $contactos = $validados['contactos'] ?? [];
            $sintomas = $validados['sintomas'] ?? [];

            $paciente = Paciente::create($validados);

            if (!empty($contactos)) {
                $paciente->contactosEmergencia()->createMany($contactos);
            }

            if (!empty($sintomas)) {
                $paciente->sintomas()->attach($sintomas, ['fecha_desde' => Carbon::now()]);
            }

            DB::commit();

            return redirect()->route('pacientes.inicio')->with('exito', '¡El paciente ha sido registrado con éxito!');

        } catch (Throwable $ex) {
            DB::rollBack();
            Log::error('[PacienteController@almacenar] Error al crear el paciente', ['excepción' => $ex->getMessage()]);

            return back()->withInput()->with('error', 'Ocurrió un error inesperado al intentar registrar el paciente.');
        }
    }

    public function obtenerActividadesGeneralesSinSuscripcion(int $id)
    {
        try {

            $paciente = Paciente::findOrFail($id);

            $actividades = $paciente->obtenerActividadesGeneralesSinSuscripcion();

            return response()->json($actividades);

        } catch (ModelNotFoundException $ex) {

            Log::info('[PacienteController@obtenerActividadesGeneralesSinSuscripcion] Paciente no encontrado', [
                'id_paciente' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Paciente no encontrado.'
            ], 404);

        } catch (Throwable $ex) {

            Log::error('[PacienteController@obtenerActividadesGeneralesSinSuscripcion] Error al obtener las actividades sin suscripción', [
                'id_paciente' => $id,
                'excepcion' => $ex->getMessage()
            ]);

            return response()->json([
                'error' => 'Se ha producido un error inesperado al obtener las actividades sin suscripción.'
            ], 500);
        }
    }

    public function inicio()
    {
        $columnas = [
            'dni' => 'DNI',
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'fecha_nac' => 'Fecha de nacimiento',
            'edad' => 'Edad',
            'domicilio' => 'Domicilio',
            'telefono' => 'Teléfono',
            'profesion' => 'Profesión',
            'created_at' => 'Fecha de ingreso'
        ];

        $pacientes = Paciente::with('sintomasActivos:id,nombre')
            ->get()
            ->map(function ($paciente) {
                return [
                    'id'         => $paciente->id,
                    'dni'        => $paciente->dni,
                    'nombre'     => $paciente->nombre,
                    'apellido'   => $paciente->apellido,
                    'fecha_nac'   => $paciente->fecha_nac->format('d-m-Y'),
                    'edad'       => $paciente->edad,
                    'domicilio'   => $paciente->domicilio,
                    'telefono'   => $paciente->telefono,
                    'profesion'   => $paciente->profesion,
                    'actividad_fisica'   => $paciente->actividad_fisica,
                    'es_adulto_mayor'   => $paciente->es_adulto_mayor,
                    'vive_con'   => $paciente->vive_con,
                    'sesiones_a_favor'   => $paciente->sesiones_a_favor,
                    'created_at' => $paciente->created_at->format('d-m-Y'),
                    'sintomas' => $paciente->sintomasActivos->map(function ($sintoma) {
                        return [
                            'id' => $sintoma->id,
                            'nombre' => $sintoma->nombre,
                            'fecha_desde' => $sintoma->pivot->fecha_desde->format('d-m-Y')
                        ];
                    })
                ];
            });

        return view('pacientes.inicio', compact('columnas', 'pacientes'));
    }

    public function buscarPorNombre(Request $request)
    {
        $nombre = $request->input('consulta', '');

        try {
            if (strlen($nombre) < 2) {
                return response()->json(['pacientes' => []], 200);
            }

            $consultaPacientes = Paciente::select('id', 'nombre', 'apellido')
                ->where('nombre', 'like', "$nombre%")
                ->limit(10)
                ->orderBy('apellido')
                ->orderBy('nombre');

            if ($request->boolean('incluir_obra')) {
                $consultaPacientes->with(['afiliacionVigente' => function ($consulta) {
                    $consulta->select('obras_sociales.id', 'obras_sociales.nombre');
                }]);
            }

            $pacientes = $consultaPacientes->get();

            return response()->json(['pacientes' => $pacientes], 200);

        } catch (Throwable $ex) {
            Log::error('[PacienteController@buscarPorNombre]', [
                'consulta' => $request->input('consulta', ''),
                'excepción' => $ex->getMessage()
            ]);

            return response()->json(['error' => 'Falla interna del servidor. Por favor, inténtelo de nuevo más tarde.'], 500);
        }
    }

    public function editar(Paciente $paciente)
    {
        if (session()->hasOldInput()) {
            $esAdultoMayor = old('es_adulto_mayor') === 'on';
            $viveSolo = old('vive_solo') === 'on';
            $contactos = old('contactos', []);
            $sintomas = old('sintomas', []);
        } else {
            $esAdultoMayor = $paciente->es_adulto_mayor;
            $viveSolo = $paciente->vive_con === null || $paciente->vive_con === 'SOLO';
            $contactos = $paciente->contactosEmergencia()
                ->get(['id', 'nombre', 'telefono', 'vinculo'])
                ->toArray();
            $sintomas = $paciente->sintomasActivos()->pluck('sintomas.id')->toArray();
        }

        return view('pacientes.editar', compact(
            'paciente',
            'esAdultoMayor',
            'viveSolo',
            'contactos',
            'sintomas'
        ));
    }

    public function actualizar(AlmacenarPacienteRequest $request, Paciente $paciente)
    {
        $validados = $request->validated();

        DB::beginTransaction();

        try {
            if ($validados['es_adulto_mayor']) {
                $contactos = collect($validados['contactos'] ?? []);
                $idsContactos = $contactos->pluck('id')->filter()->toArray();

                $paciente->contactosEmergencia()->whereNotIn('id', $idsContactos)->delete();

                foreach ($contactos as $contacto) {
                    $paciente->contactosEmergencia()->updateOrCreate(
                        ['id' => $contacto['id'] ?? null],
                        [
                            'nombre'   => $contacto['nombre'],
                            'telefono' => $contacto['telefono'],
                            'vinculo'  => $contacto['vinculo']
                        ]
                    );
                }
            } else {
                $validados['vive_con'] = null;
                $paciente->contactosEmergencia()->delete();
            }

            $paciente->update($validados);

            $sintomasEnviados = $validados['sintomas'] ?? [];
            $sintomasActivosPaciente = $paciente->sintomasActivos()->pluck('sintomas.id')->toArray();

            $sintomasAFinalizar = array_diff($sintomasActivosPaciente, $sintomasEnviados);

            if (!empty($sintomasAFinalizar)) {
                foreach ($sintomasAFinalizar as $idSintoma) {
                    $paciente->sintomas()
                        ->wherePivotNull('fecha_hasta')
                        ->updateExistingPivot($idSintoma, [
                            'fecha_hasta' => now()
                        ]);
                }
            }

            $sintomasParaCrear = array_diff($sintomasEnviados, $sintomasActivosPaciente);
            if (!empty($sintomasParaCrear)) {
                $paciente->sintomas()->attach($sintomasParaCrear);
            }

            DB::commit();

            return redirect()->route('pacientes.inicio')->with('exito', '¡La información del paciente ha sido actualizada con éxito!');

        } catch (Throwable $ex) {
            DB::rollBack();
            Log::error('[PacienteController@actualizar] Error al actualizar el paciente', ['excepción' => $ex->getMessage()]);

            return back()->withInput()->with('error', 'Ocurrió un error inesperado al intentar actualizar la información del paciente.');
        }
    }

    public function eliminar(Paciente $paciente)
    {
        $paciente->delete();

        return redirect()->back()->with('exito', 'El paciente ha sido eliminado correctamente.');
    }
}
