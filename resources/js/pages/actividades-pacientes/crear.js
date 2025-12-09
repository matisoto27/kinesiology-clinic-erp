import {
    actividadSelect,
    apiFetch,
    eliminarButton,
    idPacienteInput,
    inicializarSugerenciasListeners,
    mostrarAlerta,
    nombreInput,
    limpiarSugerencias,
    sugerencias,
    token
} from '../../shared.js';

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

function habilitarNombreInput(confirma) {

    nombreInput.disabled = !confirma;
    nombreInput.classList.toggle('bg-[#3A8F8E]', confirma);
    nombreInput.classList.toggle('bg-[#6BA9A9]', !confirma);
}

/**
 * Crea y devuelve la cadena HTML para un elemento <option> que se utiliza como opción por defecto.
 * @param {string} contenidoTextual - El texto visible que contendrá.
 * @returns {string} La cadena HTML completa: '<option value="" disabled selected>...</option>'.
 */
function crearOpcionPorDefecto(contenidoTextual) {
    return `<option value="" disabled selected>${contenidoTextual}</option>`;
}

/**
 * Crea, configura y añade un nuevo elemento <option> del DOM al <select> especificado.
 * @param {HTMLSelectElement} select - El elemento <select> requerido al que se debe añadir la opción.
 * @param {string} valor - El valor interno que tendrá la opción.
 * @param {string} contenidoTextual - El texto visible que mostrará la opción.
 * @param {boolean} [deshabilitada=false] - Indica si la opción debe estar deshabilitada (atributo 'disabled'). Por defecto es false.
 * @param {boolean} [seleccionada=false] - Indica si la opción debe estar seleccionada por defecto (atributo 'selected'). Por defecto es false.
 * @returns {void} No devuelve ningún valor.
 */
function agregarOpcion(select, valor, contenidoTextual, deshabilitada = false, seleccionada = false) {

    const option = document.createElement('option');

    option.value = valor;
    option.textContent = contenidoTextual;
    option.disabled = deshabilitada;
    option.selected = seleccionada;

    select.appendChild(option);
}

function habilitarSelect(elementoSelect, confirma) {
    if (confirma) {
        elementoSelect.classList.remove('bg-[#6BA9A9]', 'cursor-not-allowed', 'text-[#E0F0F0]');
        elementoSelect.classList.add('bg-[#3A8F8E]', 'text-white');
        elementoSelect.disabled = false;
    } else {
        elementoSelect.classList.remove('bg-[#3A8F8E]', 'text-white');
        elementoSelect.classList.add('bg-[#6BA9A9]', 'cursor-not-allowed', 'text-[#E0F0F0]');
        elementoSelect.disabled = true;
    }
}

function reiniciarPrecio() {
    precioInput.value = "$0,00";
}

function deshabilitarCantidadSelect() {
    cantidadSelect.innerHTML = '<option value="" disabled selected>Seleccione una frecuencia</option>';
    habilitarSelect(cantidadSelect, false);
}

function limpiarTurnos() {
    contenedorTurnos.innerHTML = '';
}

/**
 * Filtra los turnos para una semana específica y los agrupa por fecha.
 * @param {Array<string>} turnosDisponibles - Array de 'YYYY-MM-DD HH:MM'.
 * @param {number} desplazamiento - Desplazamiento semanal (0=Actual, 1=Sig.).
 * @returns {Record<string, string[]>} Objeto de turnos por fecha { 'YYYY-MM-DD': ['HH:MM', ...] }.
 */
function obtenerTurnosSemana(turnosDisponibles, desplazamiento) {

    const hoy = new Date();
    const [inicio, fin] = obtenerLunesViernesSemana(hoy, desplazamiento);
    const inicioISO = obtenerFechaISO(inicio);
    const finISO = obtenerFechaISO(fin);

    const turnosSemana = {};

    turnosDisponibles.forEach(turno => {

        const [fecha, hora] = turno.split(' ');

        if (fecha >= inicioISO && fecha <= finISO) {
            turnosSemana[fecha] = turnosSemana[fecha] || [];
            turnosSemana[fecha].push(hora);
        }
    });

    return turnosSemana;
}

/**
 * @param {Date} fechaBase
 * @param {number} desplazamiento
 * @returns {[Date, Date]} [Lunes, Viernes] de la semana deseada.
 */
