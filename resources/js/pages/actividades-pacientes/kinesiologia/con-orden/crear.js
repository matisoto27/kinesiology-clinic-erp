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
    habilitarSelect,
    mostrarAlerta,
    transformarFecha
} from '@compartido/general.js';

import {
    obtenerElementosBuscador,
    contenedorTurnos
} from '@compartido/referencias-dom.js';

import {
    obtenerDesdeActual,
    actualizarDesdeActual,
    obtenerPrimeraFechaFueSeleccionada,
    actualizarPrimeraFechaFueSeleccionada,
    obtenerUltimaFrecuenciaValida,
    actualizarUltimaFrecuenciaValida
} from '../../componentes/gestor-estado.js';

import {
    actualizarDiasDeshabilitados,
    cargarHorarios,
    consolidarTurnosPorDia,
    deshabilitarHoraSeleccionada,
    mostrarErrorTurnosInsuficientes,
    limpiarTurnos,
    obtenerTurnosSemanasCriticas,
    obtenerTurnosSemana,
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

function actualizarDias() {
    const mes = parseInt(mesSelect.value);
    const anio = new Date().getFullYear();

    const diasEnMes = new Date(anio, mes, 0).getDate();

    diaSelect.innerHTML = crearOpcionPorDefecto('Seleccione día');

    for (let i = 1; i <= diasEnMes; i++) {
        agregarOpcion(diaSelect, i, i);
    }

    habilitarSelect(diaSelect, true);
}

async function actualizarPagina() {
    try {
        if (!validarPrimeraParte()) return;

        const idActividad = parseInt(actividadSelect.value);
        const idPaciente = parseInt(idPacienteInput.value);
        const cantidadSesiones = parseInt(cantidadSelect.value);
        const frecuenciaSemanal = parseInt(frecuenciaSelect.value);

        const cantidadSemanas = Math.ceil(cantidadSesiones / frecuenciaSemanal);
        const tieneMasDeUnaSemana = cantidadSemanas > 1;

        const turnos = await apiFetch(`/actividades/${idActividad}/turnos-disponibles?id_paciente=${idPaciente}&cantidad_semanas=${cantidadSemanas}`);

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
        const semanaUltimaCubre = semanaCubreFrecuencia(turnosUltimaSemana, frecuenciaSemanal);

        if (!semanaActualCubre && !semanaUltimaCubre) {
            await mostrarErrorTurnosInsuficientes();
            frecuenciaSelect.value = obtenerUltimaFrecuenciaValida();
            return;
        }

        actualizarUltimaFrecuenciaValida(frecuenciaSemanal);

        let turnosPorSemana;
        let turnoHTML = '';

        if (turnosCheckbox.checked) {

            if (semanaActualCubre && semanaUltimaCubre) {

                const eleccion = await Swal.fire({
                    title: '¿Desea generar los turnos a partir de la semana actual o a partir de la semana que viene?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Semana actual',
                    cancelButtonText: 'Semana que viene'
                });

                if (eleccion.isDismissed) return;

                if (eleccion.isConfirmed) {

                    turnosPorSemana = [turnosSemanaActual, ...turnosSemanasCriticas];
                    actualizarDesdeActual(true);

                } else {

                    turnosPorSemana = [...turnosSemanasCriticas, turnosUltimaSemana];
                }

            } else if (semanaActualCubre) {

                turnosPorSemana = [turnosSemanaActual, ...turnosSemanasCriticas];

            } else {

                turnosPorSemana = [...turnosSemanasCriticas, turnosUltimaSemana];
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

                            <label class="font-medium text-lg text-white">Turno ${j}</label>

                            <div class="flex flex-col gap-1">
                                <label class="font-medium text-lg text-white">Fecha</label>
                                <select class="bg-[#6BA9A9] rounded-md text-lg p-3 cursor-not-allowed text-[#E0F0F0] fecha-select" required disabled>
                                    <option value="" disabled selected>Seleccione una fecha</option>
                                    ${opcionesPrimeraSemana.map(fecha => {
                                        return `<option value="${fecha}">${convertirFechaParaMostrar(fecha)}</option>`;
                                    }).join('')}
                                </select>
                            </div>

                            <div class="flex flex-col gap-1">
                                <label class="font-medium text-lg text-white">Hora de inicio</label>
                                <select class="bg-[#6BA9A9] rounded-md text-lg p-3 cursor-not-allowed text-[#E0F0F0] hora-select" required disabled>
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

                    habilitarSelect(select, true);
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

                    habilitarSelect(horaSelect, true);
                    horaSelect.addEventListener('change', deshabilitarHoraSeleccionada);
                });
            });

            habilitarSelect(primerFechaSelect, true);
        }

    } catch (error) {
        console.error('Error en el gestor de cambios de frecuencia semanal:', error);
        await mostrarAlerta('error', 'Error inesperado', error);
    }
}

