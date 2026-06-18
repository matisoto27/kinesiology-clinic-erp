import {
    inicioContainer,
    primerTurnoSelect,
    turnosContainer
} from './dom-turnos.js';

import { estado } from './estado-formulario.js';

import {
    agregarOpcion,
    apiFetch,
    convertirFechaParaMostrar,
    crearOpcionPorDefecto,
    habilitarElemento,
    mostrarAlerta
} from '@compartido/general.js';

export async function manejarTurnosManuales() {

    resetearEstadoManual();

    estadoManual.frecuenciaSemanal = estado.frecuenciaSemanal;

    const { turnos, fechasPorSemana } = await cargarTurnosDisponibles();

    estadoManual.turnos = turnos;
    estadoManual.fechasPorSemana = fechasPorSemana;

    inicioContainer.classList.remove('hidden');
}

function resetearEstadoManual() {

    estadoManual.frecuenciaSemanal = null;
    estadoManual.turnos = [];
    estadoManual.fechasPorSemana = [];
    estadoManual.semanaInicio = null;
    estadoManual.fechasDeterminacion = [];
    estadoManual.patron = null;

    habilitarElemento(primerTurnoSelect, false);
    primerTurnoSelect.innerHTML = crearOpcionPorDefecto('Seleccione una fecha');
    turnosContainer.innerHTML = '';
}

async function cargarTurnosDisponibles() {

    const fechaComienzoStr = obtenerPrimerLunes();

    const fechaFin = new Date(`${fechaComienzoStr}T00:00:00`);
    const semanasNecesarias = Math.ceil(
        obtenerTotalTurnos() / estadoManual.frecuenciaSemanal
    );
    fechaFin.setDate(fechaFin.getDate() + (semanasNecesarias * 7) + 14);
    const fechaFinStr = formatearFechaISO(fechaFin);

    const turnos = await apiFetch(
        `/actividades/${estado.idActividad}/turnos-disponibles?id_paciente=${estado.idPaciente}&fecha_comienzo=${fechaComienzoStr}&fecha_fin=${fechaFinStr}`
    );

    const fechasTurnos = [...new Set(turnos.map(turno => turno.split(' ')[0]))];

    return {
        turnos,
        fechasPorSemana: agruparFechasPorSemana(fechasTurnos)
    };
}

function obtenerPrimerLunes() {

    const ahora = new Date();
    const diaActual = ahora.getDay();
    const hora = ahora.getHours();
    const minutos = ahora.getMinutes();

    const fechaResultado = new Date(ahora);
    const diferenciaHastaLunes = diaActual === 0 ? -6 : 1 - diaActual;
    fechaResultado.setDate(ahora.getDate() + diferenciaHastaLunes);

    const forzarSemanaSiguiente =
        diaActual === 0 ||
        diaActual === 6 ||
        (diaActual === 5 && (hora > 19 || (hora === 19 && minutos >= 30)));

    if (forzarSemanaSiguiente) {
        fechaResultado.setDate(fechaResultado.getDate() + 7);
    }

    return formatearFechaISO(fechaResultado);
}

function agruparFechasPorSemana(fechasTurnos) {

    const semanas = fechasTurnos.reduce((acc, fechaStr) => {

        const fecha = new Date(`${fechaStr}T00:00:00`);

        const lunes = new Date(fecha);
        const dia = lunes.getDay();
        const diferencia = dia === 0 ? -6 : 1 - dia;
        lunes.setDate(lunes.getDate() + diferencia);

        const semanaKey = formatearFechaISO(lunes);

        if (!acc[semanaKey]) {
            acc[semanaKey] = [];
        }

        acc[semanaKey].push(fechaStr);
        return acc;
    }, {});

    return Object.values(semanas).map(fechas => fechas.sort());
}

function formatearFechaISO(fecha) {

    const yyyy = fecha.getFullYear();
    const mm = String(fecha.getMonth() + 1).padStart(2, '0');
    const dd = String(fecha.getDate()).padStart(2, '0');

    return `${yyyy}-${mm}-${dd}`;
}

export function manejarCambioSemanaManual(tipoInicio) {

    limpiarPatron();
    cargarPrimerTurnoSelect(tipoInicio);
}

function limpiarPatron() {

    estadoManual.fechasDeterminacion = [];
    estadoManual.patron = null;

    turnosContainer.innerHTML = '';
}

function cargarPrimerTurnoSelect(tipoInicio) {

    primerTurnoSelect.innerHTML = crearOpcionPorDefecto('Seleccione una fecha');

    estadoManual.semanaInicio = tipoInicio === 'actual' ? 0 : 1;
    const fechasDisponibles = estadoManual.fechasPorSemana[estadoManual.semanaInicio] ?? [];

    fechasDisponibles.forEach(fecha => {
        agregarOpcion(
            primerTurnoSelect,
            fecha,
            convertirFechaParaMostrar(fecha)
        );
    });

    habilitarElemento(primerTurnoSelect, fechasDisponibles.length > 0);
}

