export async function mostrarAlerta(icono, titulo, mensaje) {
    await Swal.fire({
        icon: icono,
        title: titulo,
        html: `<pre style="text-align:center; white-space: pre-wrap;">${mensaje}</pre>`
    });
}

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

        const errores = datos?.errors;
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

export function transformarFecha(fecha) {
    const año = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');
    const hora = String(fecha.getHours()).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');
    const segundos = String(fecha.getSeconds()).padStart(2, '0');

    return `${año}-${mes}-${dia} ${hora}:${minutos}:${segundos}`;
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
 * Habilita o deshabilita un elemento, ajustando su color de fondo para reflejar su estado.
 * @param {HTMLElement} elemento - El elemento HTML a modificar.
 * @param {boolean} confirma - Utilizar true para habilitar, false para deshabilitar.
 */
export function habilitarSelect(elemento, confirma) {
    elemento.disabled = !confirma;

    elemento.classList.toggle('bg-[#3A8F8E]', confirma);
    elemento.classList.toggle('text-white', confirma);

    elemento.classList.toggle('bg-[#6BA9A9]', !confirma);
    elemento.classList.toggle('cursor-not-allowed', !confirma);
    elemento.classList.toggle('text-[#E0F0F0]', !confirma);
}

export const DIAS_SEMANA = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"];