function validarPrimeraParte() {
    let todosValidos = true;

    for (const elemento of elementosRequeridos) {
        if (elemento.value === '' || elemento.value === null || !elemento.checkValidity()) {
            todosValidos = false;
        }
    }

    if (idPacienteInput.value === '') {
        todosValidos = false;
    }

    return todosValidos;
}

const { eliminarButton, nombreInput, sugerencias } = obtenerElementosBuscador();
const actividadSelect = document.getElementById('actividad-select');
const cantidadSelect = document.getElementById('cantidad-select');
const diaSelect = document.getElementById('dia-select');
const formulario = document.getElementById('formulario');
const frecuenciaSelect = document.getElementById('frecuencia-select');
const idPacienteInput = document.getElementById('id-paciente-input');
const mesSelect = document.getElementById('mes-select');
const token = document.querySelector('meta[name="csrf-token"]').content;
const turnosCheckbox = document.getElementById('turnos-checkbox');

const elementosRequeridos = [actividadSelect, mesSelect, diaSelect, cantidadSelect, frecuenciaSelect, turnosCheckbox];

document.addEventListener('DOMContentLoaded', async function() {
    try {
        inicializarSugerenciasListeners(crearLiPaciente);

        const actividades = await apiFetch('/actividades?id_tipo_actividad=2');
        actividades.forEach(actividad => {
            agregarOpcion(actividadSelect, actividad.id, actividad.nombre);
        });

        elementosRequeridos.forEach(elemento => {
            elemento.addEventListener('change', actualizarPagina);
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
    actualizarPagina();
});

eliminarButton.addEventListener('click', function() {
    idPacienteInput.value = '';
    habilitarNombre(true);
    limpiarTurnos();
    actualizarPrimeraFechaFueSeleccionada(false);
});

mesSelect.addEventListener('change', actualizarDias);

diaSelect.addEventListener('change', function() {
    habilitarSelect(frecuenciaSelect, true);
});

formulario.addEventListener('submit', async (e) => {
    try {

        e.preventDefault();

        const idActividad = parseInt(actividadSelect.value);
        const idPaciente = parseInt(idPacienteInput.value);
        const mes = parseInt(mesSelect.value);
        const dia = parseInt(diaSelect.value);

        if (!idActividad || !idPaciente || !mes || !dia) {
            throw new Error('Por favor, ingrese todos los datos requeridos en el formulario.');
        }

        const turnosAutogenerados = turnosCheckbox.checked;

        const frecuenciaSemanal = parseInt(frecuenciaSelect.value);
        const sesionesCubiertas = parseInt(cantidadSelect.value);

        const cantidadTurnos = turnosAutogenerados
            ? frecuenciaSemanal
            : sesionesCubiertas;

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
                id_actividad: idActividad,
                id_paciente: idPaciente,
                mes,
                dia,
                sesiones_cubiertas: sesionesCubiertas,
                autogenerados: turnosAutogenerados,
                turnos,
                desde_actual: obtenerDesdeActual(),
                frecuencia_semanal: frecuenciaSemanal
            })
        };

        await apiFetch(url, options);

        await mostrarAlerta(
            'success', 
            '¡Turnos registrados!', 
            'Los turnos del paciente han sido registrados correctamente.'
        );

        window.location.replace('/');

    } catch (error) {
        console.error(error);
        mostrarAlerta('error', 'Error al registrar los turnos', error.message);
    }
});
