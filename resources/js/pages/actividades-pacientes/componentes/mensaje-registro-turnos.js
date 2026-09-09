import { convertirFechaParaMostrar } from '@compartido/general.js';

/**
 * @param {string} fechaHoraStr Formato "Y-m-d H:i:s"
 */
export function formatearFechaHoraTurno(fechaHoraStr) {
    const [fecha, horaCompleta = '00:00:00'] = fechaHoraStr.split(' ');
    const [hora, minutos] = horaCompleta.split(':');

    return `${convertirFechaParaMostrar(fecha)}, ${hora}:${minutos}`;
}

/**
 * @param {Array<{solicitado: string, asignado: string}>} [reemplazos]
 */
export function mensajeExitoRegistroTurnos(reemplazos = []) {
    let mensaje = 'Los turnos del paciente han sido registrados correctamente.';

    if (!Array.isArray(reemplazos) || reemplazos.length === 0) {
        return mensaje;
    }

    const lineas = reemplazos.map((reemplazo) =>
        `• ${formatearFechaHoraTurno(reemplazo.solicitado)} → ${formatearFechaHoraTurno(reemplazo.asignado)}`
    );

    return `${mensaje}\n\nAlgunos turnos no tenían disponibilidad y se reasignaron:\n${lineas.join('\n')}`;
}
