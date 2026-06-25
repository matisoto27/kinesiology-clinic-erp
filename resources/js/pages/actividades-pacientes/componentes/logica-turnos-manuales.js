import {
    inicioContainer,
    radioActual,
    radioSiguiente,
    primerTurnoSelect,
    turnosContainer
} from './dom-turnos.js';

import { estado } from './estado-formulario.js';

import {
    agregarOpcion,
    apiFetch,
    convertirFechaParaMostrar,
    crearOpcionPorDefecto,
    esFechaValidaSemanaActual,
    formatearFechaLocalISO,
    habilitarElemento,
    mostrarAlerta
} from '@compartido/general.js';
import {
    esSegundoPaso,
    fechasOcupadasPrimeraInscripcion,
    frecuenciaPrimeraInscripcion
} from './logica-dual-calendario.js';

export async function manejarTurnosManuales() {

    resetearEstadoManual();

    estadoManual.frecuenciaSemanal = estado.frecuenciaSemanal;

    const { turnos, fechasPorSemana } = await cargarTurnosDisponibles();

    estadoManual.turnos = turnos;
    estadoManual.fechasPorSemana = fechasPorSemana;

    inicioContainer.classList.remove('hidden');
    configurarSemanaInicioManual();
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
    const fechaFinStr = formatearFechaLocalISO(fechaFin);

    const turnos = await apiFetch(
        `/actividades/${estado.idActividad}/turnos-disponibles?id_paciente=${estado.idPaciente}&fecha_comienzo=${fechaComienzoStr}&fecha_fin=${fechaFinStr}`
    );

    const fechasTurnos = [...new Set(turnos.map(turno => turno.split(' ')[0]))];

    return {
        turnos,
        fechasPorSemana: agruparFechasPorSemana(fechasTurnos)
    };
}

function configurarSemanaInicioManual() {

    if (esSegundoPaso()) {
        const actualDisponible = tieneFechasInicioValidasDual('actual');
        const siguienteDisponible = tieneFechasInicioValidasDual('siguiente');

        radioActual.checked = false;
        radioActual.disabled = !actualDisponible;
        radioSiguiente.checked = false;
        radioSiguiente.disabled = !siguienteDisponible;

        if (!actualDisponible && siguienteDisponible) {
            radioSiguiente.checked = true;
            cargarPrimerTurnoSelect('siguiente');
        }

        return;
    }

    if (debeForzarSemanaSiguienteManual()) {
        radioActual.checked = false;
        radioActual.disabled = true;
        radioSiguiente.checked = true;
        radioSiguiente.disabled = false;
        cargarPrimerTurnoSelect('siguiente');
        return;
    }

    radioActual.checked = false;
    radioActual.disabled = false;
    radioSiguiente.checked = false;
    radioSiguiente.disabled = false;

    habilitarElemento(primerTurnoSelect, false);
    primerTurnoSelect.innerHTML = crearOpcionPorDefecto('Seleccione una fecha');
}

function tieneFechasInicioValidasDual(tipoInicio) {
    let fechas = estadoManual.fechasPorSemana[tipoInicio === 'actual' ? 0 : 1] ?? [];

    if (tipoInicio === 'actual') {
        fechas = fechas.filter(esFechaValidaSemanaActual);
    }

    return fechas.some(esFechaInicioSeleccionable);
}

function obtenerPrimerLunes() {

    const ahora = new Date();
    const diaActual = ahora.getDay();

    const fechaResultado = new Date(ahora);
    const diferenciaHastaLunes = diaActual === 0 ? -6 : 1 - diaActual;
    fechaResultado.setDate(ahora.getDate() + diferenciaHastaLunes);

    return formatearFechaLocalISO(fechaResultado);
}

function debeForzarSemanaSiguienteManual() {

    const ahora = new Date();
    const diaActual = ahora.getDay();
    const hora = ahora.getHours();

    if (diaActual === 0 || diaActual === 6) {
        return true;
    }

    if (diaActual === 5 && hora >= 19) {
        return true;
    }

    const fechasSemanaActual = (estadoManual.fechasPorSemana[0] ?? [])
        .filter(esFechaValidaSemanaActual);

    return fechasSemanaActual.length === 0;
}