export function manejarPrimerTurnoManual() {

    const fecha = primerTurnoSelect.value;
    if (!fecha) {
        return;
    }

    estadoManual.fechasDeterminacion = [fecha];
    turnosContainer.innerHTML = '';

    renderizarPrimerTurno(fecha);
    continuarDeterminacionPatron();
}

function renderizarPrimerTurno(fecha) {
    const html = `
        <div class="fila-formulario turno-determinacion turno-determinacion-fijo" data-turno="1">
            <label class="etiqueta-formulario">${etiquetaTurno(1)}</label>
            <div class="columna-campo">
                <label class="etiqueta-formulario">Fecha</label>
                <select class="entrada fecha-determinacion" disabled>
                    <option value="${fecha}" selected>${convertirFechaParaMostrar(fecha)}</option>
                </select>
            </div>
        </div>
    `;

    turnosContainer.insertAdjacentHTML('beforeend', html);
}

function manejarFechaDeterminacion(numeroTurno, select) {

    const fecha = select.value;
    if (!fecha) {
        return;
    }

    truncarDeterminacionDesde(numeroTurno);
    estadoManual.fechasDeterminacion.push(fecha);

    continuarDeterminacionPatron();
}

function truncarDeterminacionDesde(numeroTurno) {

    estadoManual.fechasDeterminacion = estadoManual.fechasDeterminacion.slice(0, numeroTurno - 1);

    turnosContainer.querySelectorAll('.turno-determinacion').forEach(elemento => {
        if (Number(elemento.dataset.turno) > numeroTurno) {
            elemento.remove();
        }
    });
}

function continuarDeterminacionPatron() {

    const { frecuenciaSemanal, fechasDeterminacion, fechasPorSemana } = estadoManual;

    if (patronDeterminado(frecuenciaSemanal, fechasDeterminacion, fechasPorSemana)) {
        finalizarPatron();
        return;
    }

    renderizarSelectDeterminacion(fechasDeterminacion.length + 1);
}

function patronDeterminado(frecuencia, fechasSeleccionadas, fechasPorSemana) {

    const indiceSemana = obtenerIndiceSemana(fechasSeleccionadas[0], fechasPorSemana);

    const cantidadFechasSemana = fechasSeleccionadas.filter(
        f => obtenerIndiceSemana(f, fechasPorSemana) === indiceSemana
    ).length;

    if (cantidadFechasSemana >= frecuencia) {
        return true;
    }

    const ultimaFecha = fechasSeleccionadas.at(-1);
    const indiceUltimaSemana = obtenerIndiceSemana(ultimaFecha, fechasPorSemana);

    if (indiceUltimaSemana > indiceSemana) {
        return true;
    }

    const fechasSemanaInicio = fechasPorSemana[indiceSemana] ?? [];
    const ultimoDiaSemana = fechasSemanaInicio.at(-1);

    return ultimaFecha === ultimoDiaSemana;
}

function renderizarSelectDeterminacion(numeroTurno) {

    const ultimaFecha = estadoManual.fechasDeterminacion.at(-1);
    const fechasPosibles = obtenerFechasPosteriores(ultimaFecha);

    if (!fechasPosibles.length) {
        finalizarPatron();
        return;
    }

    const html = `
        <div class="fila-formulario turno-determinacion" data-turno="${numeroTurno}">
            <label class="etiqueta-formulario">${etiquetaTurno(numeroTurno)}</label>
            <div class="columna-campo">
                <label class="etiqueta-formulario">Fecha</label>
                <select class="entrada fecha-determinacion">
                    ${crearOpcionPorDefecto('Seleccione una fecha')}
                    ${fechasPosibles.map(fecha => `
                        <option value="${fecha}">${convertirFechaParaMostrar(fecha)}</option>
                    `).join('')}
                </select>
            </div>
        </div>
    `;

    turnosContainer.insertAdjacentHTML('beforeend', html);

    const select = turnosContainer.querySelector(
        `.turno-determinacion[data-turno="${numeroTurno}"] .fecha-determinacion`
    );

    select.addEventListener('change', () => manejarFechaDeterminacion(numeroTurno, select));
}

function obtenerFechasPosteriores(fechaAnterior) {

    const { fechasPorSemana, semanaInicio } = estadoManual;

    const fechasSemanaElegida = fechasPorSemana[semanaInicio] ?? [];
    const fechasSemanaSiguiente = fechasPorSemana[semanaInicio + 1] ?? [];

    return [...fechasSemanaElegida, ...fechasSemanaSiguiente].filter(fecha => fecha > fechaAnterior);
}

