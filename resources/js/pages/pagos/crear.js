import { esNumeroEntero, habilitarElemento } from "@compartido/general.js";

const actPacSelect = document.getElementById('act-pac-select');
const alertaExceso = document.getElementById('alerta-exceso');
const botonRegistrar = document.getElementById('boton-registrar');
const contenedorDeuda = document.getElementById('contenedor-deuda');
const deudaTexto = document.getElementById('deuda-texto');
const metodoSelect = document.getElementById('metodo-select');
const montoInput = document.getElementById('monto-input');
const montoParaEnviar = document.getElementById('monto-para-enviar');
const profesionalSelect = document.getElementById('profesional-select');
let montoDeudaActual = 0;

function montoEsValido(monto) {
    return !isNaN(monto) && monto > 0;
}

function mostrarContenedorDeuda(confirma) {
    contenedorDeuda.classList.toggle('hidden', !confirma);
}

function montoInputEnAlerta(confirma) {
    montoInput.classList.toggle('border-red-500', confirma);
    montoInput.classList.toggle('focus:ring-red-500', confirma);
    montoInput.classList.toggle('text-red-600', confirma);
    montoInput.classList.toggle('focus:ring-green-500', !confirma);
}

function extraerMontoNumerico(montoStr) {
    if (!montoStr) return 0;

    const limpio = montoStr.replace(/\./g, '').replace(',', '.');
    const transformado = parseFloat(limpio);
    return isNaN(transformado) ? 0 : transformado;
}

function actualizarPagina() {
    const datosValidos = validarDatosFormulario();

    montoParaEnviar.value = datosValidos
        ? extraerMontoNumerico(montoInput.value)
        : '';

    habilitarElemento(botonRegistrar, datosValidos);
}

function validarDatosFormulario() {
    const inscripcionSeleccionada = actPacSelect.value;
    const inscripcionValida = esNumeroEntero(inscripcionSeleccionada);

    const profesionalSeleccionado = profesionalSelect.value;
    const profesionalValido = esNumeroEntero(profesionalSeleccionado);

    const metodoSeleccionado = metodoSelect.value;
    const metodoValido = ['Efectivo', 'Transferencia'].includes(metodoSeleccionado);

    const montoIngresado = extraerMontoNumerico(montoInput.value);
    const montoValido = Number.isFinite(montoIngresado) && montoIngresado > 0 && montoIngresado <= montoDeudaActual;

    return inscripcionValida && profesionalValido && metodoValido && montoValido;
}

actPacSelect.addEventListener('change', function() {
    const opcionSeleccionada = this.options[this.selectedIndex];
    const montoDeuda = parseFloat(opcionSeleccionada.dataset.deuda);

    if (!montoEsValido(montoDeuda)) {
        if (this.value !== '') this.value = '';
        mostrarContenedorDeuda(false);
        habilitarElemento(montoInput, false);
        return;
    }

    const deudaFormateada = new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(montoDeuda);

    montoDeudaActual = montoDeuda;

    deudaTexto.innerText = deudaFormateada;
    mostrarContenedorDeuda(true);

    montoInput.value = '';
    habilitarElemento(montoInput, true);
    actualizarPagina();
});

montoInput.addEventListener('input', function() {
    if (!montoEsValido(montoDeudaActual)) return;

    let valorIngresado = this.value;

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
        // Eliminar ceros a la izquierda y limitar a 6 dígitos (máximo 999.999)
        parteEntera = parseInt(parteEntera, 10).toString().substring(0, 6);

        // Formatear miles con puntos
        parteEntera = parteEntera.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Máximo 2 decimales
    this.value = partes.length > 1
        ? parteEntera + ',' + parteDecimal.substring(0, 2)
        : parteEntera + (valorIngresado.includes(',') ? ',' : '');

    const valorNumerico = extraerMontoNumerico(this.value);

    if (!!valorNumerico && valorNumerico > montoDeudaActual) {
        alertaExceso.innerText = "El monto ingresado no puede superar la deuda total.";
        alertaExceso.classList.remove('hidden');
        montoInputEnAlerta(true);
        habilitarElemento(botonRegistrar, false);
    } else {
        alertaExceso.classList.add('hidden');
        montoInputEnAlerta(false);
        actualizarPagina();
    }
});

actPacSelect.dispatchEvent(new Event('change'));

metodoSelect.addEventListener('change', actualizarPagina);

profesionalSelect.addEventListener('change', actualizarPagina);
