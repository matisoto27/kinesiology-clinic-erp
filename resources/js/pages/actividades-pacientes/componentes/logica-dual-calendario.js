import {
    apiFetch,
    DIAS_SEMANA,
    esFechaValidaSemanaActual,
    fechaDeSemana,
    formatearFechaLocalISO
} from '@compartido/general.js';
import { checkboxes, inicioContainer, primerTurnoSelect } from './dom-turnos.js';
import { estado } from './estado-formulario.js';
import { agregarOpcion, convertirFechaParaMostrar } from '../../../compartido/general.js';
import { manejarTurnosAutogenerados } from './logica-turnos-autogenerados.js';

export function sincronizarCheckboxesDiasSemana() {

    if (estado.frecuenciaSemanal === null) {
        return false;
    }

    const ocupados = esSegundoPaso() ? diasSemanaPrimeraInscripcion() : [];
    const disponibles = Array.from(checkboxes).filter(cb => !ocupados.includes(cb.value));
    const frecuenciaTotal = frecuenciaPrimeraInscripcion() + estado.frecuenciaSemanal;
    const debeAutoseleccionar = esSegundoPaso()
        && frecuenciaTotal === 5
        && disponibles.length === estado.frecuenciaSemanal;

    if (debeAutoseleccionar) {
        disponibles.forEach(cb => { cb.checked = true; });
    }

    const cantidadSeleccionados = Array.from(checkboxes).filter(c => c.checked).length;

    checkboxes.forEach(cb => {
        if (ocupados.includes(cb.value)) {
            cb.checked = false;
            cb.disabled = true;
            return;
        }

        if (cantidadSeleccionados >= estado.frecuenciaSemanal) {
            cb.disabled = !cb.checked;
            return;
        }

        cb.disabled = false;
    });
}

export async function configurarRadiosSemanaInicioDual(diasSeleccionados, { radioActual, radioSiguiente }) {

    if (!inicioContainer || !radioActual || !radioSiguiente) {
        return;
    }

    const fechasActual = obtenerFechasValidasPrimerTurnoSegundaPierna(diasSeleccionados, 'actual');
    const fechasSiguiente = obtenerFechasValidasPrimerTurnoSegundaPierna(diasSeleccionados, 'siguiente');

    radioActual.disabled = fechasActual.length === 0;
    radioSiguiente.disabled = fechasSiguiente.length === 0;

    if (fechasActual.length > 0 && fechasSiguiente.length === 0) {
        radioActual.checked = true;
        radioSiguiente.checked = false;
        return;
    }

    if (fechasActual.length === 0 && fechasSiguiente.length > 0) {
        radioActual.checked = false;
        radioSiguiente.checked = true;
        return;
    }

    if (fechasActual.length === 0 && fechasSiguiente.length === 0) {

        radioActual.checked = false;
        radioActual.disabled = true;

        radioSiguiente.checked = false;
        radioSiguiente.disabled = true;

        const candidataISO = await buscarPrimeraFechaDisponibleFallback(diasSeleccionados);
        if (!candidataISO) {
            return;
        }

        if (primerTurnoSelect) {
            primerTurnoSelect.innerHTML = '';
            agregarOpcion(
                primerTurnoSelect,
                candidataISO,
                convertirFechaParaMostrar(candidataISO)
            );
        }

        await manejarTurnosAutogenerados();
    }
}

async function buscarPrimeraFechaDisponibleFallback(diasSeleccionados) {
    const fechaAncla = fechaAnclaPrimeraInscripcion();
    if (!fechaAncla || !estado.idActividad || !estado.idPaciente) {
        return null;
    }

    const fechaComienzo = sumarDias(fechaAncla, 1);
    const fechaFin = sumarDias(fechaAncla, 10);

    let turnos = [];

    try {
        turnos = await apiFetch(
            `/actividades/${estado.idActividad}/turnos-disponibles?id_paciente=${estado.idPaciente}&fecha_comienzo=${fechaComienzo}&fecha_fin=${fechaFin}`
        );
    } catch {
        return null;
    }

    const fechasDisponibles = [...new Set(
        turnos.map(turno => turno.split(' ')[0])
    )].sort();

    for (const candidataISO of fechasDisponibles) {
        const candidataDate = new Date(`${candidataISO}T00:00:00`);
        const dia = nombreDiaSemana(candidataDate);

        if (!dia || !diasSeleccionados.includes(dia)) {
            continue;
        }

        if (esFechaInicioSegundaValida(candidataISO, diasSeleccionados)) {
            return candidataISO;
        }
    }

    return null;
}

