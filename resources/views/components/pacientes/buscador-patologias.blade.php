@include('components.pacientes.partials.buscador-clinico', [
    'etiqueta' => 'Patologías (Opcional)',
    'inputId' => 'input-busqueda-patologia',
    'placeholder' => 'Escriba para buscar una patología',
    'campoBusqueda' => 'busquedaPatologia',
    'propiedadSugerencias' => 'sugerenciasPatologias',
    'propiedadSeleccionados' => 'patologiasSeleccionadas',
    'metodoSeleccion' => 'seleccionarPatologia',
    'metodoPendiente' => 'agregarPatologiaPendiente',
    'metodoQuitar' => 'quitarPatologia',
    'chipKeyPrefix' => 'patologia',
    'campoErrorBusqueda' => 'busquedaPatologia',
    'campoErrorSeleccionados' => 'patologiasSeleccionadas',
])
