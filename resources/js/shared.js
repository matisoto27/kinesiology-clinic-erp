let abortController = null;
let debounceTimeout = null;
let indiceSeleccionado = -1;

/**
 * @param {string} url La URL a la que se realiza la petición.
 * @param {object} [options={}] Las opciones estándar de la función fetch.
 * @returns {Promise<any>}
 */
export async function apiFetch(url, options = {}) {

    let respuesta;
    let datos = null;

    try {

        respuesta = await fetch(url, options);

    } catch (error) {

        if (error.name === 'AbortError') throw error;
        throw new Error('Error al conectar con el servidor.');
    }

    if (respuesta.status !== 204) {
        try {
            datos = await respuesta.json();
        } catch (error) {
            throw new Error('La respuesta del servidor no fue JSON válido.');
        }
    }

    if (!respuesta.ok) {

        const errores = datos?.errores;
        let mensaje;

        if (errores) {
            mensaje = Object.values(errores)
                .flat()
                .map(e => `- ${e}`)
                .join('\n');
        } else {
            mensaje = datos?.error || `Error al procesar la solicitud (Estado: ${respuesta.status}).`
        }

        throw new Error(mensaje);
    }

    return datos;
}

export function limpiarSugerencias() {
    indiceSeleccionado = -1;
    sugerencias.innerHTML = '';
    sugerencias.classList.add('hidden');
}

export function limpiarTurnos() {
    contenedorTurnos.innerHTML = '';
    actualizarDesdeActual(false);
}

function redondearNombre(confirma) {
    nombreDiv.classList.toggle('rounded-b-xl', confirma);
    nombreDiv.classList.toggle('rounded-b-none', !confirma);
}

export function habilitarNombre(confirma) {

    nombreInput.disabled = !confirma;
    if (confirma) {
        nombreInput.value = '';
    }

    nombreDiv.classList.toggle('bg-[#3A8F8E]', confirma);
    nombreDiv.classList.toggle('bg-[#6BA9A9]', !confirma);

    eliminarButton.classList.toggle('hidden', confirma);
}

/**
 * Crea y devuelve la cadena HTML para un elemento <option> que se utiliza como opción por defecto.
 * @param {string} contenidoTextual - El texto visible que contendrá.
 * @returns {string} La cadena HTML completa: '<option value="" disabled selected>...</option>'.
 */
export function crearOpcionPorDefecto(contenidoTextual) {
    return `<option value="" disabled selected>${contenidoTextual}</option>`;
}

/**
 * Crea, configura y añade un nuevo elemento <option> del DOM al <select> especificado.
 * @param {HTMLSelectElement} select - El elemento <select> requerido al que se debe añadir la opción.
 * @param {string} valor - El valor interno que tendrá la opción.
 * @param {string} contenidoTextual - El texto visible que mostrará la opción.
 * @param {boolean} [deshabilitada=false] - Indica si la opción debe estar deshabilitada (atributo 'disabled'). Por defecto es false.
 * @param {boolean} [seleccionada=false] - Indica si la opción debe estar seleccionada por defecto (atributo 'selected'). Por defecto es false.
 * @param {Object<string, string>} [atributos={}] - Objeto de clave-valor con atributos adicionales para el elemento <option>. Por defecto es un objeto vacío.
 * @returns {void} No devuelve ningún valor.
 */
export function agregarOpcion(select, valor, contenidoTextual, deshabilitada = false, seleccionada = false, atributos = {}) {

    const option = document.createElement('option');

    option.value = valor;
    option.textContent = contenidoTextual;
    option.disabled = deshabilitada;
    option.selected = seleccionada;

    for (const clave in atributos) {
        if (Object.hasOwnProperty.call(atributos, clave)) {
            option.dataset[clave] = atributos[clave];
        }
    }

    select.appendChild(option);
}

/**
 * @param {Array<string>} turnos
 * @param {number} cantidadSemanasNecesarias - Cantidad total de semanas necesarias para registrar turnos (N).
 * @returns {Array<Object>} Arreglo con los turnos de las semanas [1] a [N-1].
 */
export function obtenerTurnosSemanasCriticas(turnos, cantidadSemanasNecesarias) {

    if (cantidadSemanasNecesarias <= 1) return [];

    const indiceInicio = 1;
    const indiceFin = cantidadSemanasNecesarias - 1;

    const turnosSemanasCriticas = [];

    for (let i = indiceInicio; i <= indiceFin; i++) {

        const turnosDeLaSemana = obtenerTurnosSemana(turnos, i);

        if (turnosDeLaSemana) {
            turnosSemanasCriticas.push(turnosDeLaSemana);
        }
    }

    return turnosSemanasCriticas;
}

