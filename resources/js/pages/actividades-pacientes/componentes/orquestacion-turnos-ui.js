import {
    agregarOpcion,
    crearOpcionPorDefecto,
    formatearFechaLocalISO,
    OFFSET_DIAS
} from '@compartido/general.js';
import {
    checkboxes,
    diasContainer,
    inicioContainer,
    primerTurnoSelect,
    radioButtons,
    turnosContainer
} from './dom-turnos.js';
import { estado } from './estado-formulario.js';
import { manejarTurnosAutogenerados, obtenerDiasSeleccionados } from './logica-turnos-autogenerados.js';
import { frecuenciaAlcanzada, obtenerSemanaSeleccionada, tieneSemanaSeleccionada } from './reglas-turnos.js';
import { actualizarDiasCheckBoxes, ocultarSemanasButtons } from './ui-turnos.js';

export function mostrarConfiguracionAutomatica() {

    if (estado.frecuenciaSemanal === 5) {
        actualizarDiasCheckBoxes(true, true);
        inicioContainer?.classList.remove('hidden');
    } else {
        actualizarDiasCheckBoxes(false, false);
    }

    diasContainer?.classList.remove('hidden');
}

export async function manejarCambioDiaTurnos() {

    if (estado.frecuenciaSemanal === null) {
        return;
    }

    actualizarLimiteDias();

    if (frecuenciaAlcanzada()) {

        await configurarSemanaInicio();
        inicioContainer?.classList.remove('hidden');

    } else {

        inicioContainer?.classList.add('hidden');
        radioButtons.forEach(radio => {
            radio.checked = false;
            radio.disabled = false;
        });
        if (primerTurnoSelect) {
            primerTurnoSelect.disabled = true;
            primerTurnoSelect.innerHTML = crearOpcionPorDefecto('Seleccione una fecha');
        }
        if (turnosContainer) {
            turnosContainer.innerHTML = '';
        }
    }
}

function actualizarLimiteDias() {

    const diasSeleccionados = Array.from(checkboxes).filter(c => c.checked).length;

    if (diasSeleccionados >= estado.frecuenciaSemanal) {
        checkboxes.forEach(c => {
            if (!c.checked) {
                c.disabled = true;
            }
        });
    } else {
        checkboxes.forEach(c => {
            c.disabled = false;
        });
    }
}

async function configurarSemanaInicio() {

    const diasSeleccionados = obtenerDiasSeleccionados();

    const radioActual = inicioContainer.querySelector('input[name="inicio"][value="actual"]');
    const radioSiguiente = inicioContainer.querySelector('input[name="inicio"][value="siguiente"]');

    if (debeForzarSemanaSiguiente(diasSeleccionados)) {

        radioActual.checked = false;
        radioActual.disabled = true;

        radioSiguiente.checked = true;
        radioSiguiente.disabled = false;

        await actualizarPrimerTurnoSelect();

    } else {

        radioActual.checked = false;
        radioActual.disabled = false;

        radioSiguiente.checked = false;
        radioSiguiente.disabled = false;
    }
}

function debeForzarSemanaSiguiente(diasSeleccionados) {

    const ahora = new Date();

    const diaActual = ahora.getDay();
    const hora = ahora.getHours();

    if (diaActual === 0 || diaActual === 6) {
        return true;
    }

    if (diaActual === 5 && hora >= 19) {
        return true;
    }

    return !diasSeleccionados.some(
        dia => OFFSET_DIAS[dia] >= diaActual
    );
}

async function actualizarPrimerTurnoSelect() {

    const semanaSeleccionada = obtenerSemanaSeleccionada();
    if (!semanaSeleccionada) {
        return;
    }

    const diasSeleccionados = obtenerDiasSeleccionados();
    const tipoSemana = semanaSeleccionada.value;

    const ahora = new Date();
    const diaHoy = ahora.getDay();

    primerTurnoSelect.innerHTML = crearOpcionPorDefecto('Seleccione una fecha');

    diasSeleccionados.forEach(dia => {

        const fecha = obtenerFechaDeSemana(dia, tipoSemana);
        const diaSemana = fecha.getDay();

        if (tipoSemana === 'actual' && (diaSemana < diaHoy || (diaSemana === diaHoy && ahora.getHours() >= 19))) {
            return;
        }

        agregarOpcion(
            primerTurnoSelect,
            formatearFechaLocalISO(fecha),
            `${dia} ${fecha.getDate()}/${fecha.getMonth() + 1}`
        );
    });

    if (primerTurnoSelect.options.length === 2) {

        primerTurnoSelect.selectedIndex = 1;
        primerTurnoSelect.disabled = true;
        await manejarTurnosAutogenerados();

    } else {
        primerTurnoSelect.disabled = false;
    }
}

function obtenerFechaDeSemana(diaNombre, tipoSemana) {

    const hoy = new Date();
    hoy.setHours(12, 0, 0, 0);

    const lunes = new Date(hoy);
    const diaActual = hoy.getDay();
    const diferenciaHastaLunes = diaActual === 0
        ? -6
        : 1 - diaActual;
    lunes.setDate(
        hoy.getDate() + diferenciaHastaLunes
    );

    if (tipoSemana === 'siguiente') {
        lunes.setDate(
            lunes.getDate() + 7
        );
    }

    const fecha = new Date(lunes);

    fecha.setDate(
        lunes.getDate() +
        (OFFSET_DIAS[diaNombre] - 1)
    );

    return fecha;
}

export async function manejarCambioSemanaTurnos() {

    if (!frecuenciaAlcanzada() || !tieneSemanaSeleccionada()) {
        ocultarSemanasButtons();
        return;
    }

    primerTurnoSelect.disabled = false;
    turnosContainer.innerHTML = '';

    await actualizarPrimerTurnoSelect();
}
