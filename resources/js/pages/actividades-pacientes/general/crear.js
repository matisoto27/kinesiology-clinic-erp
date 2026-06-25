import { configurarBuscador, limpiarSugerencias } from '@compartido/buscador.js';
import { obtenerPrecio } from '../componentes/api-turnos.js';
import { estado, faltanDatosObligatorios, resetearEstado } from '../componentes/estado-formulario.js';
import { manejarTurnosAutogenerados } from '../componentes/logica-turnos-autogenerados.js';
import {
    manejarCambioSemanaManual,
    manejarPrimerTurnoManual,
    manejarTurnosManuales
} from '../componentes/logica-turnos-manuales.js';
import {
    manejarCambioDiaTurnos,
    manejarCambioSemanaTurnos,
    mostrarConfiguracionAutomatica
} from '../componentes/orquestacion-turnos-ui.js';
import {
    construirPayloadRegistro,
    recolectarPatronSemanal,
    recolectarTurnosManuales
} from '../componentes/payload-registro.js';
import { limpiarFrecuenciaPrecioTurnos, limpiarConfiguracionTurnos } from '../componentes/ui-turnos.js';
import {
    agregarOpcion,
    apiFetch,
    crearOpcionPorDefecto,
    habilitarElemento,
    mostrarAlerta,
    obtenerValor
} from '@compartido/general.js';
import {
    actividadSelect,
    cantidadSelect,
    formulario,
    precioInput,
    turnosCheckbox,
    checkboxes,
    radioButtons,
    primerTurnoSelect,
    turnosContainer
} from '../componentes/dom-turnos.js';

function crearLiPaciente(paciente, esUltimo) {

    const li = document.createElement('li');
    const idPaciente = paciente.id;
    const apellidoNombre = `${paciente.apellido} ${paciente.nombre}`;

    li.classList.add('p-2', 'cursor-pointer', 'text-left', 'bg-white', 'hover:bg-[#F5D500]', 'text-black');
    if (esUltimo) li.classList.add('rounded-b-md');
    li.textContent = apellidoNombre;
    li.dataset.idPaciente = idPaciente;

    return li;
}

async function manejarCambioPaciente(e) {

    const elementoClickeado = e.target.closest('li');
    if (!elementoClickeado) return;

    const idPaciente = obtenerValor(elementoClickeado.dataset.idPaciente);
    if (idPaciente === null) return;

    estado.idPaciente = idPaciente;
    estado.idActividad = null;
    estado.idActividadCombo = null;
    estado.frecuenciaSemanal = null;
    estado.cantidadSesiones = null;
    estado.esPlanDual = false;
    estado.planDualPendiente = null;

    idPacienteSeleccionado.value = idPaciente;
    pacienteInput.value = elementoClickeado.textContent;

    habilitarBuscador(false);
    limpiarSugerencias(sugerenciasPaciente);
    limpiarFrecuenciaPrecioTurnos();
    ocultarBanner();

    const datosDual = await obtenerDatosInscripcionDual(idPaciente);
    if (datosDual) {
        await configurarSegundoPasoDual(datosDual);
        return;
    }

    await cargarActividadesPaciente(idPaciente);
}

async function manejarCambioActividad() {

    const idActividadAnterior = estado.idActividad ?? '';

    estado.idActividadCombo = null;
    estado.frecuenciaSemanal = null;
    estado.cantidadSesiones = null;

    const idActividad = obtenerValor(actividadSelect);
    if (idActividad === null) {
        estado.idActividad = null;
        limpiarFrecuenciaPrecioTurnos();
        return;
    }

    estado.idActividad = idActividad;

    const datosDual = estado.planDualPendiente ?? null;
    const frecuenciasPermitidas = datosDual?.segunda_inscripcion.frecuencias_permitidas ?? [];

    const frecuenciasCargadas = await cargarFrecuenciasDesdeCombos(idActividad, frecuenciasPermitidas);
    if (!frecuenciasCargadas) {
        // Error al obtener los combos o ninguna frecuencia pasa el filtro
        estado.idActividad = idActividadAnterior || null;
        actividadSelect.value = idActividadAnterior;
    }

    limpiarPrecio();
    limpiarConfiguracionTurnos();
}

