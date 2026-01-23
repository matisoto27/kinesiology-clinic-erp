import { configurarBuscador, limpiarSugerencias } from '@compartido/buscador.js';

import {
    agregarOpcion,
    apiFetch,
    crearOpcionPorDefecto,
    convertirFechaParaMostrar,
    DIAS_SEMANA,
    habilitarElemento,
    mostrarAlerta,
    obtenerValor,
    transformarFecha
} from '@compartido/general.js';

import {
    obtenerDesdeActual,
    actualizarDesdeActual,
    obtenerPrimeraFechaFueSeleccionada,
    actualizarPrimeraFechaFueSeleccionada,
    obtenerTotalAPagar,
    actualizarTotalAPagar,
    obtenerUltimaActividadValida,
    actualizarUltimaActividadValida
} from '../componentes/gestor-estado.js';

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
    restablecerMoneda,
    semanaCubreFrecuencia
} from '../componentes/logica-turnos.js';

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

async function manejarCambio() {
    alternarEstadoCarga(true);
    try {
        await gestionarCambiosDeCantidad();
    } finally {
        alternarEstadoCarga(false);
    }
}

function alternarEstadoCarga(estaCargando) {
    cantidadSelect.disabled = estaCargando;
    turnosCheckbox.disabled = estaCargando;
}

