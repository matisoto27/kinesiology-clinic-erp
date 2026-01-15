import {
    agregarOpcion,
    crearOpcionPorDefecto,
    DIAS_SEMANA,
    habilitarElemento,
    mostrarAlerta
} from '@compartido/general.js';

import {
    contenedorTurnos,
    precioInput
} from '@compartido/referencias-dom.js';

import { actualizarDesdeActual } from '../componentes/gestor-estado.js';

/**
 * @param {Array<string>} turnos
 * @param {number} cantidadSemanasNecesarias - Cantidad total de semanas necesarias para registrar turnos (N).
 * @returns {Array<Object>} Arreglo con los turnos de las semanas [1] a [N-1].
 */
export function obtenerTurnosSemanasCriticas(turnos, cantidadSemanasNecesarias) {

    if (cantidadSemanasNecesarias <= 1) return [];

    const indiceInicio = 1;
    const indiceFin = cantidadSemanasNecesarias - 1;

    const turnosSemanasCriticas = [];

    for (let i = indiceInicio; i <= indiceFin; i++) {

        const turnosDeLaSemana = obtenerTurnosSemana(turnos, i);

        if (turnosDeLaSemana) {
            turnosSemanasCriticas.push(turnosDeLaSemana);
        }
    }

    return turnosSemanasCriticas;
}

/**
 * Filtra los turnos para una semana específica y los agrupa por fecha.
 * @param {Array<string>} turnosDisponibles - Array de 'YYYY-MM-DD HH:MM'.
 * @param {number} desplazamiento - Desplazamiento semanal (0=Actual, 1=Sig.).
 * @returns {Record<string, string[]>} Objeto de turnos por fecha { 'YYYY-MM-DD': ['HH:MM', ...] }.
 */
export function obtenerTurnosSemana(turnosDisponibles, desplazamiento) {

    const hoy = new Date();
    const [inicio, fin] = obtenerLunesViernesSemana(hoy, desplazamiento);
    const inicioISO = obtenerFechaISO(inicio);
    const finISO = obtenerFechaISO(fin);

    const turnosSemana = {};

    turnosDisponibles.forEach(turno => {

        const [fecha, hora] = turno.split(' ');

        if (fecha >= inicioISO && fecha <= finISO) {
            turnosSemana[fecha] = turnosSemana[fecha] || [];
            turnosSemana[fecha].push(hora);
        }
    });

    return turnosSemana;
}

/**
 * @param {Date} fechaBase
 * @param {number} desplazamiento
 * @returns {[Date, Date]} [Lunes, Viernes] de la semana deseada.
 */
function obtenerLunesViernesSemana(fechaBase, desplazamiento) {

    const copiaFecha = new Date(fechaBase);
    const diaHoy = copiaFecha.getDay();

    const diasAlLunes = (diaHoy === 0) ? 6 : diaHoy - 1;

    copiaFecha.setDate(copiaFecha.getDate() - diasAlLunes + (desplazamiento * 7));
    copiaFecha.setHours(0, 0, 0, 0);
    const lunes = new Date(copiaFecha);

    copiaFecha.setDate(lunes.getDate() + 4);
    copiaFecha.setHours(23, 59, 59, 999);
    const viernes = copiaFecha;

    return [lunes, viernes];
}

/**
 * @param {Date} fechaHora
 * @returns {string} Fecha en formato 'YYYY-MM-DD'.
 */
function obtenerFechaISO(fechaHora) {
    return fechaHora.toISOString().split('T')[0];
}

export function semanaCubreFrecuencia(semana, frecuenciaSemanal) {
    return Object.keys(semana).length >= frecuenciaSemanal;
}

export async function determinarTurnosPorSemana(semanaActualCubre, ultimaSemanaCubre, turnosSemanaActual, turnosSemanasCriticas, turnosUltimaSemana) {
    if (semanaActualCubre && ultimaSemanaCubre) {
        const eleccion = await Swal.fire({
            title: '¿Desea generar los turnos a partir de la semana actual o a partir de la semana que viene?',
            icon: 'question',
            showDenyButton: true,
            confirmButtonText: 'Semana actual',
            denyButtonText: 'Semana que viene'
        });

        if (eleccion.isDismissed) {
            return { accion: 'dismissed' };
        }

        if (eleccion.isConfirmed) {
            return {
                turnosPorSemana: [turnosSemanaActual, ...turnosSemanasCriticas],
                accion: 'confirmed'
            };
        } else if (eleccion.isDenied) {
            return {
                turnosPorSemana: [...turnosSemanasCriticas, turnosUltimaSemana]
            };
        }
    }

    if (semanaActualCubre) {
        return {
            turnosPorSemana: [turnosSemanaActual, ...turnosSemanasCriticas]
        };
    }

    return {
        turnosPorSemana: [...turnosSemanasCriticas, turnosUltimaSemana]
    };
}

/**
 * Consolida turnos de varias semanas por Día de la Semana y Hora.
 * @param {Record<string, string[]>[]} turnosPorSemana - Array de objetos de turnos por fecha.
 * @returns {Record<string, Record<string, number>>} { 'Lunes': { '08:00': 4 } }.
 */