async function manejarCambioFrecuencia() {

    estado.idActividadCombo = null;
    estado.frecuenciaSemanal = obtenerValor(cantidadSelect);
    estado.cantidadSesiones = null;

    if (faltanDatosObligatorios()) {
        limpiarFrecuenciaPrecioTurnos();
        return;
    }

    estado.cantidadSesiones = estado.frecuenciaSemanal * 4;

    if (estado.planDualPendiente) {
        await actualizarPrecioDual();
    } else if (estado.esPlanDual) {
        precioInput.value = "$---";
    } else {
        const opcionSeleccionada = cantidadSelect.options[cantidadSelect.selectedIndex];
        estado.idActividadCombo = obtenerValor(opcionSeleccionada.dataset.id);

        if (estado.idActividadCombo) {
            const precioResponse = await obtenerPrecio(estado.idActividadCombo);
            precioInput.value = precioResponse;
        }
    }

    limpiarConfiguracionTurnos();

    if (estado.turnosAutogenerados) {
        mostrarConfiguracionAutomatica();
        await manejarCambioDiaTurnos();
    } else {
        await manejarTurnosManuales();
    }
}

async function manejarCambioPlanDual() {

    if (estado.planDualPendiente) {
        planDualCheckbox.checked = true;
        return;
    }

    estado.esPlanDual = planDualCheckbox.checked;

    if (estado.idActividad) {
        await manejarCambioActividad();
    }
}

async function manejarCambioTurnosAutogenerados() {

    estado.turnosAutogenerados = turnosCheckbox.checked;

    if (faltanDatosObligatorios()) {
        return;
    }

    limpiarConfiguracionTurnos();

    if (estado.turnosAutogenerados) {
        mostrarConfiguracionAutomatica();
        await manejarCambioDiaTurnos();
    } else {
        await manejarTurnosManuales();
    }
}

function finalizarRegistroExitoso(idActPac, esPlanDualCompleto = false) {
    if (esPlanDualCompleto) {
        return mostrarAlerta(
            'success',
            '¡Plan dual registrado!',
            'El plan dual Gimnasio/Pilates quedó registrado correctamente.'
        ).then(() => window.location.replace('/'));
    }

    return mostrarAlerta(
        'success',
        '¡Turnos registrados!',
        'Los turnos del paciente han sido registrados correctamente.'
    ).then(() => Swal.fire({
        title: '¿A dónde quieres ir ahora?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Gestionar pago',
        cancelButtonText: 'Volver al inicio'
    }).then(eleccion => {
        if (eleccion.isConfirmed) {
            const urlPago = formulario.dataset.urlPago;
            window.location.href = urlPago.replace('__ID__', idActPac);
        } else {
            window.location.replace('/');
        }
    }));
}

const {
    elementos: {
        idSeleccionado: idPacienteSeleccionado,
        quitarButton: quitarPacienteButton,
        input: pacienteInput,
        sugerencias: sugerenciasPaciente
    },
    habilitarBuscador
} = configurarBuscador('paciente', '/buscar-pacientes', crearLiPaciente);
const planDualCheckbox = document.getElementById('plan-dual-checkbox');
const planDualBanner = document.getElementById('plan-dual-banner');

actividadSelect.addEventListener('change', manejarCambioActividad);
cantidadSelect.addEventListener('change', manejarCambioFrecuencia);
turnosCheckbox.addEventListener('change', manejarCambioTurnosAutogenerados);
planDualCheckbox.addEventListener('change', manejarCambioPlanDual);

checkboxes.forEach(cb => {
    cb.addEventListener('change', manejarCambioDiaTurnos);
});