async function gestionarCambiosDeCantidad() {
    actualizarDesdeActual(false);
    actualizarPrimeraFechaFueSeleccionada(false);
    actualizarTotalAPagar(0);

    try {
        const idPaciente = obtenerValor(idPacienteSeleccionado);
        const idActividad = obtenerValor(actividadSelect);
        const frecuenciaSemanal = obtenerValor(cantidadSelect);

        if (idPaciente === null || idActividad === null || frecuenciaSemanal === null) return;

        const opcionSeleccionada = cantidadSelect.options[cantidadSelect.selectedIndex];
        const idActividadCombo = obtenerValor(opcionSeleccionada.dataset.id);

        if (!idActividadCombo) return;

        const precio = await apiFetch(`/actividades-combos/${idActividadCombo}/precio-vigente`);
        actualizarTotalAPagar(precio);
        precioInput.value = '$' + precio;
        limpiarTurnos(contenedorTurnos);

        const turnos = await apiFetch(`/actividades/${idActividad}/turnos-disponibles?id_paciente=${idPaciente}&cantidad_semanas=4`);
        const turnosSemanasCriticas = obtenerTurnosSemanasCriticas(turnos, 4);

        const insuficiente = turnosSemanasCriticas.some(semana => {
            return !semanaCubreFrecuencia(semana, frecuenciaSemanal);
        });

        if (insuficiente) {
            await mostrarErrorTurnosInsuficientes();
            return;
        }

        const turnosSemanaActual = obtenerTurnosSemana(turnos, 0);
        const turnosSemanaCuatro = obtenerTurnosSemana(turnos, 4);

        const semanaActualCubre = semanaCubreFrecuencia(turnosSemanaActual, frecuenciaSemanal);
        const semanaCuatroCubre = semanaCubreFrecuencia(turnosSemanaCuatro, frecuenciaSemanal);

        if (!semanaActualCubre && !semanaCuatroCubre) {
            await mostrarErrorTurnosInsuficientes();
            return;
        }

        let turnosPorSemana;
        let turnoHTML = '';

        if (turnosCheckbox.checked) {
            const resultado = await determinarTurnosPorSemana(semanaActualCubre, semanaCuatroCubre, turnosSemanaActual, turnosSemanasCriticas, turnosSemanaCuatro);

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
                    cargarHorarios(this, turnosPorDia); 
                });
            });

        } else {

            const turnosPrimeraSemana = turnosSemanasCriticas[0];

            const fechasSemanaActual = Object.keys(turnosSemanaActual);
            const fechasSemanaUno = Object.keys(turnosPrimeraSemana);

            const opcionesPrimeraSemana = semanaActualCubre
                ? [...fechasSemanaActual, ...fechasSemanaUno]
                : fechasSemanaUno;

            for (let i = 1; i <= 4; i++) {
                turnoHTML += `<h3 class="mb-4 border-t font-medium text-xl text-[#F5D500]">Semana ${i}</h3>`;

                for (let j = 1; j <= frecuenciaSemanal; j++) {
                    turnoHTML += `
                        <div class="fila-formulario turno" data-semana="${i}">

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
                    : [...turnosSemanasCriticas, turnosSemanaCuatro];

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
        await mostrarAlerta('error', 'Error inesperado', error.message);
    }
}

const actividadSelect = document.getElementById('actividad-select');
const cantidadSelect = document.getElementById('cantidad-select');
const contenedorTurnos = document.getElementById('contenedor-turnos');
const formulario = document.getElementById('formulario');
const precioInput = document.getElementById('precio-input');
const turnosCheckbox = document.getElementById('turnos-checkbox');
const {
    elementos: {
        idSeleccionado: idPacienteSeleccionado,
        quitarButton: quitarPacienteButton,
        input: pacienteInput,
        sugerencias: sugerenciasPaciente
    },
    habilitarBuscador
} = configurarBuscador('paciente', '/buscar-pacientes', crearLiPaciente);

actividadSelect.addEventListener('change', async function() {
    const idActividad = obtenerValor(this);
    if (idActividad === null) return;

    let combos = [];

    try {
        combos = await apiFetch(`/actividades/${idActividad}/combos?con_precio=true`);
    } catch (error) {
        this.value = obtenerUltimaActividadValida();
        console.error(error);
        await mostrarAlerta('error', 'Error al cargar los combos', error.message);
        return;
    }

    if (combos.length === 0) {
        this.value = obtenerUltimaActividadValida();
        mostrarAlerta('error', 'No hay combos disponibles', 'No existen combos con un precio registrado para la actividad seleccionada.');
        return;
    }

    actualizarUltimaActividadValida(idActividad);

    cantidadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    combos.forEach(combo => {
        const sesionesPorSemana = combo.cantidad_sesiones / 4;
        const contenidoTextual = `${sesionesPorSemana} ${sesionesPorSemana === 1 ? 'vez' : 'veces'} por semana`;
        const atributos = { id: combo.id_actividad_combo };
        agregarOpcion(cantidadSelect, sesionesPorSemana, contenidoTextual, false, false, atributos);
    });
    habilitarElemento(cantidadSelect, true);

    restablecerMoneda(precioInput);
    limpiarTurnos(contenedorTurnos);    
});

cantidadSelect.addEventListener('change', manejarCambio);

formulario.addEventListener('submit', async (e) => {
    e.preventDefault();

    try {
        const idActividad = obtenerValor(actividadSelect);
        const idPaciente = obtenerValor(idPacienteSeleccionado);

        if (idActividad === null || idPaciente === null) {
            throw new Error('Por favor, seleccione un paciente y una actividad.');
        }

        const turnosAutogenerados = turnosCheckbox.checked;
        const frecuenciaSemanal = cantidadSelect.value;

        const cantidadTurnos = turnosAutogenerados
            ? frecuenciaSemanal
            : frecuenciaSemanal * 4;

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
        const cantSesiones = frecuenciaSemanal * 4;
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
                cant_sesiones: cantSesiones,
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
    idPacienteSeleccionado.value = '';
    habilitarBuscador(true);

    actividadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una actividad');
    habilitarElemento(actividadSelect, false);

    cantidadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    habilitarElemento(cantidadSelect, false);

    restablecerMoneda(precioInput);
    limpiarTurnos(contenedorTurnos);
});

sugerenciasPaciente.addEventListener('click', async function(e) {
    const elementoClickeado = e.target.closest('li');
    if (!elementoClickeado) return;

    const idPaciente = obtenerValor(elementoClickeado.dataset.idPaciente);
    if (idPaciente === null) return;

    let actividades = [];

    try {
        actividades = await apiFetch(`/pacientes/${idPaciente}/actividades-generales-sin-suscripcion`);
    } catch (error) {
        console.error(error);
        mostrarAlerta('error', 'Error al cargar las actividades', error.message);
        return;
    }

    actualizarUltimaActividadValida('');

    idPacienteSeleccionado.value = idPaciente;
    pacienteInput.value = elementoClickeado.textContent;
    habilitarBuscador(false);
    limpiarSugerencias(this);

    if (actividades.length === 0) {
        actividadSelect.innerHTML = crearOpcionPorDefecto('Paciente suscripto a todas');
        return;
    }

    actividadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una actividad');
    actividades.forEach(actividad => {
        agregarOpcion(actividadSelect, actividad.id, actividad.nombre);
    });
    habilitarElemento(actividadSelect, true);
});

turnosCheckbox.addEventListener('change', manejarCambio);
