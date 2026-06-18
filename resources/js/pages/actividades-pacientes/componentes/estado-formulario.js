export function faltanDatosObligatorios() {
    return !estado.idPaciente || !estado.idActividad || !estado.frecuenciaSemanal;
}

export function faltanDatosTurnos() {
    if (faltanDatosObligatorios()) {
        return true;
    }

    return estado.cantidadSesiones === null || estado.cantidadSesiones === undefined;
}

export function resetearEstado() {
    estado.idPaciente = null;
    estado.idActividad = null;
    estado.idActividadCombo = null;
    estado.frecuenciaSemanal = null;
    estado.cantidadSesiones = null;
    estado.dias = [];
    estado.semanaInicio = null;
    estado.primerTurno = null;
}

export const estado = {
    idPaciente: null,
    idActividad: null,
    idActividadCombo: null,
    frecuenciaSemanal: null,
    cantidadSesiones: null,
    turnosAutogenerados: true,
    dias: [],
    semanaInicio: null,
    primerTurno: null
};