radioButtons.forEach(radio => {
    radio.addEventListener('change', async () => {
        if (estado.turnosAutogenerados) {
            await manejarCambioSemanaTurnos();
        } else {
            manejarCambioSemanaManual(radio.value);
        }
    });
});

primerTurnoSelect.addEventListener('change', async () => {
    if (estado.turnosAutogenerados) {
        if (faltanDatosObligatorios()) {
            limpiarFrecuenciaPrecioTurnos();
            return;
        }

        await manejarTurnosAutogenerados();
    } else {
        manejarPrimerTurnoManual();
    }
});

formulario.addEventListener('submit', async (e) => {
    e.preventDefault();

    try {
        if (faltanDatosObligatorios()) {
            throw new Error('Por favor, seleccione un paciente y una actividad.');
        }

        const idActividad = estado.idActividad;
        const idPaciente = estado.idPaciente;
        const turnosAutogenerados = estado.turnosAutogenerados;
        const frecuenciaSemanal = estado.frecuenciaSemanal;
        const idActividadCombo = estado.idActividadCombo;
        const cantSesiones = estado.cantidadSesiones;
        const esPlanDual = estado.esPlanDual || Boolean(estado.planDualPendiente);

        if (!esPlanDual && idActividadCombo === null) {
            throw new Error('Por favor, seleccione una frecuencia semanal.');
        }

        const turnos = turnosAutogenerados
            ? recolectarPatronSemanal(turnosContainer, frecuenciaSemanal)
            : recolectarTurnosManuales(turnosContainer, cantSesiones);

        const payload = construirPayloadRegistro({
            idActividad,
            idPaciente,
            idActividadCombo,
            frecuenciaSemanal,
            autogenerados: turnosAutogenerados,
            turnos,
            fechaAncla: primerTurnoSelect.value,
            esPlanDual: esPlanDual
        });

        const url = formulario.dataset.url;
        const options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(payload)
        };

        const respuesta = await apiFetch(url, options);
        const pendienteDual =  respuesta?.plan_dual_pendiente ?? null;

        if (pendienteDual) {
            await mostrarAlerta(
                'success',
                'Primera actividad registrada',
                'Continúe con la segunda actividad del plan dual.'
            );
            await configurarSegundoPasoDual(pendienteDual);
            return;
        }

        const idActPac = respuesta.id;
        const esPlanDualCompleto = Boolean(respuesta.plan_dual_completado || estado.planDualPendiente);

        await finalizarRegistroExitoso(idActPac, esPlanDualCompleto);

    } catch (error) {
        console.error(error);
        await mostrarAlerta('error', 'Error al registrar los turnos', error.message);
    }
});

quitarPacienteButton.addEventListener('click', function() {

    resetearEstado();
    idPacienteSeleccionado.value = '';
    habilitarBuscador(true);

    habilitarElemento(actividadSelect, false);
    actividadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una actividad');

    limpiarFrecuenciaPrecioTurnos();
    ocultarBanner();
    planDualCheckbox.disabled = false;
});

sugerenciasPaciente.addEventListener('click', manejarCambioPaciente);

async function cargarActividadesPaciente(idPaciente) {
    habilitarElemento(actividadSelect, false);
    actividadSelect.innerHTML = crearOpcionPorDefecto('Cargando actividades...');

    try {
        const actividades = await apiFetch(`/pacientes/${idPaciente}/actividades-generales-sin-suscripcion`);

        if (actividades.length === 0) {
            actividadSelect.innerHTML = crearOpcionPorDefecto('Suscripto a todas');
            return;
        }

        actividadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una actividad');
        actividades.forEach(actividad => {
            agregarOpcion(actividadSelect, actividad.id, actividad.nombre);
        });
        habilitarElemento(actividadSelect, true);

    } catch (error) {
        console.error(error);
        await mostrarAlerta('error', 'Error al cargar las actividades', error.message);
    }
}

// FUNCIONES INSCRIPCIÓN DUAL ========================================================================================================

function mostrarBanner(texto) {

    if (!planDualBanner) return;

    planDualBanner.classList.remove('hidden');
    planDualBanner.textContent = texto;
}

