import { configurarBuscador, limpiarSugerencias } from '@compartido/buscador.js';
import {
    actualizarDiasDelMes,
    agregarOpcion,
    apiFetch,
    crearOpcionPorDefecto,
    habilitarElemento,
    mostrarAlerta,
    obtenerValor
} from '@compartido/general.js';
import { estado, resetearEstado } from '../../componentes/estado-formulario.js';
import { manejarTurnosAutogenerados } from '../../componentes/logica-turnos-autogenerados.js';
import {
    manejarCambioSemanaManual,
    manejarPrimerTurnoManual,
    manejarTurnosManuales
} from '../../componentes/logica-turnos-manuales.js';
import {
    manejarCambioDiaTurnos,
    manejarCambioSemanaTurnos,
    mostrarConfiguracionAutomatica
} from '../../componentes/orquestacion-turnos-ui.js';
import {
    construirPayloadKineConOrden,
    recolectarPatronSemanal,
    recolectarTurnosManuales
} from '../../componentes/payload-registro.js';
import { limpiarConfiguracionTurnos } from '../../componentes/ui-turnos.js';
import {
    actividadSelect,
    cantidadSelect,
    checkboxes,
    diaSelect,
    formulario,
    frecuenciaSelect,
    mesSelect,
    primerTurnoSelect,
    radioButtons,
    turnosCheckbox,
    turnosContainer
} from '../../componentes/dom-turnos.js';

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

function faltanDatosFormulario() {
    return [
        estado.idPaciente,
        estado.idActividad,
        estado.cantidadSesiones,
        estado.frecuenciaSemanal,
        obtenerValor(mesSelect),
        obtenerValor(diaSelect)
    ].some(valor => valor === null || valor === undefined);
}

async function actualizarConfiguracionTurnos() {

    limpiarConfiguracionTurnos();

    if (faltanDatosFormulario()) {
        return;
    }

    if (estado.turnosAutogenerados) {
        mostrarConfiguracionAutomatica();
    } else {
        await manejarTurnosManuales();
    }
}

function sincronizarEstadoDesdeFormulario() {
    estado.idActividad = obtenerValor(actividadSelect);
    estado.cantidadSesiones = obtenerValor(cantidadSelect);
    estado.frecuenciaSemanal = obtenerValor(frecuenciaSelect);
}

function manejarCambioTurnosAutogenerados() {
    estado.turnosAutogenerados = turnosCheckbox.checked;

    if (faltanDatosFormulario()) {
        return;
    }

    actualizarConfiguracionTurnos();
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

actividadSelect.addEventListener('change', () => {
    sincronizarEstadoDesdeFormulario();
    actualizarConfiguracionTurnos();
});

cantidadSelect.addEventListener('change', () => {
    sincronizarEstadoDesdeFormulario();
    actualizarConfiguracionTurnos();
});

frecuenciaSelect.addEventListener('change', () => {
    sincronizarEstadoDesdeFormulario();
    actualizarConfiguracionTurnos();
});

turnosCheckbox.addEventListener('change', manejarCambioTurnosAutogenerados);

mesSelect.addEventListener('change', function() {
    actualizarDiasDelMes(this, diaSelect);
    sincronizarEstadoDesdeFormulario();
    actualizarConfiguracionTurnos();
});

diaSelect.addEventListener('change', function() {
    habilitarElemento(frecuenciaSelect, true);
    sincronizarEstadoDesdeFormulario();
    actualizarConfiguracionTurnos();
});

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
        if (faltanDatosFormulario()) {
            limpiarConfiguracionTurnos();
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
        sincronizarEstadoDesdeFormulario();

        const mes = obtenerValor(mesSelect);
        const dia = obtenerValor(diaSelect);

        if (faltanDatosFormulario()) {
            throw new Error('Por favor, ingrese todos los datos requeridos en el formulario.');
        }

        const turnosAutogenerados = estado.turnosAutogenerados;
        const frecuenciaSemanal = estado.frecuenciaSemanal;
        const cantidadSesiones = estado.cantidadSesiones;

        const turnos = turnosAutogenerados
            ? recolectarPatronSemanal(turnosContainer, frecuenciaSemanal)
            : recolectarTurnosManuales(turnosContainer, cantidadSesiones);

        const payload = construirPayloadKineConOrden({
            idActividad: estado.idActividad,
            idPaciente: estado.idPaciente,
            mes,
            dia,
            cantidadSesiones,
            frecuenciaSemanal,
            autogenerados: turnosAutogenerados,
            turnos,
            fechaAncla: primerTurnoSelect.value
        });

        await apiFetch(formulario.dataset.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(payload)
        });

        await mostrarAlerta(
            'success',
            '¡Turnos registrados!',
            'Los turnos del paciente han sido registrados correctamente.'
        );

        window.location.replace('/');

    } catch (error) {
        console.error(error);
        await mostrarAlerta('error', 'Error al registrar los turnos', error.message);
    }
});

quitarPacienteButton.addEventListener('click', function() {
    resetearEstado();
    idPacienteSeleccionado.value = '';
    habilitarBuscador(true);
    limpiarConfiguracionTurnos();
});

sugerenciasPaciente.addEventListener('click', function(e) {
    const liSeleccionado = e.target.closest('li');
    if (!liSeleccionado) return;

    const idPaciente = obtenerValor(liSeleccionado.dataset.idPaciente);
    if (idPaciente === null) return;

    estado.idPaciente = idPaciente;
    idPacienteSeleccionado.value = idPaciente;
    pacienteInput.value = liSeleccionado.textContent;
    habilitarBuscador(false);
    limpiarSugerencias(sugerenciasPaciente);

    actualizarConfiguracionTurnos();
});

async function cargarActividades() {
    try {
        const actividades = await apiFetch('/actividades?id_tipo_actividad=2');
        actividades.forEach(actividad => {
            agregarOpcion(actividadSelect, actividad.id, actividad.nombre);
        });
    } catch (error) {
        console.error(error);
        mostrarAlerta('error', 'Error al cargar los tratamientos', error.message);
    }
}

cargarActividades();