function obtenerLunesViernesSemana(fechaBase, desplazamiento) {

    const copiaFecha = new Date(fechaBase);
    const diaHoy = copiaFecha.getDay();

    const diasAlLunes = (diaHoy === 0) ? 6 : diaHoy - 1;

    copiaFecha.setDate(copiaFecha.getDate() - diasAlLunes + (desplazamiento * 7));
    copiaFecha.setHours(0, 0, 0, 0);
    const lunes = new Date(copiaFecha);

    copiaFecha.setDate(lunes.getDate() + 4);
    copiaFecha.setHours(23, 59, 59, 999);
    const viernes = copiaFecha;

    return [lunes, viernes];
}

/**
 * @param {Date} fechaHora
 * @returns {string} Fecha en formato 'YYYY-MM-DD'.
 */
function obtenerFechaISO(fechaHora) {
    return fechaHora.toISOString().split('T')[0];
}

/**
 * Consolida turnos de varias semanas por Día de la Semana y Hora.
 * @param {Record<string, string[]>[]} turnosPorSemana - Array de objetos de turnos por fecha.
 * @returns {Record<string, Record<string, number>>} { 'Lunes': { '08:00': 4 } }.
 */
function consolidarTurnosPorDia(turnosPorSemana) {

    const turnosPorDia = {};

    for (const objetoTurnosSemana of turnosPorSemana) {
        for (const [fechaStr, horas] of Object.entries(objetoTurnosSemana)) {

            const fecha = new Date(`${fechaStr}T12:00:00`);
            const indiceDia = fecha.getDay();

            if (indiceDia === 0 || indiceDia === 6) continue;

            const diaSemana = DIAS_SEMANA[indiceDia - 1];

            turnosPorDia[diaSemana] ||= {};

            for (const hora of horas) {
                turnosPorDia[diaSemana][hora] ||= 0;
                turnosPorDia[diaSemana][hora] += 1;
            }
        }
    }

    return turnosPorDia;
}

/**
 * Renderiza el HTML de los selects para la selección de turnos fijos.
 * @param {number} frecuenciaSemanal - Cantidad de turnos a seleccionar.
 * @param {string[]} diasConTurnos - Días de la semana disponibles.
 * @param {HTMLElement} contenedor - El contenedor donde inyectar el HTML.
 */
function renderizarTurnosFijos(frecuenciaSemanal, diasConTurnos, contenedor) {

    let turnoHTML = '';
    
    for (let i = 1; i <= frecuenciaSemanal; i++) {

        turnoHTML += `
            <div class="mb-4 flex gap-5 text-white turno">
                <div class="flex flex-col">
                    <label class="font-medium text-lg">Turno ${i}</label>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="font-medium text-lg">Día de la semana</label>
                    <select class="bg-[#3A8F8E] rounded-md text-lg p-3 dia-select" required>
                        <option value="" disabled selected>Seleccione un día</option>
                        ${diasConTurnos.map(dia => `<option value="${dia}">${dia}</option>`).join('')}
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="font-medium text-lg">Hora de inicio</label>
                    <select class="bg-[#6BA9A9] rounded-md text-lg p-3 cursor-not-allowed text-[#E0F0F0] hora-select" disabled required>
                        <option value="" disabled selected>Seleccione un horario</option>
                    </select>
                </div>
            </div>
        `;
    }
    contenedor.innerHTML = turnoHTML;
}

/**
 * Deshabilita los días de la semana ya seleccionados en otros selects.
 * @param {NodeListOf<HTMLSelectElement>} diaSelects - Todos los selects de día.
 */
function actualizarDiasDeshabilitados(diaSelects) {

    const diasSeleccionados = Array.from(diaSelects)
        .map(s => s.value)
        .filter(v => v);

    diaSelects.forEach(select => {
        Array.from(select.options).forEach(option => {

            if (option.value === '') return;

            option.disabled = diasSeleccionados.includes(option.value) && option.value !== select.value;
        });
    });
}

/**
 * Carga los horarios disponibles en el select de hora correspondiente.
 * @param {HTMLSelectElement} select - El select de día que disparó el evento.
 * @param {Record<string, Record<string, number>>} turnosPorDia - Datos consolidados de disponibilidad.
 */
function cargarHorarios(select, turnosPorDia) {

    const diaSeleccionado = select.value;
    const horariosDisponibles = turnosPorDia[diaSeleccionado] ?? {};
    const horas = Object.keys(horariosDisponibles).sort();

    const turnoDiv = select.closest('.turno');
    const horaSelect = turnoDiv.querySelector('.hora-select');

    horaSelect.innerHTML = crearOpcionPorDefecto('Seleccione un horario');

    horas.forEach(hora => {
        const [hh, mm] = hora.split(':');
        const horaConvertida = `${hh}:${mm}hs (${horariosDisponibles[hora]} / 4 turnos disponibles)`;
        agregarOpcion(horaSelect, hora, horaConvertida);
    });

    habilitarSelect(horaSelect, !!diaSeleccionado);
}