/**
 * Filtra los turnos para una semana específica y los agrupa por fecha.
 * @param {Array<string>} turnosDisponibles - Array de 'YYYY-MM-DD HH:MM'.
 * @param {number} desplazamiento - Desplazamiento semanal (0=Actual, 1=Sig.).
 * @returns {Record<string, string[]>} Objeto de turnos por fecha { 'YYYY-MM-DD': ['HH:MM', ...] }.
 */
export function obtenerTurnosSemana(turnosDisponibles, desplazamiento) {

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

export function semanaCubreFrecuencia(semana, frecuenciaSemanal) {
    return Object.keys(semana).length >= frecuenciaSemanal;
}

export async function mostrarErrorTurnosInsuficientes() {
    await mostrarAlerta(
        'error',
        'Turnos insuficientes',
        'No hay suficientes turnos disponibles como para cubrir la frecuencia semanal seleccionada.'
    );
}

export async function mostrarAlerta(icono, titulo, mensaje) {
    await Swal.fire({
        icon: icono,
        title: titulo,
        html: `<pre style="text-align:center; white-space: pre-wrap;">${mensaje}</pre>`
    });
}

/**
 * Consolida turnos de varias semanas por Día de la Semana y Hora.
 * @param {Record<string, string[]>[]} turnosPorSemana - Array de objetos de turnos por fecha.
 * @returns {Record<string, Record<string, number>>} { 'Lunes': { '08:00': 4 } }.
 */
export function consolidarTurnosPorDia(turnosPorSemana) {

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
export function renderizarTurnosFijos(frecuenciaSemanal, diasConTurnos, contenedor) {

    let turnoHTML = '';
    
    for (let i = 1; i <= frecuenciaSemanal; i++) {

        turnoHTML += `
            <div class="mb-4 flex gap-5 turno">
                <div class="flex flex-col">
                    <label class="font-medium text-lg text-white">Turno ${i}</label>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="font-medium text-lg text-white">Día de la semana</label>
                    <select class="bg-[#3A8F8E] p-2 rounded-xl text-xl text-white dia-select" required>
                        <option value="" disabled selected>Seleccione un día</option>
                        ${diasConTurnos.map(dia => `<option value="${dia}">${dia}</option>`).join('')}
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="font-medium text-lg text-white">Hora de inicio</label>
                    <select class="bg-[#6BA9A9] cursor-not-allowed text-[#E0F0F0] p-2 rounded-xl text-xl hora-select" disabled required>
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
export function actualizarDiasDeshabilitados(diaSelects) {

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
 * @param {number} [cantidadSemanas=4] - Número de semanas necesarias para agendar todos los turnos según la frecuencia semanal.
 */
export function cargarHorarios(select, turnosPorDia, cantidadSemanas = 4) {

    const diaSeleccionado = select.value;
    const horariosDisponibles = turnosPorDia[diaSeleccionado] ?? {};
    const horas = Object.keys(horariosDisponibles).sort();

    const turnoDiv = select.closest('.turno');
    const horaSelect = turnoDiv.querySelector('.hora-select');

    horaSelect.innerHTML = crearOpcionPorDefecto('Seleccione un horario');

    horas.forEach(hora => {
        const [hh, mm] = hora.split(':');
        const horaConvertida = `${hh}:${mm}hs (${horariosDisponibles[hora]} / ${cantidadSemanas} turnos disponibles)`;
        agregarOpcion(horaSelect, hora, horaConvertida);
    });

    habilitarSelect(horaSelect, !!diaSeleccionado);

    horaSelect.addEventListener('change', deshabilitarHoraSeleccionada);
}

export function deshabilitarHoraSeleccionada(event) {

    const horaSelect = event.target;
    const horaSeleccionada = horaSelect.value;

    Array.from(horaSelect.options).forEach(option => {

        if (option.value === '') {
            option.disabled = true;
            return;
        }

        option.disabled = (option.value === horaSeleccionada);
    });
}

/**
 * Habilita o deshabilita un elemento, ajustando su color de fondo para reflejar su estado.
 * @param {HTMLElement} elemento - El elemento HTML a modificar.
 * @param {boolean} confirma - true para habilitar y usar el color de "confirmación/activo", false para deshabilitar y usar el color de "inactivo".
 */
export function habilitarSelect(elemento, confirma) {
    elemento.disabled = !confirma;

    elemento.classList.toggle('bg-[#3A8F8E]', confirma);
    elemento.classList.toggle('text-white', confirma);

    elemento.classList.toggle('bg-[#6BA9A9]', !confirma);
    elemento.classList.toggle('cursor-not-allowed', !confirma);
    elemento.classList.toggle('text-[#E0F0F0]', !confirma);
}

export function convertirFechaParaMostrar(fechaStr) {

    const opcionesFormato = {
        weekday: 'long',
        day: 'numeric',
        month: 'long'
    };
    const [anio, mes, dia] = fechaStr.split('-');
    const fecha = new Date(anio, mes - 1, dia);

    return fecha.toLocaleDateString('es-ES', opcionesFormato);
}

async function obtenerPacientes(nombreIngresado, signal) {
    try {

        const datos = await apiFetch(`/buscar-pacientes?query=${encodeURIComponent(nombreIngresado)}`, signal);
        return datos.pacientes;

    } catch (error) {

        console.error(error);
        if (error.name !== 'AbortError') {
            mostrarAlerta('error', 'Error al buscar los pacientes', error.message);
        }
        return null;
    }
}

function crearLiCeroPacientes() {

    const li = document.createElement('li');

    li.classList.add('bg-white', 'cursor-pointer', 'p-2', 'rounded-b-md', 'text-gray-500', 'text-left');
    li.textContent = 'No se encontraron pacientes';

    return li;
}

function actualizarSeleccion(pacientesSugeridos) {
    pacientesSugeridos.forEach((sug, indice) => {
        if (indice === indiceSeleccionado) {
            sug.classList.add('bg-yellow-400');
        } else {
            sug.classList.remove('bg-yellow-400');
        }
    })
}

export function actualizarDesdeActual(valor) {
    desdeActual = valor;
}

export function actualizarPrimeraFechaFueSeleccionada(valor) {
    primeraFechaFueSeleccionada = valor;
}

export function transformarFecha(fecha) {

    const año = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');
    const hora = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');
    const segundos = String(fecha.getSeconds()).padStart(2, '0');

    return `${año}-${mes}-${dia} ${hora}:${minutos}:${segundos}`;
}

export function inicializarSugerenciasListeners(crearLiPaciente) {

    nombreInput.addEventListener('input', function() {

        if (abortController) abortController.abort();

        clearTimeout(debounceTimeout);

        debounceTimeout = setTimeout(async () => {

            let nombreIngresado = this.value.trim();

            if (nombreIngresado.length < 3) {
                redondearNombre(true);
                limpiarSugerencias();
                return;
            }

            limpiarSugerencias();
            abortController = new AbortController();
            const signal = abortController.signal;

            try {

                const pacientes = await obtenerPacientes(nombreIngresado, signal);
                if (pacientes === null) return; // Error al buscar los pacientes

                if (pacientes.length === 0) {

                    sugerencias.appendChild(crearLiCeroPacientes());

                } else {

                    pacientes.forEach((paciente, i) => {
                        sugerencias.appendChild(crearLiPaciente(paciente, i === pacientes.length - 1));
                    })
                }

                redondearNombre(false);
                sugerencias.classList.remove('hidden');

                } catch (error) {

                    console.error('Error en el gestor de cambios de nombre:', error);
                    await mostrarAlerta('error', 'Error inesperado', error);

                } finally {
                    abortController = null;
                }

        }, 80);
    });

    nombreInput.addEventListener('keydown', function(event) {

        const pacientesSugeridos = sugerencias.querySelectorAll('li');
        const cantidad = pacientesSugeridos.length;

        if (cantidad === 0) return;

        switch(event.key) {
            case 'ArrowDown':
            case 'Tab':
                event.preventDefault();
                indiceSeleccionado = (indiceSeleccionado + 1) % cantidad;
                actualizarSeleccion(pacientesSugeridos);
                break;
            case 'ArrowUp':
                event.preventDefault();
                indiceSeleccionado = (indiceSeleccionado - 1 + cantidad) % cantidad;
                actualizarSeleccion(pacientesSugeridos);
                break;
            case 'Enter':
                event.preventDefault();
                if (indiceSeleccionado >= 0 && indiceSeleccionado < cantidad) {
                    pacientesSugeridos[indiceSeleccionado].click();
                }
                break;
        }
    });

    document.addEventListener('click', function(event) {
        if (!nombreInput.contains(event.target) && !sugerencias.contains(event.target)) {
            redondearNombre(true);
            limpiarSugerencias();
        }
    });
}

export const DIAS_SEMANA = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"];
export const actividadSelect = document.getElementById('actividad-select');
export const cantidadSelect = document.getElementById('cantidad-select');
export const contenedorTurnos = document.getElementById('contenedor-turnos');
export const eliminarButton = document.getElementById('eliminar-button');
export const formulario = document.getElementById('formulario');
export const idPacienteInput = document.getElementById('id-paciente-input');
export const nombreDiv = document.getElementById('nombre-div');
export const nombreInput = document.getElementById('nombre-input');
export const sugerencias = document.getElementById('sugerencias');
export const token = document.querySelector('meta[name="csrf-token"]').content;
export const turnosCheckbox = document.getElementById('turnos-checkbox');
export let desdeActual = false;
export let primeraFechaFueSeleccionada = false;
