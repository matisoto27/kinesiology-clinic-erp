import { apiFetch, mostrarAlerta, mostrarElemento } from '@compartido/general.js';
import { obtenerElementosBuscador } from '@compartido/referencias-dom.js';

let abortController = null;
let debounceTimeout = null;
let indiceSeleccionado = -1;

export function inicializarSugerenciasListeners(crearLiPaciente) {
    const { nombreDiv, nombreInput, sugerencias } = obtenerElementosBuscador();

    nombreInput.addEventListener('input', function() {

        if (abortController) abortController.abort();

        clearTimeout(debounceTimeout);

        debounceTimeout = setTimeout(async () => {

            let nombreIngresado = this.value.trim();

            if (nombreIngresado.length < 3) {
                redondearBordeInferior(nombreDiv, true);
                limpiarSugerencias();
                return;
            }

            limpiarSugerencias();
            abortController = new AbortController();

            try {
                const pacientes = await obtenerPacientes(nombreIngresado, abortController.signal);
                if (pacientes === null) return;

                if (pacientes.length === 0) {
                    const li = document.createElement('li');
                    li.classList.add('bg-white', 'cursor-pointer', 'p-2', 'rounded-b-md', 'text-gray-500', 'text-left');
                    li.textContent = 'No se encontraron pacientes';

                    sugerencias.appendChild(li);

                } else {

                    pacientes.forEach((paciente, i) => {
                        sugerencias.appendChild(crearLiPaciente(paciente, i === pacientes.length - 1));
                    })
                }

                redondearBordeInferior(nombreDiv, false);
                mostrarElemento(sugerencias, true);

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
            redondearBordeInferior(nombreDiv, true);
            limpiarSugerencias();
        }
    });
}

function redondearBordeInferior(elemento, confirma) {
    elemento.classList.toggle('rounded-b-xl', confirma);
    elemento.classList.toggle('rounded-b-none', !confirma);
}

export function limpiarSugerencias() {
    const { sugerencias } = obtenerElementosBuscador();

    indiceSeleccionado = -1;
    sugerencias.innerHTML = '';
    mostrarElemento(sugerencias, false);
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

export function habilitarNombre(confirma) {
    const { nombreDiv, nombreInput, eliminarButton } = obtenerElementosBuscador();

    nombreInput.disabled = !confirma;
    if (confirma) {
        nombreInput.value = '';
    }

    nombreDiv.classList.toggle('bg-[#3A8F8E]', confirma);
    nombreDiv.classList.toggle('bg-[#6BA9A9]', !confirma);

    mostrarElemento(eliminarButton, !confirma);
}

async function obtenerPacientes(nombreIngresado, signal) {
    try {
        const { pacientes } = await apiFetch(
            `/buscar-pacientes?query=${encodeURIComponent(nombreIngresado)}`,
            { signal }
        );

        return pacientes;
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error(error);
            await mostrarAlerta('error', 'Error al buscar los pacientes', error.message);
        }

        return null;
    }
}
