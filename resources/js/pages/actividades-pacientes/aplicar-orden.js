import {
    actualizarDiasDelMes,
    agregarOpcion,
    crearOpcionPorDefecto,
    habilitarSelect,
    mostrarAlerta,
    obtenerValorEntero
} from '@compartido/general.js';

function actualizarSesionesAFavor() {
    const cantidadSeleccionada = obtenerValorEntero(cantidadSelect);
    const datosLocalesCargados = typeof cantSesionesLocal === 'number' && typeof sesionesAFavorLocal === 'number';

    if (cantidadSeleccionada === null || !datosLocalesCargados) {
        sesionesInput.value = '-';
        limpiarAlerta();
    } else {
        const sumatoria = cantidadSeleccionada - cantSesionesLocal + sesionesAFavorLocal;

        if (sumatoria >= 0) {
            sesionesInput.value = sumatoria;
            limpiarAlerta();
        } else {
            sesionesInput.value = 0;
            const restantes = Math.abs(sumatoria);
            textoInsuficiente.innerHTML = `
                <span class="font-medium">La orden médica ingresada no alcanza a cubrir la cantidad total de sesiones.</span>
                <span class="block font-semibold italic">
                    (El paciente deberá pagar ${restantes} ${restantes === 1 ? 'sesión restante' : 'sesiones restantes'})
                </span>
            `;
            contenedorAlerta.classList.remove('hidden');
        }
    }

    const mesSeleccionado = obtenerValorEntero(mesSelect);
    const diaSeleccionado = obtenerValorEntero(diaSelect);

    const datosValidos = cantidadSeleccionada !== null && datosLocalesCargados && mesSeleccionado !== null && diaSeleccionado !== null;

    if (datosValidos) {
        botonRegistrar.disabled = false;
        botonRegistrar.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        botonRegistrar.disabled = true;
        botonRegistrar.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

function limpiarAlerta() {
    textoInsuficiente.textContent = '';
    contenedorAlerta.classList.add('hidden');
}

const actPacSelect = document.getElementById('act-pac-select');
const botonRegistrar = document.getElementById('boton-registrar');
const cantidadSelect = document.getElementById('cantidad-select');
const contenedorAlerta = document.getElementById('contenedor-alerta');
const diaSelect = document.getElementById('dia-select');
const mesSelect = document.getElementById('mes-select');
const sesionesInput = document.getElementById('sesiones-input');
const textoInsuficiente = document.getElementById('texto-insuficiente');

let cantSesionesLocal = null;
let sesionesAFavorLocal = null;
let ultimaInscripcionValida = '';

actPacSelect.addEventListener('change', async function() {
    const inscripcionSeleccionada = obtenerValorEntero(this);
    if (inscripcionSeleccionada === null) return;

    const opcionSeleccionada = actPacSelect.options[actPacSelect.selectedIndex];
    const cantSesiones = obtenerValorEntero(opcionSeleccionada.dataset.cantSesiones);
    const sesionesAFavor = obtenerValorEntero(opcionSeleccionada.dataset.sesionesAFavor, true);

    if (cantSesiones === null || sesionesAFavor === null) {
        await mostrarAlerta('error', 'Valores inválidos', 'Los valores asociados a la inscripción seleccionada no son válidos.');
        this.value = ultimaInscripcionValida;
        return;
    }

    cantSesionesLocal = cantSesiones;
    sesionesAFavorLocal = sesionesAFavor;
    ultimaInscripcionValida = inscripcionSeleccionada;

    actualizarSesionesAFavor();
});

cantidadSelect.addEventListener('change', actualizarSesionesAFavor);

diaSelect.addEventListener('change', actualizarSesionesAFavor);

mesSelect.addEventListener('change', function() {
    actualizarDiasDelMes(this, diaSelect);
    actualizarSesionesAFavor();
});
