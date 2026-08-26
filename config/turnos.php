<?php

return [
    // Días de anticipación con los que se genera la próxima inscripción mensual de un paciente fijo
    // antes de que finalice su ciclo actual.
    'dias_anticipacion_renovacion' => (int) env('TURNOS_DIAS_ANTICIPACION_RENOVACION', 28),
];
