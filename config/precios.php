<?php

return [
    // Clase de prueba (Gimnasio o Pilates): mismo precio para ambas actividades.
    'clase_prueba' => (float) env('PRECIO_CLASE_PRUEBA', 0),

    // Monto por defecto al registrar un copago de kinesio con orden.
    'copago' => (float) env('PRECIO_COPAGO', 7000),
];
