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

function redondearNombreInput(confirma) {
    if (confirma) {
        nombreInput.classList.remove('rounded-t-md');
        nombreInput.classList.add('rounded-md');
    } else {
        nombreInput.classList.remove('rounded-md');
        nombreInput.classList.add('rounded-t-md');
    }
}

export async function mostrarAlerta(icono, titulo, mensaje) {
    await Swal.fire({
        icon: icono,
        title: titulo,
        html: `<pre style="text-align:center; white-space: pre-wrap;">${mensaje}</pre>`
    });
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

export function inicializarSugerenciasListeners(crearLiPaciente) {

    nombreInput.addEventListener('input', function() {

        if (abortController) abortController.abort();

        clearTimeout(debounceTimeout);

        debounceTimeout = setTimeout(async () => {

            let nombreIngresado = this.value.trim();

            if (nombreIngresado.length < 3) {
                redondearNombreInput(true);
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

                redondearNombreInput(false);
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
            redondearNombreInput(true);
            limpiarSugerencias();
        }
    });
}

export const actividadSelect = document.getElementById('actividad-select');
export const eliminarButton = document.getElementById('eliminar-button');
export const idPacienteInput = document.getElementById('id-paciente-input');
export const nombreInput = document.getElementById('nombre-input');
export const sugerencias = document.getElementById('sugerencias');
export const token = document.querySelector('meta[name="csrf-token"]').content;