function convertirFechaParaMostrar(fechaStr) {

    const opcionesFormato = {
        weekday: 'long',
        day: 'numeric',
        month: 'long'
    };
    const [anio, mes, dia] = fechaStr.split('-');
    const fecha = new Date(anio, mes - 1, dia);

    return fecha.toLocaleDateString('es-ES', opcionesFormato);
}

function transformarFecha(fecha) {

    const año = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');
    const hora = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');
    const segundos = String(fecha.getSeconds()).padStart(2, '0');

    return `${año}-${mes}-${dia} ${hora}:${minutos}:${segundos}`;
}

async function gestionarCambiosDeCantidad() {
    try {

        const idActividad = parseInt(actividadSelect.value);
        const frecuenciaSemanal = parseInt(cantidadSelect.value);

        if (!idActividad || !frecuenciaSemanal) return;

        const precio = await apiFetch(`/combos-actividad/${frecuenciaSemanal}/precio-actual`);
        totalAPagar = precio;
        precioInput.value = '$' + precio;
        limpiarTurnos();

        const turnos = await apiFetch(`/actividades/${idActividad}/turnos-disponibles`);

        const turnosSemanaUno = obtenerTurnosSemana(turnos, 1);
        const turnosSemanaDos = obtenerTurnosSemana(turnos, 2);
        const turnosSemanaTres = obtenerTurnosSemana(turnos, 3);

        const turnosSemanasCriticas = [turnosSemanaUno, turnosSemanaDos, turnosSemanaTres];

        const insuficiente = turnosSemanasCriticas.some(semana => {
            return Object.keys(semana).length < frecuenciaSemanal;
        });

        if (insuficiente) {
            await mostrarErrorTurnosInsuficientes();
            return;
        }

        const turnosSemanaActual = obtenerTurnosSemana(turnos, 0);
        const turnosSemanaCuatro = obtenerTurnosSemana(turnos, 4);

        const diasDisponiblesActual = Object.keys(turnosSemanaActual).length;
        const diasDisponiblesCuatro = Object.keys(turnosSemanaCuatro).length;

        const semanaActualCubre = diasDisponiblesActual >= frecuenciaSemanal;
        const semanaCuatroCubre = diasDisponiblesCuatro >= frecuenciaSemanal;

        if (!semanaActualCubre && !semanaCuatroCubre) {
            await mostrarErrorTurnosInsuficientes();
            return;
        }

        let turnosPorSemana;
        let turnoHTML = '';

        if (turnosCheckbox.checked) {

            if (semanaActualCubre && semanaCuatroCubre) {

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
                    desdeActual = true;

                } else {

                    turnosPorSemana = [...turnosSemanasCriticas, turnosSemanaCuatro];
                }

            } else if (semanaActualCubre) {

                turnosPorSemana = [turnosSemanaActual, ...turnosSemanasCriticas];

            } else {

                turnosPorSemana = [...turnosSemanasCriticas, turnosSemanaCuatro];
            }

            const turnosPorDia = consolidarTurnosPorDia(turnosPorSemana);

            // Obtener y ordenar los días disponibles
            const diasConTurnos = Object.keys(turnosPorDia);
            diasConTurnos.sort((diaA, diaB) => {
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

            const fechasSemanaActual = Object.keys(turnosSemanaActual);
            const fechasSemanaUno = Object.keys(turnosSemanaUno);

            const opcionesPrimeraSemana = semanaActualCubre
                ? [...fechasSemanaActual, ...fechasSemanaUno]
                : fechasSemanaUno;

            for (let i = 1; i <= 4; i++) {
                turnoHTML += `<h3 class="mb-4 border-t font-medium text-xl text-[#F5D500]">Semana ${i}</h3>`;

                for (let j = 1; j <= frecuenciaSemanal; j++) {
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

                if (primeraFechaFueSeleccionada) return;

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

                    habilitarSelect(select, true);
                });

                primeraFechaFueSeleccionada = true;
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
                });
            });

            habilitarSelect(primerFechaSelect, true);
        }

    } catch (error) {

        console.error('Error en el gestor de cambios de frecuencia semanal:', error);
        await mostrarAlerta('error', 'Error inesperado', error);
    }
}

async function mostrarErrorTurnosInsuficientes() {
    await mostrarAlerta(
        'error',
        'Turnos insuficientes',
        'No hay suficientes turnos disponibles como para cubrir la frecuencia semanal seleccionada.'
    );
}

