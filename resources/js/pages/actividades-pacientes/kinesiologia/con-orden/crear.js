import {
    habilitarBuscador,
    inicializarElementosBuscador,
    inicializarSugerenciasListeners,
    limpiarSugerencias
} from '@compartido/buscador-pacientes.js';

import {
    actualizarDiasDelMes,
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
    determinarTurnosPorSemana,
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
        const ultimaSemanaCubre = semanaCubreFrecuencia(turnosUltimaSemana, frecuenciaSemanal);

        if (!semanaActualCubre && !ultimaSemanaCubre) {
            await mostrarErrorTurnosInsuficientes();
            frecuenciaSelect.value = obtenerUltimaFrecuenciaValida();
            return;
        }

        actualizarUltimaFrecuenciaValida(frecuenciaSemanal);

        let turnosPorSemana;
        let turnoHTML = '';

        if (turnosCheckbox.checked) {
            const resultado = await determinarTurnosPorSemana(semanaActualCubre, ultimaSemanaCubre, turnosSemanaActual, turnosSemanasCriticas, turnosUltimaSemana);

            if (resultado.accion === 'dismissed') return;

            turnosPorSemana = resultado.turnosPorSemana;
            if (resultado.accion === 'confirmed') {
                actualizarDesdeActual(true);
            }

            const turnosPorDia = consolidarTurnosPorDia(turnosPorSemana);

            const diasConTurnos = Object.keys(turnosPorDia).sort((diaA, diaB) => {
                return DIAS_SEMANA.indexOf(diaA) - DIAS_SEMANA.indexOf(diaB);
            });

            renderizarTurnosFijos(frecuenciaSemanal, diasConTurnos, contenedorTurnos);

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

const actividadSelect = document.getElementById('actividad-select');
const cantidadSelect = document.getElementById('cantidad-select');
const contenedorTurnos = document.getElementById('contenedor-turnos');
const diaSelect = document.getElementById('dia-select');
const formulario = document.getElementById('formulario');
const frecuenciaSelect = document.getElementById('frecuencia-select');
const idPacienteInput = document.getElementById('id-paciente-input');
const mesSelect = document.getElementById('mes-select');
const turnosCheckbox = document.getElementById('turnos-checkbox');

const elementosRequeridos = [actividadSelect, cantidadSelect, diaSelect, frecuenciaSelect, mesSelect, turnosCheckbox];

mesSelect.addEventListener('change', function() {
    actualizarDiasDelMes(this, diaSelect);
    actualizarPagina();
});

diaSelect.addEventListener('change', function() {
    habilitarElemento(frecuenciaSelect, true);
    actualizarPagina();
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
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
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

document.addEventListener('DOMContentLoaded', async function() {
    const {
        quitarButton: quitarPacienteButton,
        buscador: buscadorPaciente,
        input: pacienteInput,
        sugerencias: sugerenciasPaciente
    } = inicializarElementosBuscador('paciente');

    try {
        const actividades = await apiFetch('/actividades?id_tipo_actividad=2');
        actividades.forEach(actividad => {
            agregarOpcion(actividadSelect, actividad.id, actividad.nombre);
        });
    } catch (error) {
        console.error(error);
        mostrarAlerta('error', 'Error al cargar los tratamientos', error.message);
    }

    [actividadSelect, cantidadSelect, frecuenciaSelect, turnosCheckbox].forEach(elemento => {
        elemento.addEventListener('change', actualizarPagina);
    });

    inicializarSugerenciasListeners(buscadorPaciente, pacienteInput, sugerenciasPaciente, '/buscar-pacientes', crearLiPaciente);

    sugerenciasPaciente.addEventListener('click', function(e) {
        const liSeleccionado = e.target.closest('li');
        if (!liSeleccionado) return;

        const idPaciente = parseInt(liSeleccionado.dataset.idPaciente);
        if (!idPaciente) return;

        idPacienteInput.value = idPaciente;
        pacienteInput.value = liSeleccionado.textContent;
        habilitarBuscador(buscadorPaciente, pacienteInput, quitarPacienteButton, false);
        limpiarSugerencias(sugerenciasPaciente);

        actualizarPagina();
    });

    quitarPacienteButton.addEventListener('click', function() {
        idPacienteInput.value = '';
        habilitarBuscador(buscadorPaciente, pacienteInput, this, true);
        limpiarTurnos(contenedorTurnos);
        actualizarPrimeraFechaFueSeleccionada(false);
    });
});
