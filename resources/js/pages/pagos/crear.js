import {
    habilitarElemento,
    inputEnAlerta,
    mostrarAlerta,
    mostrarElemento,
    obtenerValor,
    textoADecimal,
    transformarIngresoMonto
} from '@compartido/general.js';

const actPacSelect = document.getElementById('act-pac-select');
const botonRegistrar = document.getElementById('boton-registrar');
const contenedorDeuda = document.getElementById('contenedor-deuda');
const deudaTexto = document.getElementById('deuda-texto');
const metodoSelect = document.getElementById('metodo-select');
const montoInput = document.getElementById('monto-input');
const montoParaEnviar = document.getElementById('monto-para-enviar');
const profesionalSelect = document.getElementById('profesional-select');
const textoAlerta = document.getElementById('texto-alerta');
let montoDeudaActual = 0;
let ultimoActPacValido = actPacSelect.value;

function limpiarValores() {
    mostrarElemento(contenedorDeuda, false);
    deudaTexto.innerText = '';
    montoInput.value = '';
    habilitarElemento(montoInput, false);
    actualizarPagina();
}

function actualizarPagina() {
    const montoDecimal = textoADecimal(montoInput.value);
    const montoValido = Number.isFinite(montoDecimal) && montoDecimal > 0;

    const esMayor = montoValido && montoDecimal > montoDeudaActual;

    if (montoValido && esMayor) {
        textoAlerta.innerText = 'El monto ingresado no puede superar la deuda total.';
        mostrarElemento(textoAlerta, true);
        inputEnAlerta(montoInput, true);
    } else {
        textoAlerta.innerText = '';
        mostrarElemento(textoAlerta, false);
        inputEnAlerta(montoInput, false);
    }

    const datosValidos = obtenerValor(actPacSelect) !== null && obtenerValor(profesionalSelect) !== null && metodoSelect.value !== '' && montoValido && !esMayor;

    montoParaEnviar.value = datosValidos ? montoDecimal : '';
    habilitarElemento(botonRegistrar, datosValidos);
}

actPacSelect.addEventListener('change', async function(event) {
    if (obtenerValor(this) === null) {
        montoDeudaActual = 0;
        ultimoActPacValido = '';
        limpiarValores();
        return;
    }

    const opcionSeleccionada = this.options[this.selectedIndex];
    const montoDeuda = obtenerValor(opcionSeleccionada.dataset.deuda, false, false);

    if (montoDeuda === null) {
        await mostrarAlerta('error', 'Valor inválido', 'El monto de la deuda asociado a la inscripción seleccionada no es válido.');
        this.value = ultimoActPacValido;
        limpiarValores();
        return;
    }

    const deudaFormateada = new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(montoDeuda);

    montoDeudaActual = montoDeuda;
    ultimoActPacValido = this.value;

    deudaTexto.innerText = deudaFormateada;
    mostrarElemento(contenedorDeuda, true);

    if (event.isTrusted) {
        montoInput.value = '';
    }
    habilitarElemento(montoInput, true);

    actualizarPagina();
});

montoInput.addEventListener('input', function() {
    transformarIngresoMonto(this);
    actualizarPagina();
});

metodoSelect.addEventListener('change', actualizarPagina);

profesionalSelect.addEventListener('change', actualizarPagina);

document.addEventListener('DOMContentLoaded', function() {
    if (actPacSelect.value === '') {
        actualizarPagina();
    } else {
        actPacSelect.dispatchEvent(new Event('change'));
    }    
});
