import { transformarFecha } from '@compartido/general.js';

export function recolectarPatronSemanal(turnosContainer, frecuenciaSemanal) {
    const selects = turnosContainer.querySelectorAll('.hora-select[data-dia]');

    if (selects.length !== frecuenciaSemanal) {
        throw new Error(
            'Detectamos una inconsistencia en la estructura de la página. Por favor, recargue la página para solucionarlo.'
        );
    }

    const turnos = [];

    selects.forEach(select => {
        const diaSemana = select.dataset.dia;
        const horaInicio = normalizarHoraInicio(select.value);

        if (!diaSemana || !horaInicio) {
            throw new Error('Por favor, seleccione para cada turno un día de la semana y una hora de inicio.');
        }

        turnos.push({
            dia_semana: diaSemana,
            hora_inicio: horaInicio
        });
    });

    return turnos;
}

function normalizarHoraInicio(hora) {
    if (!hora) {
        return hora;
    }

    if (/^\d{2}:\d{2}:\d{2}$/.test(hora)) {
        return hora;
    }

    if (/^\d{2}:\d{2}$/.test(hora)) {
        return `${hora}:00`;
    }

    return hora.replace('hs', '').trim() + ':00';
}

export function recolectarTurnosManuales(turnosContainer, cantidadEsperada) {
    const divsTurnos = turnosContainer.querySelectorAll('.turno');

    if (divsTurnos.length !== cantidadEsperada) {
        throw new Error(
            'Detectamos una inconsistencia en la estructura de la página. Por favor, recargue la página para solucionarlo.'
        );
    }

    const turnos = [];

    divsTurnos.forEach(turno => {
        const selects = turno.querySelectorAll('select');

        if (selects.length < 2) {
            throw new Error(
                'Detectamos una inconsistencia en la estructura de la página. Por favor, recargue la página para solucionarlo.'
            );
        }

        const fechaStr = selects[0].value;
        const horaInicio = selects[1].value;

        if (!fechaStr || !horaInicio) {
            throw new Error('Por favor, seleccione para cada turno una fecha y una hora de inicio.');
        }

        const [hora, minuto] = horaInicio.split(':').map(Number);
        const [anio, mes, dia] = fechaStr.split('-').map(Number);
        const fecha = new Date(anio, mes - 1, dia, hora, minuto, 0, 0);

        turnos.push(transformarFecha(fecha));
    });

    return turnos;
}

export function construirPayloadRegistro({
    idActividad,
    idPaciente,
    idActividadCombo,
    frecuenciaSemanal,
    autogenerados,
    turnos,
    fechaAncla,
    esPlanDual = false
}) {
    const payload = {
        id_actividad: idActividad,
        id_paciente: idPaciente,
        cant_sesiones: frecuenciaSemanal * 4,
        autogenerados,
        frecuencia_semanal: frecuenciaSemanal,
        turnos,
        plan_dual: esPlanDual
    };

    if (!esPlanDual && idActividadCombo !== null) {
        payload.id_actividad_combo = idActividadCombo;
    }

    if (autogenerados) {
        if (!fechaAncla) {
            throw new Error('Por favor, seleccione la fecha de la primera clase.');
        }

        payload.fecha_ancla = fechaAncla;
    }

    return payload;
}

export function construirPayloadKineConOrden({
    idActividad,
    idPaciente,
    mes,
    dia,
    cantidadSesiones,
    frecuenciaSemanal,
    autogenerados,
    turnos,
    fechaAncla
}) {
    const payload = {
        id_actividad: idActividad,
        id_paciente: idPaciente,
        mes,
        dia,
        sesiones_cubiertas: cantidadSesiones,
        autogenerados,
        frecuencia_semanal: frecuenciaSemanal,
        turnos
    };

    if (autogenerados) {
        if (!fechaAncla) {
            throw new Error('Por favor, seleccione la fecha de la primera clase.');
        }

        payload.fecha_ancla = fechaAncla;
    }

    return payload;
}

export function construirPayloadKineSinOrden({
    idActividad,
    idPaciente,
    cantidadSesiones,
    frecuenciaSemanal,
    autogenerados,
    turnos,
    fechaAncla
}) {
    const payload = {
        id_actividad: idActividad,
        id_paciente: idPaciente,
        cant_sesiones: cantidadSesiones,
        autogenerados,
        frecuencia_semanal: frecuenciaSemanal,
        turnos
    };

    if (autogenerados) {
        if (!fechaAncla) {
            throw new Error('Por favor, seleccione la fecha de la primera clase.');
        }

        payload.fecha_ancla = fechaAncla;
    }

    return payload;
}