export function consolidarTurnosPorDia(turnosPorSemana) {

    const turnosPorDia = {};

    for (const objetoTurnosSemana of turnosPorSemana) {
        for (const [fechaStr, horas] of Object.entries(objetoTurnosSemana)) {

            const fecha = new Date(`${fechaStr}T12:00:00`);
            const indiceDia = fecha.getDay();

            if (indiceDia === 0 || indiceDia === 6) continue;

            const diaSemana = DIAS_SEMANA[indiceDia - 1];

            turnosPorDia[diaSemana] ||= {};

            for (const hora of horas) {
                turnosPorDia[diaSemana][hora] ||= 0;
                turnosPorDia[diaSemana][hora] += 1;
            }
        }
    }

    return turnosPorDia;
}

/**
 * Renderiza el HTML de los selects para la selección de turnos fijos.
 * @param {number} frecuenciaSemanal - Cantidad de turnos a seleccionar.
 * @param {string[]} diasConTurnos - Días de la semana disponibles.
 * @param {HTMLElement} contenedor - El contenedor donde inyectar el HTML.
 */
export function renderizarTurnosFijos(frecuenciaSemanal, diasConTurnos) {
    if (!contenedorTurnos) return;

    let turnoHTML = '';
    
    for (let i = 1; i <= frecuenciaSemanal; i++) {

        turnoHTML += `
            <div class="fila-formulario turno">
                <div class="flex flex-col">
                    <label class="etiqueta-formulario">Turno ${i}</label>
                </div>

                <div class="columna-campo">
                    <label class="etiqueta-formulario">Día de la semana</label>
                    <select class="entrada dia-select" required>
                        <option value="" disabled selected>Seleccione un día</option>
                        ${diasConTurnos.map(dia => `<option value="${dia}">${dia}</option>`).join('')}
                    </select>
                </div>

                <div class="columna-campo">
                    <label class="etiqueta-formulario">Hora de inicio</label>
                    <select class="entrada hora-select" disabled required>
                        <option value="" disabled selected>Seleccione un horario</option>
                    </select>
                </div>
            </div>
        `;
    }
    contenedorTurnos.innerHTML = turnoHTML;
}

/**
 * Deshabilita los días de la semana ya seleccionados en otros selects.
 * @param {NodeListOf<HTMLSelectElement>} diaSelects - Todos los selects de día.
 */
export function actualizarDiasDeshabilitados(diaSelects) {
    const diasSeleccionados = Array.from(diaSelects)
        .map(s => s.value)
        .filter(v => v);

    diaSelects.forEach(select => {
        Array.from(select.options).forEach(option => {

            if (option.value === '') return;

            option.disabled = diasSeleccionados.includes(option.value) && option.value !== select.value;
        });
    });
}

/**
 * Carga los horarios disponibles en el select de hora correspondiente.
 * @param {HTMLSelectElement} select - El select de día que disparó el evento.
 * @param {Record<string, Record<string, number>>} turnosPorDia - Datos consolidados de disponibilidad.
 * @param {number} [cantidadSemanas=4] - Número de semanas necesarias para agendar todos los turnos según la frecuencia semanal.
 */
export function cargarHorarios(select, turnosPorDia, cantidadSemanas = 4) {

    const diaSeleccionado = select.value;
    const horariosDisponibles = turnosPorDia[diaSeleccionado] ?? {};
    const horas = Object.keys(horariosDisponibles).sort();

    const turnoDiv = select.closest('.turno');
    const horaSelect = turnoDiv.querySelector('.hora-select');

    horaSelect.innerHTML = crearOpcionPorDefecto('Seleccione un horario');

    horas.forEach(hora => {
        const [hh, mm] = hora.split(':');
        const horaConvertida = `${hh}:${mm}hs (${horariosDisponibles[hora]} / ${cantidadSemanas} turnos disponibles)`;
        agregarOpcion(horaSelect, hora, horaConvertida);
    });

    habilitarElemento(horaSelect, !!diaSeleccionado);

    horaSelect.addEventListener('change', deshabilitarHoraSeleccionada);
}

export function deshabilitarHoraSeleccionada(event) {
    const horaSelect = event.target;
    const horaSeleccionada = horaSelect.value;

    Array.from(horaSelect.options).forEach(option => {

        if (option.value === '') {
            option.disabled = true;
            return;
        }

        option.disabled = (option.value === horaSeleccionada);
    });
}

export function reiniciarPrecio() {
    if (precioInput) precioInput.value = "$0,00";
}

export function limpiarTurnos() {
    if (contenedorTurnos) contenedorTurnos.innerHTML = '';
    actualizarDesdeActual(false);
}

export async function mostrarErrorTurnosInsuficientes() {
    await mostrarAlerta(
        'error',
        'Turnos insuficientes',
        'No hay suficientes turnos disponibles como para cubrir la frecuencia semanal seleccionada.'
    );
}