let valorAnterior = actividadSelect.value || '';
let primeraFechaFueSeleccionada = false;
let totalAPagar = 0;
let desdeActual = false;

const DIAS_SEMANA = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"];
const cantidadSelect = document.getElementById('cantidad-select');
const contenedorTurnos = document.getElementById('contenedor-turnos');
const formulario = document.getElementById('formulario');
const precioInput = document.getElementById('precio-input');
const turnosCheckbox = document.getElementById('turnos-checkbox');

sugerencias.addEventListener('click', async function(e) {
    try {

        const elementoClickeado = e.target;
        const nombreElemento = elementoClickeado.tagName.toLowerCase();

        if (nombreElemento !== 'li') return;

        const idPaciente = parseInt(elementoClickeado.dataset.idPaciente);
        if (!idPaciente) return;

        idPacienteInput.value = idPaciente;
        nombreInput.value = elementoClickeado.textContent;
        habilitarNombreInput(false);

        eliminarButton.classList.remove('hidden');
        valorAnterior = actividadSelect.value || '';
        limpiarSugerencias();

        const actividades = await apiFetch(`/pacientes/${idPaciente}/actividades-generales-sin-suscripcion`);

        if (actividades.length === 0) {
            actividadSelect.innerHTML = crearOpcionPorDefecto('Paciente suscripto a todas');
            return;
        }

        actividadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una actividad');

        actividades.forEach(actividad => {
            agregarOpcion(actividadSelect, actividad.id, actividad.nombre);
        });

        habilitarSelect(actividadSelect, true);

    } catch (error) {
        console.error(error);
        mostrarAlerta('error', 'Error al seleccionar paciente', error.message);
    }
});

eliminarButton.addEventListener('click', function() {

    idPacienteInput.value = 0;

    nombreInput.value = '';
    habilitarNombreInput(true);

    eliminarButton.classList.add('hidden');

    actividadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una actividad');
    habilitarSelect(actividadSelect, false);

    cantidadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    habilitarSelect(cantidadSelect, false);

    reiniciarPrecio();
    limpiarTurnos();

    primeraFechaFueSeleccionada = false;
});

actividadSelect.addEventListener('change', async function() {

    const idActividad = parseInt(this.value);
    if (!idActividad) return;

    this.disabled = true;

    try {

        const combos = await apiFetch(`/actividades/${idActividad}/combos?con_precio=true`);

        if (combos.length === 0) {

            this.value = valorAnterior;
            mostrarAlerta('error', 'No hay combos disponibles', 'No existen combos con un precio registrado para la actividad seleccionada.');
            return;
        }

        reiniciarPrecio();
        deshabilitarCantidadSelect();
        limpiarTurnos();
        valorAnterior = this.value;

        combos.forEach(combo => {
            const sesionesPorSemana = combo.cantidad_sesiones / 4;
            const contenidoTextual = `${sesionesPorSemana} ${sesionesPorSemana === 1 ? 'vez' : 'veces'} por semana`;
            agregarOpcion(cantidadSelect, sesionesPorSemana, contenidoTextual);
        });

        habilitarSelect(cantidadSelect, true);

    } catch (error) {

        this.value = valorAnterior;
        console.error(error);
        await mostrarAlerta('error', 'Error al cargar combos', error.message);
    }
    finally {

        this.disabled = false;
    }
});

cantidadSelect.addEventListener('change', async function() {
    await gestionarCambiosDeCantidad();
});

formulario.addEventListener('submit', async (e) => {
    try {

        e.preventDefault();

        const idActividad = parseInt(actividadSelect.value);
        const idPaciente = parseInt(idPacienteInput.value);

        if (!idActividad || !idPaciente) {
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

        const cantSesiones = frecuenciaSemanal * 4;
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
                cant_sesiones: cantSesiones,
                total_a_pagar: totalAPagar,
                autogenerados: turnosAutogenerados,
                desde_actual: desdeActual,
                turnos: turnos
            })
        };

        await apiFetch(`/actividades-pacientes`, options);

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

        if (eleccion.dismiss === Swal.DismissReason.cancel) {
            window.location.href = '/';
        } else {
            window.location.href = '/pagos';
        }

    } catch (error) {
        console.error(error);
        mostrarAlerta('error', 'Error al registrar los turnos', error.message);
    }
});

turnosCheckbox.addEventListener('change', async function() {
    await gestionarCambiosDeCantidad();
});

document.addEventListener('DOMContentLoaded', function() {
    inicializarSugerenciasListeners(crearLiPaciente);
});
