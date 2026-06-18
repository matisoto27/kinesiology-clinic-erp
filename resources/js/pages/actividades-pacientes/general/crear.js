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

function manejarCambioPaciente(e) {

    const elementoClickeado = e.target.closest('li');
    if (!elementoClickeado) return;

    const idPaciente = obtenerValor(elementoClickeado.dataset.idPaciente);
    if (idPaciente === null) return;

    estado.idPaciente = idPaciente;
    estado.idActividad = null;
    estado.idActividadCombo = null;
    estado.frecuenciaSemanal = null;
    estado.cantidadSesiones = null;

    idPacienteSeleccionado.value = idPaciente;
    pacienteInput.value = elementoClickeado.textContent;

    habilitarBuscador(false);
    limpiarSugerencias(sugerenciasPaciente);
    limpiarFrecuenciaPrecioTurnos();

    cargarActividadesPaciente(idPaciente);
}

async function manejarCambioActividad() {

    const idActividad = obtenerValor(actividadSelect);
    if (idActividad === null) {
        estado.idActividad = null;
        estado.idActividadCombo = null;
        estado.frecuenciaSemanal = null;
        estado.cantidadSesiones = null;
        limpiarFrecuenciaPrecioTurnos();
        return;
    }

    let combos = [];

    try {
        combos = await apiFetch(`/actividades/${idActividad}/combos?con_precio=true`);
    } catch (error) {
        actividadSelect.value = estado.idActividad ?? '';
        console.error(error);
        await mostrarAlerta('error', 'Error al cargar los combos', error.message);
        return;
    }

    if (combos.length === 0) {
        actividadSelect.value = estado.idActividad ?? '';
        await mostrarAlerta(
            'error',
            'No hay combos disponibles',
            'No existen combos con un precio registrado para la actividad seleccionada.'
        );
        return;
    }

    estado.idActividad = idActividad;
    estado.idActividadCombo = null;
    estado.frecuenciaSemanal = null;
    estado.cantidadSesiones = null;

    cantidadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    combos.forEach(combo => {
        const sesionesPorSemana = combo.cantidad_sesiones / 4;
        const contenidoTextual = `${sesionesPorSemana} ${sesionesPorSemana === 1 ? 'vez' : 'veces'} por semana`;
        const atributos = { id: combo.id_actividad_combo };
        agregarOpcion(cantidadSelect, sesionesPorSemana, contenidoTextual, false, false, atributos);
    });
    habilitarElemento(cantidadSelect, true);

    precioInput.value = '$0,00';
    limpiarConfiguracionTurnos();
}

async function manejarCambioFrecuencia() {

    estado.frecuenciaSemanal = obtenerValor(cantidadSelect);

    if (faltanDatosObligatorios()) {
        limpiarFrecuenciaPrecioTurnos();
        return;
    }

    estado.cantidadSesiones = estado.frecuenciaSemanal * 4;

    const opcionSeleccionada = cantidadSelect.options[cantidadSelect.selectedIndex];
    estado.idActividadCombo = obtenerValor(opcionSeleccionada.dataset.id);

    if (estado.idActividadCombo) {
        const precioResponse = await obtenerPrecio(estado.idActividadCombo);
        precioInput.value = precioResponse;
    }

    limpiarConfiguracionTurnos();

    if (estado.turnosAutogenerados) {
        mostrarConfiguracionAutomatica();
    } else {
        await manejarTurnosManuales();
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
    } else {
        await manejarTurnosManuales();
    }
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

actividadSelect.addEventListener('change', manejarCambioActividad);
cantidadSelect.addEventListener('change', manejarCambioFrecuencia);
turnosCheckbox.addEventListener('change', manejarCambioTurnosAutogenerados);

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

        if (idActividadCombo === null) {
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
            fechaAncla: primerTurnoSelect.value
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
        const idActPac = respuesta.id;

        await mostrarAlerta(
            'success',
            '¡Turnos registrados!',
            'Los turnos del paciente han sido registrados correctamente.'
        );

        const eleccion = await Swal.fire({
            title: '¿A dónde quieres ir ahora?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Gestionar pago',
            cancelButtonText: 'Volver al inicio'
        });

        if (eleccion.isConfirmed) {
            const urlPago = formulario.dataset.urlPago;
            window.location.href = urlPago.replace('__ID__', idActPac);
        } else {
            window.location.replace('/');
        }

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
});

sugerenciasPaciente.addEventListener('click', manejarCambioPaciente);

async function cargarActividadesPaciente(idPaciente) {
    actividadSelect.innerHTML = crearOpcionPorDefecto('Cargando actividades...');
    habilitarElemento(actividadSelect, false);

    try {
        const actividades = await apiFetch(`/pacientes/${idPaciente}/actividades-generales-sin-suscripcion`);

        if (actividades.length === 0) {
            actividadSelect.innerHTML = crearOpcionPorDefecto('Paciente suscripto a todas');
            return;
        }

        actividadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una actividad');
        actividades.forEach(actividad => {
            agregarOpcion(actividadSelect, actividad.id, actividad.nombre);
        });
        habilitarElemento(actividadSelect, true);

    } catch (error) {
        console.error(error);
        actividadSelect.innerHTML = crearOpcionPorDefecto('Error al cargar');
        mostrarAlerta('error', 'Error al cargar las actividades', error.message);
    }
}
