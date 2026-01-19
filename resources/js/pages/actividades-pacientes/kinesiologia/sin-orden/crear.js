import {
    habilitarNombre,
    inicializarSugerenciasListeners,
    limpiarSugerencias
} from '@compartido/buscador-pacientes.js';

import {
    agregarOpcion,
    apiFetch,
    crearOpcionPorDefecto,
    convertirFechaParaMostrar,
    DIAS_SEMANA,
    habilitarElemento,
    mostrarAlerta,
    transformarFecha
} from '@compartido/general.js';

import {
    obtenerElementosBuscador,
    contenedorTurnos,
    precioInput
} from '@compartido/referencias-dom.js';

import {
    obtenerDesdeActual,
    actualizarDesdeActual,
    obtenerPrimeraFechaFueSeleccionada,
    actualizarPrimeraFechaFueSeleccionada,
    obtenerTotalAPagar,
    actualizarTotalAPagar,
    obtenerUltimaActividadValida,
    actualizarUltimaActividadValida,
    obtenerUltimaFrecuenciaValida,
    actualizarUltimaFrecuenciaValida
} from '../../componentes/gestor-estado.js';

import {
    actualizarDiasDeshabilitados,
    cargarHorarios,
    consolidarTurnosPorDia,
    deshabilitarHoraSeleccionada,
    determinarTurnosPorSemana,
    mostrarErrorTurnosInsuficientes,
    limpiarTurnos,
    obtenerTurnosSemanasCriticas,
    obtenerTurnosSemana,
    reiniciarPrecio,
    renderizarTurnosFijos,
    semanaCubreFrecuencia
} from '../../componentes/logica-turnos.js';

function crearLiPaciente(paciente, esUltimo) {
    const li = document.createElement('li');
    const idPaciente = paciente.id;
    const apellidoNombre = `${paciente.apellido} ${paciente.nombre}`;

    li.classList.add('p-2', 'cursor-pointer', 'text-left', 'bg-white', 'hover:bg-[#F5D500]', 'text-black');
    if (esUltimo) li.classList.add('rounded-b-md'); // Último paciente se redondean los bordes inferiores
    li.textContent = apellidoNombre;
    li.dataset.idPaciente = idPaciente;

    return li;
}

function manejarSeleccion(liSeleccionado) {
    const idPaciente = parseInt(liSeleccionado.dataset.idPaciente);
    if (!idPaciente) return;

    idPacienteInput.value = idPaciente;
    nombreInput.value = liSeleccionado.textContent;
    habilitarNombre(false);
    limpiarSugerencias();
}

function obtenerDatosFormulario() {
    const datos = {
        idPaciente: parseInt(idPacienteInput.value),
        idActividad: parseInt(actividadSelect.value),
        cantidadSesiones: cantidadInput.valueAsNumber,
        frecuenciaSemanal: parseInt(frecuenciaSelect.value)
    };

    const esValido = !isNaN(datos.idPaciente) &&
                     !isNaN(datos.idActividad) &&
                     validarCantidad(datos.cantidadSesiones) &&
                     !isNaN(datos.frecuenciaSemanal);

    return esValido ? datos : null;
}

function validarCantidad(valueAsNumber) {
    return !isNaN(valueAsNumber) && valueAsNumber >= 1 && valueAsNumber <= 20;
}

async function intentarActualizarPagina() {
    const datos = obtenerDatosFormulario();
    if (!datos) return;

    if (controladorAbortar) controladorAbortar.abort();
    controladorAbortar = new AbortController();

    try {
        await actualizarPagina(datos, controladorAbortar.signal);
    } catch (error) {
        if (error.name === 'AbortError') return;
        console.error('Error al intentar actualizar la página', error.message);
    }
}