function agruparFechasPorSemana(fechasTurnos) {

    const semanas = fechasTurnos.reduce((acc, fechaStr) => {

        const fecha = new Date(`${fechaStr}T00:00:00`);

        const lunes = new Date(fecha);
        const dia = lunes.getDay();
        const diferencia = dia === 0 ? -6 : 1 - dia;
        lunes.setDate(lunes.getDate() + diferencia);

        const semanaKey = formatearFechaLocalISO(lunes);

        if (!acc[semanaKey]) {
            acc[semanaKey] = [];
        }

        acc[semanaKey].push(fechaStr);
        return acc;
    }, {});

    return Object.values(semanas).map(fechas => fechas.sort());
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
    let fechasDisponibles = estadoManual.fechasPorSemana[estadoManual.semanaInicio] ?? [];

    if (tipoInicio === 'actual') {
        fechasDisponibles = fechasDisponibles.filter(esFechaValidaSemanaActual);
    }

    fechasDisponibles.forEach(fecha => {
        agregarOpcion(
            primerTurnoSelect,
            fecha,
            convertirFechaParaMostrar(fecha),
            !esFechaInicioSeleccionable(fecha)
        );
    });

    habilitarElemento(primerTurnoSelect, fechasDisponibles.some(esFechaInicioSeleccionable));
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

    const { fechasPorSemana } = estadoManual;
    const frecuencia = esSegundoPaso()
        ? estadoManual.frecuenciaSemanal
        : frecuenciaParaDeterminarPatron();
    const fechas = esSegundoPaso()
        ? estadoManual.fechasDeterminacion
        : fechasParaDeterminarPatron();

    if (patronDeterminado(frecuencia, fechas, fechasPorSemana)) {
        finalizarPatron();
        return;
    }

    renderizarSelectDeterminacion(estadoManual.fechasDeterminacion.length + 1);
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

    if (!fechasPosibles.some(fecha => !esFechaOcupadaPrimeraInscripcion(fecha))) {
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
                        <option value="${fecha}" ${esFechaOcupadaPrimeraInscripcion(fecha) ? 'disabled' : ''}>${convertirFechaParaMostrar(fecha)}</option>
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

    const { frecuenciaSemanal, fechasPorSemana } = estadoManual;
    const fechas = fechasParaDeterminarPatron();
    const frecuencia = frecuenciaParaDeterminarPatron();

    const patronCombinado = calcularPatron(frecuencia, fechas, fechasPorSemana, obtenerTotalTurnosParaPatron());
    const indiceSemanaBase = obtenerIndiceSemana(fechas[0], fechasPorSemana);
    const patron = esSegundoPaso()
        ? calcularPatronSegundaInscripcion(patronCombinado, indiceSemanaBase)
        : patronCombinado;

    if (!patronEsValido(patron) || !patronEsFactible(patron, indiceSemanaBase, fechasPorSemana)) {
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

function fechasParaDeterminarPatron() {
    if (!esSegundoPaso()) {
        return [...estadoManual.fechasDeterminacion].sort();
    }

    const ultimoIndiceDeterminado = Math.max(
        ...estadoManual.fechasDeterminacion.map(fecha => obtenerIndiceSemana(fecha, estadoManual.fechasPorSemana))
    );
    const fechasPrimera = fechasOcupadasPrimeraInscripcion()
        .filter(fecha => obtenerIndiceSemana(fecha, estadoManual.fechasPorSemana) <= ultimoIndiceDeterminado);

    return [...new Set([...fechasPrimera, ...estadoManual.fechasDeterminacion])].sort();
}

function calcularPatron(frecuencia, fechas, fechasPorSemana, totalSesiones = obtenerTotalTurnos()) {

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

function ajustarPatronATotal(patron, totalSesiones) {
    let suma = patron.reduce((acumulado, valor) => acumulado + valor, 0);

    while (suma > totalSesiones) {
        patron[patron.length - 1]--;
        suma--;
    }
}

function patronEsFactible(patron, indiceSemanaBase, fechasPorSemana) {
    return patron.every((cantidad, indice) => {
        const fechasSemana = (fechasPorSemana[indiceSemanaBase + indice] ?? [])
            .filter(fecha => !esFechaOcupadaPrimeraInscripcion(fecha));

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
        if (cantidad < 1) {
            return;
        }

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
                convertirFechaParaMostrar(fecha),
                esFechaOcupadaPrimeraInscripcion(fecha)
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
                esFechaOcupadaPrimeraInscripcion(option.value) ||
                elegidas.includes(option.value) &&
                option.value !== select.value;
        });
    });
}

// Dual =======================================

function esFechaOcupadaPrimeraInscripcion(fecha) {
    return esSegundoPaso() && fechasOcupadasPrimeraInscripcion().includes(fecha);
}

function esFechaInicioSeleccionable(fecha) {
    if (esFechaOcupadaPrimeraInscripcion(fecha)) {
        return false;
    }

    return !esSegundoPaso() || esFechaInicioDualManualValida(fecha);
}

function esFechaInicioDualManualValida(fecha) {
    const indiceBase = indiceSemanaBaseDual(fecha);
    const indiceCandidato = obtenerIndiceSemana(fecha, estadoManual.fechasPorSemana);

    if (indiceBase < 0 || indiceCandidato < 0) {
        return false;
    }

    return posiblesCantidadesPrimeraSemanaDual(indiceBase, indiceCandidato)
        .some(cantidadPrimeraSemana => {
            const patronCombinado = crearPatronDesdePrimeraSemana(
                frecuenciaParaDeterminarPatron(),
                obtenerTotalTurnosParaPatron(),
                cantidadPrimeraSemana
            );
            const patronSegunda = calcularPatronSegundaInscripcion(patronCombinado, indiceBase);

            return patronSegunda[indiceCandidato - indiceBase] > 0
                && patronEsValido(patronSegunda)
                && patronEsFactible(patronSegunda, indiceBase, estadoManual.fechasPorSemana);
        });
}

function indiceSemanaBaseDual(fechaCandidata) {
    const indices = [...fechasOcupadasPrimeraInscripcion(), fechaCandidata]
        .map(fecha => obtenerIndiceSemana(fecha, estadoManual.fechasPorSemana))
        .filter(indice => indice >= 0);

    return indices.length ? Math.min(...indices) : -1;
}

function posiblesCantidadesPrimeraSemanaDual(indiceBase, indiceCandidato) {

    const frecuenciaTotal = frecuenciaParaDeterminarPatron();

    const cantOcupadasPrimera = fechasOcupadasPrimeraInscripcion().filter(
        fecha => obtenerIndiceSemana(fecha, estadoManual.fechasPorSemana) === indiceBase
    ).length;

    const primeraSeleccionadaEnBase = indiceCandidato === indiceBase ? 1 : 0;
    const minimo = cantOcupadasPrimera + primeraSeleccionadaEnBase;
    const maximo = Math.min(frecuenciaTotal, cantOcupadasPrimera + estadoManual.frecuenciaSemanal);
    const cantidades = [];

    for (let cantidad = minimo; cantidad <= maximo; cantidad++) {
        cantidades.push(cantidad);
    }

    return cantidades;
}

function frecuenciaParaDeterminarPatron() {
    return esSegundoPaso()
        ? frecuenciaPrimeraInscripcion() + estadoManual.frecuenciaSemanal
        : estadoManual.frecuenciaSemanal;
}

function obtenerIndiceSemana(fecha, fechasPorSemana) {
    return fechasPorSemana.findIndex(semana => semana.includes(fecha));
}

function crearPatronDesdePrimeraSemana(frecuencia, totalSesiones, cantidadPrimeraSemana) {
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

function obtenerTotalTurnosParaPatron() {
    if (!esSegundoPaso()) {
        return obtenerTotalTurnos();
    }

    return frecuenciaParaDeterminarPatron() * 4;
}

function calcularPatronSegundaInscripcion(patronCombinado, indiceSemanaBase) {
    const ocupadas = fechasOcupadasPrimeraInscripcion();

    return patronCombinado.map((cantidad, indice) => {
        const indiceSemana = indiceSemanaBase + indice;
        const ocupadasSemana = ocupadas.filter(
            fecha => obtenerIndiceSemana(fecha, estadoManual.fechasPorSemana) === indiceSemana
        ).length;

        return Math.max(0, cantidad - ocupadasSemana);
    });
}

function patronEsValido(patron) {
    if (!esSegundoPaso()) {
        return true;
    }

    return patron.reduce((total, cantidad) => total + cantidad, 0) === obtenerTotalTurnos()
        && patron.every(cantidad => cantidad >= 0 && cantidad <= estadoManual.frecuenciaSemanal);
}

const estadoManual = {
    frecuenciaSemanal: null,
    turnos: [],
    fechasPorSemana: [],
    semanaInicio: null,
    fechasDeterminacion: [],
    patron: null
};
