@include('components.pacientes.partials.buscador-clinico', [
    'etiqueta' => '¿Cuáles síntomas presenta el paciente? (Opcional)',
    'inputId' => 'input-busqueda-sintoma',
    'placeholder' => 'Escriba para buscar un síntoma',
    'campoBusqueda' => 'busquedaSintoma',
    'propiedadSugerencias' => 'sugerenciasSintomas',
    'propiedadSeleccionados' => 'sintomasSeleccionados',
    'metodoSeleccion' => 'seleccionarSintoma',
    'metodoPendiente' => 'agregarSintomaPendiente',
    'metodoQuitar' => 'quitarSintoma',
    'chipKeyPrefix' => 'sintoma',
    'campoErrorBusqueda' => 'busquedaSintoma',
    'campoErrorSeleccionados' => 'sintomasSeleccionados',
])
