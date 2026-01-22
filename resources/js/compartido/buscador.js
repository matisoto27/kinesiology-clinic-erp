import { apiFetch, mostrarAlerta, mostrarElemento } from '@compartido/general.js';

export function configurarBuscador(nombre, url, constructorLi) {
    const elementos = {
        idSeleccionado: document.getElementById(`id-${nombre}-seleccionado`),
        quitarButton: document.getElementById(`quitar-${nombre}-button`),
        buscador: document.getElementById(`buscador-${nombre}`),
        input: document.getElementById(`${nombre}-input`),
        sugerencias: document.getElementById(`sugerencias-${nombre}`)
    }

    const { quitarButton, buscador, input, sugerencias } = elementos;

    let abortController = null;
    let debounceTimeout = null;
    let indiceSeleccionado = -1;

    input.addEventListener('input', function() {
        if (abortController) abortController.abort();
        clearTimeout(debounceTimeout);

        debounceTimeout = setTimeout(async () => {
            const valorIngresado = this.value.trim();
            indiceSeleccionado = -1;

            if (valorIngresado.length < 3) {
                redondearBordeInferior(buscador, true);
                limpiarSugerencias(sugerencias);
                return;
            }

            limpiarSugerencias(sugerencias);
            abortController = new AbortController();

            try {
                const ruta = `${url}?consulta=${encodeURIComponent(valorIngresado)}`;
                const respuesta = await apiFetch(ruta, { signal: abortController.signal });

                const entidades = Array.isArray(respuesta) ? respuesta : Object.values(respuesta)[0];

                if (!entidades || entidades.length === 0) {
                    const li = document.createElement('li');

                    li.classList.add('bg-white', 'cursor-pointer', 'p-2', 'rounded-b-md', 'text-gray-500', 'text-left');
                    li.innerHTML = `
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Sin coincidencias
                    `;

                    sugerencias.appendChild(li);
                } else {
                    entidades.forEach((entidad, indice) => {
                        const esUltima = indice === entidades.length - 1;
                        const li = constructorLi(entidad, esUltima);
                        sugerencias.appendChild(li);
                    })
                }

                redondearBordeInferior(buscador, false);
                mostrarElemento(sugerencias, true);

            } catch (error) {
                manejarErrorBusqueda(error);
            } finally {
                abortController = null;
            }
        }, 80);
    });

    input.addEventListener('keydown', function(event) {
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

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !sugerencias.contains(e.target)) {
            redondearBordeInferior(buscador, true);
            limpiarSugerencias(sugerencias);
            indiceSeleccionado = -1;
        }
    });

    const habilitarBuscador = (confirma) => {
        input.disabled = !confirma;
        if (confirma) {
            input.value = '';
            input.focus();
        }
        buscador.classList.toggle('bg-[#3A8F8E]', confirma);
        buscador.classList.toggle('bg-[#6BA9A9]', !confirma);
        mostrarElemento(quitarButton, !confirma);
    }

    return { elementos, habilitarBuscador };
}

function redondearBordeInferior(elemento, confirma) {
    elemento.classList.toggle('rounded-b-xl', confirma);
    elemento.classList.toggle('rounded-b-none', !confirma);
}

export function limpiarSugerencias(sugerencias) {
    sugerencias.innerHTML = '';
    mostrarElemento(sugerencias, false);
}

function manejarErrorBusqueda(error) {
    if (error.name === 'AbortError') return;

    console.error('Error al realizar la búsqueda:', error);
    mostrarAlerta('error', 'Ocurrió un error al intentar realizar la búsqueda', error.message);
}

function actualizarSeleccion(sugerenciasRecibidas) {
    sugerenciasRecibidas.forEach((sugerencia, indice) => {
        const esSeleccionado = indice === indiceSeleccionado;
        sugerencia.classList.toggle('bg-[#F5D500]', esSeleccionado);
        sugerencia.classList.toggle('bg-white', !esSeleccionado);
    })
}
