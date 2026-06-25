export async function mostrarAlerta(icono, titulo, mensaje) {
    await Swal.fire({
        icon: icono,
        title: titulo,
        html: `<pre style="text-align:center; white-space: pre-wrap;">${mensaje}</pre>`
    });
}

/**
 * @param {string} url La URL a la que se realiza la petición.
 * @param {object} [opciones={}] Las opciones estándar de la función fetch.
 * @returns {Promise<any>}
 */
export async function apiFetch(url, opciones = {}) {
    let respuesta;
    let datos = null;

    try {
        respuesta = await fetch(url, opciones);
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
            mensaje = datos?.error || datos?.message || `Error al procesar la solicitud (Estado: ${respuesta.status}).`
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

export function habilitarElemento(elemento, confirma) {
    elemento.disabled = !confirma;
}

export function actualizarDiasDelMes(mesSelect, diaSelect) {
    const mes = obtenerValor(mesSelect);
    if (mes === null) return;

    const anio = new Date().getFullYear();

    const diasEnMes = new Date(anio, mes, 0).getDate();

    diaSelect.innerHTML = crearOpcionPorDefecto('Seleccione un día');

    for (let i = 1; i <= diasEnMes; i++) {
        agregarOpcion(diaSelect, i, i);
    }

    habilitarElemento(diaSelect, true);
}

export function obtenerValor(entrada, admiteCero = false, esEntero = true) {
    const valor = (entrada && typeof entrada === 'object' && 'value' in entrada) ? entrada.value : entrada;
    if (valor === null || valor === undefined || valor === '') return null;

    const numero = Number(valor);
    if (!Number.isFinite(numero)) return null;
    if (esEntero && !Number.isInteger(numero)) return null;

    const dentroDelRango = admiteCero ? numero >= 0 : numero > 0;

    return dentroDelRango ? numero : null;
}

export function transformarIngresoMonto(input) {
    let valorIngresado = input.value;

    // No permite ingresar puntos
    // Solo permite ingresar números o coma
    valorIngresado = valorIngresado.replace(/\./g, '').replace(/[^0-9,]/g, '');

    // Si se ingresa una coma como primer caracter, se agrega un 0 delante
    if (valorIngresado.startsWith(',')) valorIngresado = '0' + valorIngresado;

    // Solo puede haber una única coma
    let partes = valorIngresado.split(',');
    let parteEntera = partes[0];
    let parteDecimal = partes.length > 1 ? partes.slice(1).join('') : null;

    if (parteEntera.length > 0) {
        // Eliminar ceros a la izquierda y limitar a 6 dígitos (máximo 9.999.999)
        parteEntera = parseInt(parteEntera, 10).toString().substring(0, 7);

        // Formatear miles con puntos
        parteEntera = parteEntera.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Máximo 2 decimales
    input.value = partes.length > 1
        ? parteEntera + ',' + parteDecimal.substring(0, 2)
        : parteEntera + (valorIngresado.includes(',') ? ',' : '');
}

export function textoADecimal(montoStr) {
    if (typeof montoStr !== 'string' || montoStr.trim() === '') {
        return 0;
    }

    const limpio = montoStr.replace(/\./g, '').replace(',', '.');
    const transformado = parseFloat(limpio);

    return isNaN(transformado) ? 0 : transformado;
}

export function inputEnAlerta(input, confirma) {
    input.classList.toggle('border-red-500', confirma);
    input.classList.toggle('focus:ring-red-500', confirma);
    input.classList.toggle('text-red-600', confirma);
    input.classList.toggle('focus:ring-green-500', !confirma);
}

export function mostrarElemento(elemento, confirma) {
    elemento.classList.toggle('hidden', !confirma);
}

export function formatearFechaLocalISO(fecha) {
    const yyyy = fecha.getFullYear();
    const mm = String(fecha.getMonth() + 1).padStart(2, '0');
    const dd = String(fecha.getDate()).padStart(2, '0');

    return `${yyyy}-${mm}-${dd}`;
}

export function lunesDeSemana(fecha) {
    const lunes = new Date(fecha);
    const dia = lunes.getDay();
    const diferencia = dia === 0 ? -6 : 1 - dia;
    lunes.setDate(lunes.getDate() + diferencia);

    return lunes;
}

export function fechaDeSemana(diaNombre, tipoSemana) {
    const hoy = new Date();
    hoy.setHours(12, 0, 0, 0);

    const lunes = lunesDeSemana(hoy);
    if (tipoSemana === 'siguiente') {
        lunes.setDate(lunes.getDate() + 7);
    }

    const fecha = new Date(lunes);
    fecha.setDate(lunes.getDate() + (OFFSET_DIAS[diaNombre] - 1));

    return fecha;
}

export function esFechaValidaSemanaActual(fechaIso) {
    const ahora = new Date();
    const hoyIso = formatearFechaLocalISO(ahora);

    if (fechaIso < hoyIso) {
        return false;
    }

    return !(fechaIso === hoyIso && ahora.getHours() >= 19);
}

export const DIAS_SEMANA = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"];
export const OFFSET_DIAS = {
    'Lunes': 1,
    'Martes': 2,
    'Miércoles': 3,
    'Jueves': 4,
    'Viernes': 5
};