function ocultarBanner() {

    if (!planDualBanner) return;

    planDualBanner.classList.add('hidden');
    planDualBanner.textContent = '';
}

function limpiarPrecio() {
    if (!precioInput) return;
    precioInput.value = '$0,00';
}

async function obtenerDatosInscripcionDual(idPaciente) {
    try {
        const datos = await apiFetch(`/pacientes/${idPaciente}/inscripcion-dual/pendiente`);

        return datos.plan_dual_pendiente ?? null;

    } catch (error) {
        console.error(error);
        await mostrarAlerta('error', 'Error al cargar datos de inscripción dual', error.message);
        return null;
    }
}

async function actualizarPrecioDual() {
    try {
        const preview = await apiFetch(
            `/pacientes/${estado.idPaciente}/inscripcion-dual/preview?frecuencia_segunda=${estado.frecuenciaSemanal}`
        );
        precioInput.value = `$${Number(preview.precio_plan).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    } catch (error) {
        console.error(error);
        await mostrarAlerta('error', 'Error al cargar el precio', error.message);
        precioInput.value = 'Error';
    }
}

async function configurarSegundoPasoDual(datosDual) {

    const pendiente = datosDual;
    estado.esPlanDual = true;
    estado.planDualPendiente = pendiente;
    if (planDualCheckbox) {
        planDualCheckbox.checked = true;
        planDualCheckbox.disabled = true;
    }
    mostrarBanner('Primera actividad registrada. Complete el plan dual con la segunda actividad.');

    const segundaInscripcion = pendiente.segunda_inscripcion;
    const idActividad = segundaInscripcion.actividad.id;
    estado.idActividad = idActividad;
    estado.idActividadCombo = null;
    estado.frecuenciaSemanal = null;
    estado.cantidadSesiones = null;

    actividadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una actividad');
    agregarOpcion(actividadSelect, idActividad, segundaInscripcion.actividad.nombre);
    actividadSelect.value = String(idActividad);
    habilitarElemento(actividadSelect, false);

    await cargarFrecuenciasDesdeCombos(idActividad, segundaInscripcion.frecuencias_permitidas ?? []);

    limpiarPrecio();
    limpiarConfiguracionTurnos();
}

async function cargarFrecuenciasDesdeCombos(idActividad, frecuenciasPermitidas = []) {

    habilitarElemento(cantidadSelect, false);
    cantidadSelect.innerHTML = crearOpcionPorDefecto('Cargando frecuencias ...');

    let combos = [];

    try {
        combos = await apiFetch(`/actividades/${idActividad}/combos?con_precio=true`);
    } catch (error) {
        console.error(error);
        await mostrarAlerta('error', 'Error al cargar los combos', error.message);
        return false;
    }

    const maxima = estado.esPlanDual && !estado.planDualPendiente ? 4 : null;

    const combosFiltrados = combos.filter(combo => {
        const frecuencia = combo.cantidad_sesiones / 4;

        if (maxima !== null && frecuencia > maxima) {
            return false;
        }

        if (frecuenciasPermitidas.length > 0 && !frecuenciasPermitidas.includes(frecuencia)) {
            return false;
        }

        return true;
    });

    if (combosFiltrados.length === 0) {
        await mostrarAlerta(
            'error',
            'No hay combos disponibles',
            'No existen combos con un precio registrado para la actividad seleccionada.'
        );
        return false;
    }

    cantidadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    combosFiltrados.forEach(combo => {
        const sesionesPorSemana = combo.cantidad_sesiones / 4;
        const contenidoTextual = `${sesionesPorSemana} ${sesionesPorSemana === 1 ? 'vez' : 'veces'} por semana`;
        const atributos = { id: combo.id_actividad_combo };
        agregarOpcion(cantidadSelect, sesionesPorSemana, contenidoTextual, false, false, atributos);
    });
    habilitarElemento(cantidadSelect, true);

    return true;
}
