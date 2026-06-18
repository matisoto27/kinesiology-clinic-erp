import { configurarBuscador, limpiarSugerencias } from '@compartido/buscador.js';
import {
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
    construirPayloadKineSinOrden,
    recolectarPatronSemanal,
    recolectarTurnosManuales
} from '../../componentes/payload-registro.js';
import { limpiarConfiguracionTurnos } from '../../componentes/ui-turnos.js';
import {
    actividadSelect,
    cantidadSelect,
    checkboxes,
    formulario,
    frecuenciaSelect,
    precioInput,
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
        estado.frecuenciaSemanal
    ].some(valor => valor === null || valor === undefined);
}

function limpiarSeleccionKineSinOrden() {
    combosActividad = null;
    cantidadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una cantidad');
    habilitarElemento(cantidadSelect, false);
    frecuenciaSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    habilitarElemento(frecuenciaSelect, false);
    precioInput.value = '$0,00';
    limpiarConfiguracionTurnos();
}

async function actualizarConfiguracionTurnos() {
    if (faltanDatosFormulario()) {
        limpiarConfiguracionTurnos();
        return;
    }

    limpiarConfiguracionTurnos();

    if (estado.turnosAutogenerados) {
        mostrarConfiguracionAutomatica();
    } else {
        await manejarTurnosManuales();
    }
}

function manejarCambioTurnosAutogenerados() {
    estado.turnosAutogenerados = turnosCheckbox.checked;

    if (faltanDatosFormulario()) {
        return;
    }

    actualizarConfiguracionTurnos();
}

let combosActividad = null;

const {
    elementos: {
        idSeleccionado: idPacienteSeleccionado,
        quitarButton: quitarPacienteButton,
        input: pacienteInput,
        sugerencias: sugerenciasPaciente
    },
    habilitarBuscador
} = configurarBuscador('paciente', '/buscar-pacientes', crearLiPaciente);

async function manejarCambioActividad() {

    const idActividad = obtenerValor(actividadSelect);
    if (idActividad === null) {
        estado.idActividad = null;
        estado.frecuenciaSemanal = null;
        estado.cantidadSesiones = null;
        limpiarSeleccionKineSinOrden();
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

    const cantidadCombos = combos.length;

    if (cantidadCombos === 0) {
        actividadSelect.value = estado.idActividad ?? '';
        await mostrarAlerta('error', 'Combos no disponibles', 'La actividad seleccionada no tiene combos con precios registrados.');
        return;
    }

    if (idActividad === 3 && cantidadCombos !== 4) {
        actividadSelect.value = estado.idActividad ?? '';
        await mostrarAlerta('error', 'Combos sin precios definidos', 'Por favor, registre los precios de los 4 combos de Kinesiología Convencional.');
        return;
    }

    const precioSesionIndExiste = combos.some(c => c.cantidad_sesiones === 1);
    if (!precioSesionIndExiste) {
        actividadSelect.value = estado.idActividad ?? '';
        await mostrarAlerta('error', 'Precio no definido', 'La actividad seleccionada no tiene un precio definido para su sesión individual.');
        return;
    }

    estado.idActividad = idActividad;
    estado.frecuenciaSemanal = null;
    estado.cantidadSesiones = null;

    combosActividad = Object.fromEntries(
        combos.map(combo => [combo.cantidad_sesiones, combo.precio_vigente])
    );

    cantidadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una cantidad');
    if (idActividad === 3) {
        agregarOpcion(cantidadSelect, 1, '1');
        agregarOpcion(cantidadSelect, 3, '3');
        agregarOpcion(cantidadSelect, 5, '5');
        agregarOpcion(cantidadSelect, 10, '10');
    } else {
        for (let i = 1; i <= 10; i++) {
            agregarOpcion(cantidadSelect, i, `${i}`);
        }
    }
    habilitarElemento(cantidadSelect, true);

    frecuenciaSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    habilitarElemento(frecuenciaSelect, false);

    precioInput.value = '$0,00';
    limpiarConfiguracionTurnos();
}

function manejarCambiosCantidad() {

    const frecuenciaSeleccionada = obtenerValor(frecuenciaSelect);

    frecuenciaSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    habilitarElemento(frecuenciaSelect, false);

    precioInput.value = '$0,00';
    limpiarConfiguracionTurnos();

    const idActividad = obtenerValor(actividadSelect);
    const cantidadIngresada = obtenerValor(cantidadSelect);
    if (idActividad === null || cantidadIngresada === null || cantidadIngresada > 10) {
        return;
    }

    estado.cantidadSesiones = cantidadIngresada;
    estado.frecuenciaSemanal = null;

    if (idActividad !== 3 || cantidadIngresada !== 10) {
        agregarOpcion(frecuenciaSelect, 1, '1 vez por semana');
    }
    for (let i = 2; i <= 5 && i <= cantidadIngresada; i++) {
        agregarOpcion(frecuenciaSelect, i, `${i} veces por semana`);
    }
    habilitarElemento(frecuenciaSelect, true);

    if (frecuenciaSeleccionada !== null && idActividad !== 3) {
        frecuenciaSelect.value = frecuenciaSeleccionada <= cantidadIngresada
            ? frecuenciaSeleccionada
            : Math.min(cantidadIngresada, 5);
    }

    if (!combosActividad[1]) {
        precioInput.value = 'Error';
        return;
    }

    const cantidadesDisponibles = Object.keys(combosActividad).map(Number);
    let precioCombo;

    if (cantidadesDisponibles.length === 1) {
        precioCombo = combosActividad[1] * cantidadIngresada;
    } else {
        precioCombo = combosActividad[cantidadIngresada] || (combosActividad[1] * cantidadIngresada);
    }

    precioInput.value = `$${precioCombo}`;

    if (frecuenciaSelect.value) {
        estado.frecuenciaSemanal = obtenerValor(frecuenciaSelect);
        actualizarConfiguracionTurnos();
    }
}

actividadSelect.addEventListener('change', manejarCambioActividad);
cantidadSelect.addEventListener('change', manejarCambiosCantidad);
turnosCheckbox.addEventListener('change', manejarCambioTurnosAutogenerados);

frecuenciaSelect.addEventListener('change', function() {
    estado.frecuenciaSemanal = obtenerValor(this);
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
        if (faltanDatosFormulario()) {
            throw new Error('Por favor, ingrese todos los datos requeridos en el formulario.');
        }

        const turnosAutogenerados = estado.turnosAutogenerados;
        const frecuenciaSemanal = estado.frecuenciaSemanal;
        const cantidadSesiones = estado.cantidadSesiones;

        const turnos = turnosAutogenerados
            ? recolectarPatronSemanal(turnosContainer, frecuenciaSemanal)
            : recolectarTurnosManuales(turnosContainer, cantidadSesiones);

        const payload = construirPayloadKineSinOrden({
            idActividad: estado.idActividad,
            idPaciente: estado.idPaciente,
            cantidadSesiones,
            frecuenciaSemanal,
            autogenerados: turnosAutogenerados,
            turnos,
            fechaAncla: primerTurnoSelect.value
        });

        const respuesta = await apiFetch(formulario.dataset.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(payload)
        });

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
    actividadSelect.value = '';
    limpiarSeleccionKineSinOrden();
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