async function finalizarPatron() {

    const { frecuenciaSemanal, fechasDeterminacion, fechasPorSemana } = estadoManual;

    const patron = calcularPatron(frecuenciaSemanal, fechasDeterminacion, fechasPorSemana);
    const indiceSemanaBase = obtenerIndiceSemana(fechasDeterminacion[0], fechasPorSemana);

    if (!patronEsFactible(patron, indiceSemanaBase, fechasPorSemana)) {
        await mostrarAlerta(
            'error',
            'Turnos insuficientes',
            'No hay suficientes turnos disponibles como para cubrir la frecuencia semanal seleccionada.'
        );
        resetearFlujoManual();
        return;
    }

    estadoManual.patron = patron;
    renderizarEstructuraDefinitiva(indiceSemanaBase);

    if (frecuenciaSemanal === 5) {
        autocompletarFrecuenciaCinco(indiceSemanaBase);
    } else {
        aplicarFechasDeterminacion();
    }
}

function calcularPatron(frecuencia, fechas, fechasPorSemana) {

    const totalSesiones = obtenerTotalTurnos();
    const semanaInicio = obtenerIndiceSemana(fechas[0], fechasPorSemana);

    const cantidadPrimeraSemana = fechas.filter(
        f => obtenerIndiceSemana(f, fechasPorSemana) === semanaInicio
    ).length;

    if (cantidadPrimeraSemana >= frecuencia) {
        const totalSemanas = Math.ceil(totalSesiones / frecuencia);
        const patron = Array(totalSemanas).fill(frecuencia);
        ajustarPatronATotal(patron, totalSesiones);

        return patron;
    }

    const patron = [cantidadPrimeraSemana];
    let acumulado = cantidadPrimeraSemana;

    while (acumulado < totalSesiones) {
        const faltante = totalSesiones - acumulado;
        patron.push(Math.min(frecuencia, faltante));
        acumulado += patron.at(-1);
    }

    return patron;
}

function obtenerIndiceSemana(fecha, fechasPorSemana) {
    return fechasPorSemana.findIndex(semana => semana.includes(fecha));
}

function ajustarPatronATotal(patron, totalSesiones) {
    let suma = patron.reduce((acumulado, valor) => acumulado + valor, 0);

    while (suma > totalSesiones) {
        patron[patron.length - 1]--;
        suma--;
    }
}

function patronEsFactible(patron, indiceSemanaBase, fechasPorSemana) {
    return patron.every((cantidad, indice) => {
        const fechasSemana = fechasPorSemana[indiceSemanaBase + indice] ?? [];
        return fechasSemana.length >= cantidad;
    });
}

function resetearFlujoManual() {

    limpiarPatron();

    const radioSeleccionado = inicioContainer.querySelector('input[name="inicio"]:checked');
    if (radioSeleccionado) {
        cargarPrimerTurnoSelect(radioSeleccionado.value);
    } else {
        habilitarElemento(primerTurnoSelect, false);
        primerTurnoSelect.innerHTML = crearOpcionPorDefecto('Seleccione una fecha');
    }
}

function renderizarEstructuraDefinitiva(indiceSemanaBase) {

    turnosContainer.innerHTML = '';

    let numeroTurnoGlobal = 1;

    estadoManual.patron.forEach((cantidad, indice) => {

        turnosContainer.insertAdjacentHTML(
            'beforeend',
            `<h3 class="mb-4 border-t font-medium text-xl text-[#F5D500]">Semana ${indice + 1}</h3>`
        );

        for (let turno = 1; turno <= cantidad; turno++) {

            turnosContainer.insertAdjacentHTML(
                'beforeend',
                `
                <div class="fila-formulario turno" data-semana="${indice}" data-turno="${turno}">
                    <label class="etiqueta-formulario">${etiquetaTurno(numeroTurnoGlobal)}</label>
                    <div class="columna-campo">
                        <label class="etiqueta-formulario">Fecha</label>
                        <select class="entrada fecha-select" required>
                            ${crearOpcionPorDefecto('Seleccione una fecha')}
                        </select>
                    </div>
                    <div class="columna-campo">
                        <label class="etiqueta-formulario">Hora de inicio</label>
                        <select class="entrada hora-select" disabled required>
                            ${crearOpcionPorDefecto('Seleccione un horario')}
                        </select>
                    </div>
                </div>
                `
            );

            numeroTurnoGlobal++;
        }
    });

    inicializarSelectsDefinitivos(indiceSemanaBase);
}

function etiquetaTurno(numeroTurno) {
    return `Turno ${numeroTurno}/${obtenerTotalTurnos()}`;
}

function obtenerTotalTurnos() {

    if (estado.cantidadSesiones !== null && estado.cantidadSesiones !== undefined) {
        return estado.cantidadSesiones;
    }

    return estadoManual.frecuenciaSemanal * 4;
}

