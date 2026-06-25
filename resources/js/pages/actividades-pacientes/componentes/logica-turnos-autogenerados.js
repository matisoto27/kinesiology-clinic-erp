import { DIAS_SEMANA, formatearFechaLocalISO, OFFSET_DIAS, apiFetch } from '../../../compartido/general.js';
import { checkboxes, primerTurnoSelect, turnosContainer } from '../componentes/dom-turnos.js';
import { estado } from './estado-formulario.js';

export async function manejarTurnosAutogenerados() {

    const idActividad = estado.idActividad;
    const idPaciente = estado.idPaciente;
    const diasSeleccionados = obtenerDiasSeleccionados();

    const fechaComienzo = primerTurnoSelect.value;
    const fechasEsperadas = calcularFechasEsperadas(fechaComienzo, diasSeleccionados);
    const fechaFin = fechasEsperadas.at(-1);

    const turnos = await apiFetch(`/actividades/${idActividad}/turnos-disponibles?id_paciente=${idPaciente}&fecha_comienzo=${fechaComienzo}&fecha_fin=${fechaFin}`);
    const fechasDisponibles = new Set(
        turnos.map(
            turno => turno.split(' ')[0]
        )
    );

    const faltantes = fechasEsperadas.filter(fecha => !fechasDisponibles.has(fecha));
    if (faltantes.length > 0) {
        await mostrarErrorTurnosInsuficientes();
        return;
    }

    const turnosAgrupados = agruparTurnosPorFechaHora(turnos, fechasEsperadas);
    renderizarHorarios(diasSeleccionados, turnosAgrupados, fechasEsperadas);
}

export function obtenerDiasSeleccionados() {
    return Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.value);
}

function calcularFechasEsperadas(fechaComienzo, diasSeleccionados) {

    const totalSesiones = obtenerCantidadSesiones();
    const inicio = new Date(`${fechaComienzo}T12:00:00`);
    const diaInicio = inicio.getDay();

    const maxSemanas = Math.ceil(totalSesiones / estado.frecuenciaSemanal) + 2;
    const diasOrdenados = [...diasSeleccionados].sort(
        (a, b) => OFFSET_DIAS[a] - OFFSET_DIAS[b]
    );

    const fechas = [];

    for (let semana = 0; semana <= maxSemanas && fechas.length < totalSesiones; semana++) {
        for (const dia of diasOrdenados) {

            if (fechas.length >= totalSesiones) {
                break;
            }

            const fecha = new Date(inicio);
            fecha.setDate(
                inicio.getDate() +
                (OFFSET_DIAS[dia] - diaInicio) +
                (semana * 7)
            );

            if (fecha >= inicio) {
                fechas.push(formatearFechaLocalISO(fecha));
            }
        }
    }

    return fechas;
}

function obtenerCantidadSesiones() {

    if (estado.cantidadSesiones !== null && estado.cantidadSesiones !== undefined) {
        return estado.cantidadSesiones;
    }

    return estado.frecuenciaSemanal * 4;
}

async function mostrarErrorTurnosInsuficientes() {
    await mostrarAlerta(
        'error',
        'Turnos insuficientes',
        'No hay suficientes turnos disponibles como para cubrir la frecuencia semanal seleccionada.'
    );
}

function agruparTurnosPorFechaHora(turnos, fechasEsperadas) {

    const fechasEsperadasSet = new Set(fechasEsperadas);
    const agrupados = {};

    turnos.forEach(turno => {

        const fecha = turno.split(' ')[0];

        if (!fechasEsperadasSet.has(fecha)) {
            return;
        }

        const fechaHora = new Date(`${fecha}T12:00:00`);
        const dia = DIAS_SEMANA[fechaHora.getDay() - 1];
        const hora = turno.split(' ')[1]?.slice(0, 5);

        if (!hora || !dia) {
            return;
        }

        agrupados[dia] ??= {};
        agrupados[dia][hora] ??= new Set();

        agrupados[dia][hora].add(fecha);
    });

    return agrupados;
}

function renderizarHorarios(diasSeleccionados, turnosPorDia, fechasEsperadas) {

    const sesionesPorDia = contarSesionesEsperadasPorDia(fechasEsperadas);

    let html = '';

    diasSeleccionados.forEach(dia => {

        const horarios = turnosPorDia[dia] ?? {};

        html += `
            <div class="mb-4 columna-campo">
                <label class="etiqueta-formulario">
                    ${dia}
                </label>

                <select class="entrada hora-select" data-dia="${dia}" required>
                    <option value="" disabled selected>
                        Seleccione un horario
                    </option>
        `;

        Object.entries(horarios)
            .sort(([a], [b]) => a.localeCompare(b))
            .forEach(([hora, fechas]) => {
                const disponibles = fechas.size;
                const totalEsperado = sesionesPorDia[dia] ?? 0;

                html += `
                    <option value="${hora}">
                        ${hora} (${disponibles}/${totalEsperado} disp.)
                    </option>
                `;
            });

        html += `
                </select>
            </div>
        `;
    });

    turnosContainer.innerHTML = html;
}

function contarSesionesEsperadasPorDia(fechasEsperadas) {

    const conteo = {};

    fechasEsperadas.forEach(fechaStr => {
        const fecha = new Date(`${fechaStr}T12:00:00`);
        const dia = DIAS_SEMANA[fecha.getDay() - 1];
        conteo[dia] = (conteo[dia] ?? 0) + 1;
    });

    return conteo;
}