export function obtenerFechasValidasPrimerTurnoSegundaPierna(diasSeleccionados, tipoSemana) {

    if (diasSeleccionados.length === 0) {
        return [];
    }

    return fechasCandidatasSemana(diasSeleccionados, tipoSemana)
        .filter(fecha => esFechaInicioSegundaValida(fecha, diasSeleccionados));
}

export function esSegundoPaso() {
    return Boolean(estado.planDualPendiente);
}

function fechasCandidatasSemana(diasSeleccionados, tipoSemana) {
    return diasSeleccionados
        .map(dia => fechaDeSemana(dia, tipoSemana))
        .map(formatearFechaLocalISO)
        .filter(fecha => tipoSemana !== 'actual' || esFechaValidaSemanaActual(fecha))
        .sort();
}

export function fechasOcupadasPrimeraInscripcion() {
    const turnos = estado.planDualPendiente?.primera_inscripcion?.turnos ?? [];

    if (turnos.length > 0) {
        return turnos.map(turno => String(turno).slice(0, 10));
    }

    const fechaAncla = fechaAnclaPrimeraInscripcion();
    return fechaAncla ? [fechaAncla] : [];
}

export function frecuenciaPrimeraInscripcion() {
    return estado.planDualPendiente?.primera_inscripcion?.frecuencia ?? 0;
}

function esFechaInicioSegundaValida(fechaCandidata, diasSegunda) {
    const fechaAncla = fechaAnclaPrimeraInscripcion();
    if (!fechaAncla) return false;

    const diasPrimera = diasSemanaPrimeraInscripcion();
    const diasCombinados = [...new Set([...diasPrimera, ...diasSegunda])];

    const inicioSecuencia = fechaAncla < fechaCandidata ? fechaAncla : fechaCandidata;
    const finHorizonte = sumarDias(inicioSecuencia, 28);

    // Generar todas las fechas del horizonte como ISO strings
    const todasLasFechas = [];
    let cursor = inicioSecuencia;
    while (cursor <= finHorizonte) {
        todasLasFechas.push(cursor);
        cursor = sumarDias(cursor, 1);
    }

    // Filtrar solo las que pertenecen a algún día combinado
    const ocurrencias = todasLasFechas.filter(iso => {
        const d = new Date(iso + 'T00:00:00');
        const dia = nombreDiaSemana(d);
        return diasCombinados.includes(dia);
    });

    // Para cada ocurrencia, determinar si está cubierta
    const cubiertas = ocurrencias.map(iso => {
        const d = new Date(iso + 'T00:00:00');
        const dia = nombreDiaSemana(d);
        const porPrimera = diasPrimera.includes(dia) && iso >= fechaAncla;
        const porSegunda = diasSegunda.includes(dia) && iso >= fechaCandidata;
        return porPrimera || porSegunda;
    });

    // Detectar hueco: una ocurrencia no cubierta entre dos cubiertas
    const primerCubierta = cubiertas.indexOf(true);
    const ultimaCubierta = cubiertas.lastIndexOf(true);

    if (primerCubierta === -1) return false; // ninguna cubierta

    for (let i = primerCubierta; i <= ultimaCubierta; i++) {
        if (!cubiertas[i]) return false; // hueco entre cubiertas
    }

    return true;
}

function sumarDias(fecha, dias) {
    const d = new Date(fecha + 'T00:00:00');
    d.setDate(d.getDate() + dias);
    return formatearFechaLocalISO(d);
}

function fechasEntre(inicioIso, finIso) {
    const fechas = [];
    const actual = new Date(`${inicioIso}T12:00:00`);
    const fin = new Date(`${finIso}T12:00:00`);

    while (actual <= fin) {
        fechas.push(new Date(actual));
        actual.setDate(actual.getDate() + 1);
    }

    return fechas;
}

function fechaMinima(a, b) {
    return a < b ? a : b;
}

function fechaMaxima(a, b) {
    return a > b ? a : b;
}

function fechaAnclaPrimeraInscripcion() {
    const raw = estado.planDualPendiente?.primera_inscripcion?.fecha_ancla;
    if (!raw) return null;

    return String(raw).slice(0, 10);
}

function diasSemanaPrimeraInscripcion() {
    return estado.planDualPendiente?.primera_inscripcion?.dias_semana ?? [];
}

function nombreDiaSemana(fecha) {
    return DIAS_SEMANA[fecha.getDay() - 1] ?? null;
}