function inicializarSelectsDefinitivos(semanaBase) {
    turnosContainer.querySelectorAll('.turno').forEach(bloque => {

        const indicePatron = Number(bloque.dataset.semana);
        const indiceSemanaReal = semanaBase + indicePatron;
        const fechasSemana = estadoManual.fechasPorSemana[indiceSemanaReal] ?? [];

        const fechaSelect = bloque.querySelector('.fecha-select');
        const horaSelect = bloque.querySelector('.hora-select');

        fechasSemana.forEach(fecha => {
            agregarOpcion(
                fechaSelect,
                fecha,
                convertirFechaParaMostrar(fecha)
            );
        });

        fechaSelect.addEventListener('change', () => {
            actualizarDuplicadosSemana(indicePatron);
            cargarHorariosSelect(fechaSelect, horaSelect);
        });
    });
}

function autocompletarFrecuenciaCinco(semanaBase) {

    const fechasDeterminacion = estadoManual.fechasDeterminacion;
    const semanasActualizadas = new Set();

    let indiceGlobal = 0;

    turnosContainer.querySelectorAll('.turno').forEach(bloque => {

        const indicePatron = Number(bloque.dataset.semana);
        const indiceSemanaReal = semanaBase + indicePatron;
        const fechasSemana = estadoManual.fechasPorSemana[indiceSemanaReal] ?? [];

        const numeroTurno = Number(bloque.dataset.turno);
        const fechaSelect = bloque.querySelector('.fecha-select');
        const horaSelect = bloque.querySelector('.hora-select');

        const fecha = indiceGlobal < fechasDeterminacion.length
            ? fechasDeterminacion[indiceGlobal]
            : fechasSemana[numeroTurno - 1];

        if (!fecha) {
            indiceGlobal++;
            return;
        }

        fechaSelect.value = fecha;
        fechaSelect.disabled = debeDeshabilitarFechaFrecuenciaCinco(indicePatron, indiceGlobal);

        semanasActualizadas.add(indicePatron);
        cargarHorariosSelect(fechaSelect, horaSelect);
        indiceGlobal++;
    });

    semanasActualizadas.forEach(actualizarDuplicadosSemana);
}

function debeDeshabilitarFechaFrecuenciaCinco(indicePatron, indiceGlobal) {

    const patron = estadoManual.patron;

    if (patron.length !== 5) {
        return true;
    }

    const ultimaSemanaPatron = patron.length - 1;

    if (indicePatron === 0) {
        return indiceGlobal === 0;
    }

    if (indicePatron === ultimaSemanaPatron) {
        return false;
    }

    return true;
}

function aplicarFechasDeterminacion() {
    const fechas = estadoManual.fechasDeterminacion;
    const bloques = turnosContainer.querySelectorAll('.turno');
    const semanasActualizadas = new Set();

    bloques.forEach((bloque, indice) => {
        if (indice >= fechas.length) {
            return;
        }

        const fechaSelect = bloque.querySelector('.fecha-select');
        const horaSelect = bloque.querySelector('.hora-select');
        const indicePatron = Number(bloque.dataset.semana);

        fechaSelect.value = fechas[indice];

        if (indice === 0) {
            fechaSelect.disabled = true;
        }

        semanasActualizadas.add(indicePatron);
        cargarHorariosSelect(fechaSelect, horaSelect);
    });

    semanasActualizadas.forEach(actualizarDuplicadosSemana);
}

function cargarHorariosSelect(fechaSelect, horaSelect) {

    horaSelect.innerHTML = crearOpcionPorDefecto('Seleccione un horario');

    const fecha = fechaSelect.value;
    if (!fecha) {
        habilitarElemento(horaSelect, false);
        return;
    }

    const horarios = obtenerHorariosDisponibles(fecha);
    horarios.forEach(hora => {
        agregarOpcion(horaSelect, hora, `${hora.slice(0, 5)}hs`);
    });
    habilitarElemento(horaSelect, horarios.length > 0);
}

function obtenerHorariosDisponibles(fecha) {
    return estadoManual.turnos
        .filter(turno => turno.startsWith(`${fecha} `))
        .map(turno => turno.split(' ')[1]);
}

function actualizarDuplicadosSemana(indicePatron) {

    const selects = turnosContainer.querySelectorAll(
        `.turno[data-semana="${indicePatron}"] .fecha-select`
    );

    const elegidas = Array.from(selects)
        .map(select => select.value)
        .filter(Boolean);

    selects.forEach(select => {
        Array.from(select.options).forEach(option => {

            if (!option.value) {
                return;
            }

            option.disabled =
                elegidas.includes(option.value) &&
                option.value !== select.value;
        });
    });
}

const estadoManual = {
    frecuenciaSemanal: null,
    turnos: [],
    fechasPorSemana: [],
    semanaInicio: null,
    fechasDeterminacion: [],
    patron: null
};
