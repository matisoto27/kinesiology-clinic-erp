import {
    agregarOpcion,
    crearOpcionPorDefecto,
    esFechaValidaSemanaActual,
    fechaDeSemana,
    formatearFechaLocalISO,
    OFFSET_DIAS
} from '@compartido/general.js';
import {
    diasContainer,
    inicioContainer,
    radioActual,
    radioSiguiente,
    primerTurnoSelect,
    radioButtons,
    turnosContainer
} from './dom-turnos.js';
import { estado } from './estado-formulario.js';
import {
    esSegundoPaso,
    sincronizarCheckboxesDiasSemana,
    configurarRadiosSemanaInicioDual,
    obtenerFechasValidasPrimerTurnoSegundaPierna
} from './logica-dual-calendario.js';
import { manejarTurnosAutogenerados, obtenerDiasSeleccionados } from './logica-turnos-autogenerados.js';
import { frecuenciaAlcanzada, obtenerSemanaSeleccionada, tieneSemanaSeleccionada } from './reglas-turnos.js';
import { actualizarDiasCheckBoxes, ocultarSemanasButtons } from './ui-turnos.js';
import { convertirFechaParaMostrar } from '../../../compartido/general.js';

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

    sincronizarCheckboxesDiasSemana();

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

async function configurarSemanaInicio() {

    const diasSeleccionados = obtenerDiasSeleccionados();

    if (esSegundoPaso()) {
        await configurarRadiosSemanaInicioDual(diasSeleccionados, { radioActual, radioSiguiente });
        if (radioActual.checked || radioSiguiente.checked) {
            await actualizarPrimerTurnoSelect();
        }
        return;
    }

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

    primerTurnoSelect.innerHTML = crearOpcionPorDefecto('Seleccione una fecha');

    if (esSegundoPaso()) {
        const fechasValidas = obtenerFechasValidasPrimerTurnoSegundaPierna(diasSeleccionados, tipoSemana);

        fechasValidas.forEach(fechaIso => {
            agregarOpcion(
                primerTurnoSelect,
                fechaIso,
                convertirFechaParaMostrar(fechaIso)
            );
        });

        if (primerTurnoSelect.options.length === 2) {
            primerTurnoSelect.selectedIndex = 1;
            primerTurnoSelect.disabled = true;
            await manejarTurnosAutogenerados();
        } else {
            primerTurnoSelect.disabled = fechasValidas.length === 0;
        }

        return;
    }

    diasSeleccionados.forEach(dia => {
        const fecha = fechaDeSemana(dia, tipoSemana);
        const fechaIso = formatearFechaLocalISO(fecha);

        if (tipoSemana === 'actual' && !esFechaValidaSemanaActual(fechaIso)) {
            return;
        }

        agregarOpcion(
            primerTurnoSelect,
            fechaIso,
            convertirFechaParaMostrar(fechaIso)
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

export async function manejarCambioSemanaTurnos() {

    if (!frecuenciaAlcanzada() || !tieneSemanaSeleccionada()) {
        ocultarSemanasButtons();
        return;
    }

    primerTurnoSelect.disabled = false;
    turnosContainer.innerHTML = '';

    await actualizarPrimerTurnoSelect();
}