async function actualizarPagina(datos, signal) {
    try {
        const cantidadSesiones = datos.cantidadSesiones;
        const frecuenciaSemanal = datos.frecuenciaSemanal;

        const cantidadSemanas = Math.ceil(cantidadSesiones / frecuenciaSemanal);
        const tieneMasDeUnaSemana = cantidadSemanas > 1;

        const turnos = await apiFetch(`/actividades/${datos.idActividad}/turnos-disponibles?id_paciente=${datos.idPaciente}&cantidad_semanas=${cantidadSemanas}`, { signal });

        let turnosSemanasCriticas = [];

        if (tieneMasDeUnaSemana) {
            turnosSemanasCriticas = obtenerTurnosSemanasCriticas(turnos, cantidadSemanas);

            const insuficiente = turnosSemanasCriticas.some(semana => {
                return !semanaCubreFrecuencia(semana, frecuenciaSemanal);
            });

            if (insuficiente) {
                await mostrarErrorTurnosInsuficientes();
                frecuenciaSelect.value = obtenerUltimaFrecuenciaValida();
                return;
            }
        }

        const turnosSemanaActual = obtenerTurnosSemana(turnos, 0);
        const turnosUltimaSemana = obtenerTurnosSemana(turnos, cantidadSemanas);

        const semanaActualCubre = semanaCubreFrecuencia(turnosSemanaActual, frecuenciaSemanal);
        const ultimaSemanaCubre = semanaCubreFrecuencia(turnosUltimaSemana, frecuenciaSemanal);

        if (!semanaActualCubre && !ultimaSemanaCubre) {
            await mostrarErrorTurnosInsuficientes();
            frecuenciaSelect.value = obtenerUltimaFrecuenciaValida();
            return;
        }

        let turnosPorSemana;
        let turnoHTML = '';

        if (turnosCheckbox.checked) {
            const resultado = await determinarTurnosPorSemana(semanaActualCubre, ultimaSemanaCubre, turnosSemanaActual, turnosSemanasCriticas, turnosUltimaSemana);

            if (resultado.accion === 'dismissed') {
                frecuenciaSelect.value = obtenerUltimaFrecuenciaValida();
                return;
            }

            turnosPorSemana = resultado.turnosPorSemana;
            if (resultado.accion === 'confirmed') {
                actualizarDesdeActual(true);
            }

            const turnosPorDia = consolidarTurnosPorDia(turnosPorSemana);

            const diasConTurnos = Object.keys(turnosPorDia).sort((diaA, diaB) => {
                return DIAS_SEMANA.indexOf(diaA) - DIAS_SEMANA.indexOf(diaB);
            });

            renderizarTurnosFijos(frecuenciaSemanal, diasConTurnos);

            const diaSelects = contenedorTurnos.querySelectorAll('.dia-select');

            diaSelects.forEach(select => {
                select.addEventListener('change', function() {

                    actualizarDiasDeshabilitados(diaSelects);
                    cargarHorarios(this, turnosPorDia, cantidadSemanas);
                });
            });

        } else {

            const turnosPrimeraSemana =  tieneMasDeUnaSemana
                ? turnosSemanasCriticas[0]
                : turnosUltimaSemana;

            const fechasSemanaActual = Object.keys(turnosSemanaActual);
            const fechasSemanaUno = Object.keys(turnosPrimeraSemana);

            const opcionesPrimeraSemana = semanaActualCubre
                ? [...fechasSemanaActual, ...fechasSemanaUno]
                : fechasSemanaUno;

            let turnosGenerados = 0;

            for (let i = 1; i <= cantidadSemanas; i++) {
                turnoHTML += `<h3 class="mb-4 border-t font-medium text-xl text-[#F5D500]">Semana ${i}</h3>`;

                for (let j = 1; j <= frecuenciaSemanal; j++) {

                    if (turnosGenerados >= cantidadSesiones) break;

                    turnosGenerados++;

                    turnoHTML += `
                        <div class="mb-4 flex gap-5 turno" data-semana="${i}">

                            <label class="etiqueta-formulario">Turno ${j}</label>

                            <div class="columna-campo">
                                <label class="etiqueta-formulario">Fecha</label>
                                <select class="entrada fecha-select" disabled required>
                                    <option value="" disabled selected>Seleccione una fecha</option>
                                    ${opcionesPrimeraSemana.map(fecha => {
                                        return `<option value="${fecha}">${convertirFechaParaMostrar(fecha)}</option>`;
                                    }).join('')}
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
            }
            contenedorTurnos.innerHTML = turnoHTML;

            const fechaSelects = contenedorTurnos.querySelectorAll('.fecha-select');
            const primerFechaSelect = fechaSelects[0];
            let turnosPorFecha;

            primerFechaSelect.addEventListener('change', function () {

                if (obtenerPrimeraFechaFueSeleccionada()) return;

                const fechaSeleccionada = this.value;
                const comienzaSemanaActual = fechasSemanaActual.includes(fechaSeleccionada);

                turnosPorSemana = comienzaSemanaActual
                    ? [turnosSemanaActual, ...turnosSemanasCriticas]
                    : [...turnosSemanasCriticas, turnosUltimaSemana];

                turnosPorFecha = Object.assign({}, ...turnosPorSemana);

                fechaSelects.forEach(select => {

                    const indiceSemana = parseInt(select.closest('.turno').dataset.semana) - 1;
                    const fechasSemana = Object.keys(turnosPorSemana[indiceSemana]);

                    select.innerHTML = crearOpcionPorDefecto('Seleccione una fecha');

                    fechasSemana.forEach(fecha => {
                        const esFechaSeleccionadaDePrimeraSemana = indiceSemana === 0 && fecha === fechaSeleccionada;

                        const deshabilitada = esFechaSeleccionadaDePrimeraSemana;
                        const seleccionada = deshabilitada && select === this;

                        agregarOpcion(
                            select,
                            fecha,
                            convertirFechaParaMostrar(fecha),
                            deshabilitada,
                            seleccionada
                        );
                    });

                    habilitarElemento(select, true);
                });

                actualizarPrimeraFechaFueSeleccionada(true);
            });

            fechaSelects.forEach(select => {
                select.addEventListener('change', function () {

                    const fechasSeleccionadas = Array.from(fechaSelects)
                        .map(s => s.value)
                        .filter(v => v);

                    fechaSelects.forEach(s => {
                        Array.from(s.options).forEach(option => {

                            if (option.value === '') return;

                            option.disabled = fechasSeleccionadas.includes(option.value) && option.value !== s.value;
                        });
                    });

                    const fechaSeleccionada = this.value;
                    const horarios = turnosPorFecha[fechaSeleccionada]; 
                    const turno = this.closest('.turno');
                    const horaSelect = turno.querySelector('.hora-select');

                    horaSelect.innerHTML = crearOpcionPorDefecto('Seleccione un horario');

                    horarios.forEach(hora => {

                        const [hh, mm] = hora.split(':');
                        const horaConvertida = `${hh}:${mm}hs`;

                        agregarOpcion(horaSelect, hora, horaConvertida);
                    });

                    habilitarElemento(horaSelect, true);
                    horaSelect.addEventListener('change', deshabilitarHoraSeleccionada);
                });
            });

            habilitarElemento(primerFechaSelect, true);
        }

        actualizarUltimaFrecuenciaValida(frecuenciaSemanal);

    } catch (error) {
        if (error.name === 'AbortError') throw error;
        console.error('Error en el gestor de cambios de frecuencia semanal', error);
        await mostrarAlerta('error', 'Error al actualizar la pagina', error.message);
    }
}

function limpiarFrecuenciaPrecioTurnos() {
    frecuenciaSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    habilitarElemento(frecuenciaSelect, false);
    reiniciarPrecio();
    contenedorTurnos.innerHTML = '';
}

const { eliminarButton, nombreInput, sugerencias } = obtenerElementosBuscador();
const actividadSelect = document.getElementById('actividad-select');
const cantidadInput = document.getElementById('cantidad-input');
const formulario = document.getElementById('formulario');
const frecuenciaSelect = document.getElementById('frecuencia-select');
const idPacienteInput = document.getElementById('id-paciente-input');
const token = document.querySelector('meta[name="csrf-token"]').content;
const turnosCheckbox = document.getElementById('turnos-checkbox');
let combosActividad = null;
let controladorAbortar = null;

document.addEventListener('DOMContentLoaded', async function() {
    try {
        inicializarSugerenciasListeners(crearLiPaciente);

        const actividades = await apiFetch('/actividades?id_tipo_actividad=2');
        actividades.forEach(actividad => {
            agregarOpcion(actividadSelect, actividad.id, actividad.nombre);
        });

        cantidadInput.addEventListener('input', intentarActualizarPagina);
        [actividadSelect, frecuenciaSelect, turnosCheckbox].forEach(elemento => {
            elemento.addEventListener('change', intentarActualizarPagina);
        });

    } catch (error) {
        console.error(error);
        mostrarAlerta('error', 'Error al cargar la página', error.message);
    }
});

sugerencias.addEventListener('click', function(e) {
    const liSeleccionado = e.target.closest('li');
    if (!liSeleccionado) return;

    manejarSeleccion(liSeleccionado);
    intentarActualizarPagina();
});

eliminarButton.addEventListener('click', function() {
    idPacienteInput.value = '';
    habilitarNombre(true);

    limpiarTurnos();

    actualizarPrimeraFechaFueSeleccionada(false);
});

actividadSelect.addEventListener('change', async function() {
    try {
        const idActividad = parseInt(this.value);
        if (!idActividad) return;

        const combos = await apiFetch(`/actividades/${idActividad}/combos?con_precio=true`);

        if (combos.length === 0) {
            this.value = obtenerUltimaActividadValida();
            await mostrarAlerta('error', 'No hay combos disponibles', 'No existen combos con un precio registrado para la actividad seleccionada.');
            return;
        }

        actualizarUltimaActividadValida(idActividad);

        habilitarElemento(cantidadInput, true);
        reiniciarPrecio();
        limpiarTurnos();

        combosActividad = Object.fromEntries(
            combos.map(combo => [combo.cantidad_sesiones, combo.precio_vigente])
        );

    } catch (error) {
        this.value = obtenerUltimaActividadValida();
        console.error(error);
        await mostrarAlerta('error', 'Error al cargar combos', error.message);
    }
});

cantidadInput.addEventListener('input', async function() {
    try {
        const cantidadIngresada = cantidadInput.valueAsNumber;
        if (!validarCantidad(cantidadIngresada)) {
            limpiarFrecuenciaPrecioTurnos();
            return;
        }

        const frecuenciaSeleccionada = parseInt(frecuenciaSelect.value);

        frecuenciaSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
        agregarOpcion(frecuenciaSelect, 1, '1 vez por semana');
        for (let i = 2; i <= 5 && i <= cantidadIngresada; i++) {
            agregarOpcion(frecuenciaSelect, i, `${i} veces por semana`);
        }
        habilitarElemento(frecuenciaSelect, true);

        if (frecuenciaSeleccionada) {
            frecuenciaSelect.value = frecuenciaSeleccionada <= cantidadIngresada
                ? frecuenciaSeleccionada
                : Math.min(cantidadIngresada, 5);
        }

        if (!combosActividad[1]) {
            precioInput.value = 'ERROR AL CARGAR';
            return;
        }

        const precio = combosActividad[cantidadIngresada] ?? (combosActividad[1] * cantidadIngresada);
        actualizarTotalAPagar(precio);
        precioInput.value = `$${precio}`;

        await intentarActualizarPagina();

    } catch (error) {
        console.error(error);
        await mostrarAlerta('error', 'Error al ingresar cantidad de sesiones', error.message);
    }
});

formulario.addEventListener('submit', async (e) => {
    try {
        e.preventDefault();

        const datos = obtenerDatosFormulario();
        if (!datos) {
            throw new Error('Por favor, ingrese todos los datos requeridos en el formulario.');
        }

        const frecuenciaSemanal = datos.frecuenciaSemanal;
        const cantidadSesiones = datos.cantidadSesiones;

        const turnosAutogenerados = turnosCheckbox.checked;

        const cantidadTurnos = turnosAutogenerados
            ? frecuenciaSemanal
            : cantidadSesiones;

        const divsTurnos = contenedorTurnos.querySelectorAll('.turno');
        const cantidadTurnosReal = divsTurnos.length;

        if (cantidadTurnosReal < cantidadTurnos) {
            throw new Error('Detectamos una inconsistencia en la estructura de la página. Por favor, recargue la página para solucionarlo.');
        }

        const turnos = [];

        if (turnosAutogenerados) {

            for (const turno of divsTurnos) {

                const selects = turno.querySelectorAll('select');

                if (selects.length < 2) {
                    throw new Error('Detectamos una inconsistencia en la estructura de la página. Por favor, recargue la página para solucionarlo.');
                }

                const diaSemana = selects[0].value;
                const horaInicio = selects[1].value;

                if (!diaSemana || !horaInicio) {
                    throw new Error('Por favor, seleccione para cada turno un día de la semana y una hora de inicio.');
                }

                turnos.push({
                    dia_semana: diaSemana,
                    hora_inicio: horaInicio
                });
            }

        } else {

            for (const turno of divsTurnos) {

                const selects = turno.querySelectorAll('select');

                if (selects.length < 2) {
                    throw new Error('Detectamos una inconsistencia en la estructura de la página. Por favor, recargue la página para solucionarlo.');
                }

                const fechaStr = selects[0].value;
                const horaInicio = selects[1].value;

                if (!fechaStr || !horaInicio) {
                    throw new Error('Por favor, seleccione para cada turno una fecha y una hora de inicio.');
                }

                const [hora, minuto] = horaInicio.split(':').map(Number);
                const [anio, mes, dia] = fechaStr.split('-').map(Number);
                const fecha = new Date(anio, mes - 1, dia, hora, minuto, 0, 0);

                turnos.push(transformarFecha(fecha));
            }
        }

        const url = formulario.dataset.url;
        const options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({
                id_actividad: datos.idActividad,
                id_paciente: datos.idPaciente,
                cant_sesiones: cantidadSesiones,
                total_a_pagar: obtenerTotalAPagar(),
                autogenerados: turnosAutogenerados,
                desde_actual: obtenerDesdeActual(),
                turnos,
                frecuencia_semanal: frecuenciaSemanal
            })
        };

        const respuesta = await apiFetch(url, options);
        const idActPac = respuesta.id_act_pac;

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
            window.location.href = `/pagos/crear?id_act_pac=${idActPac}`;
        } else {
            window.location.replace('/');
        }

    } catch (error) {
        console.error(error);
        await mostrarAlerta('error', 'Error al registrar los turnos', error.message);
    }
});
