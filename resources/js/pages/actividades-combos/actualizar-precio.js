import {
    habilitarElemento,
    inputEnAlerta,
    mostrarAlerta,
    mostrarElemento,
    obtenerValor,
    textoADecimal,
    transformarIngresoMonto
} from '@compartido/general.js';

const actComSelect = document.getElementById('actcom-select');
const botonRegistrar = document.getElementById('boton-registrar');
const precioVigenteInput = document.getElementById('precio-vigente-input');
const nuevoPrecioInput = document.getElementById('nuevo-precio-input');
const textoAlerta = document.getElementById('texto-alerta');
const valorParaEnviar = document.getElementById('valor-para-enviar');
let precioVigente = 0;
let ultimoActComValido = actComSelect.value;

function limpiarValores() {
    precioVigenteInput.value = '-';
    actualizarPagina();
}

function actualizarPagina() {
    const valorIngresado = textoADecimal(nuevoPrecioInput.value);
    const valorValido = Number.isFinite(valorIngresado) && valorIngresado > 0;

    const esDuplicado = valorValido && valorIngresado === precioVigente;

    if (valorValido && esDuplicado) {
        textoAlerta.innerText = 'El nuevo precio del combo no puede ser igual al precio vigente.';
        mostrarElemento(textoAlerta, true);
        inputEnAlerta(nuevoPrecioInput, true);
    } else {
        textoAlerta.innerText = '';
        mostrarElemento(textoAlerta, false);
        inputEnAlerta(nuevoPrecioInput, false);
    }

    const datosValidos = obtenerValor(actComSelect) !== null && valorValido && !esDuplicado;

    valorParaEnviar.value = datosValidos ? valorIngresado : '';
    habilitarElemento(botonRegistrar, datosValidos);
}

actComSelect.addEventListener('change', async function() {
    if (obtenerValor(this) === null) {
        precioVigente = 0;
        ultimoActComValido = '';
        limpiarValores();
        return;
    }

    const opcionSeleccionada = this.options[this.selectedIndex];
    const precio = obtenerValor(opcionSeleccionada.dataset.precio, true, false);

    if (precio === null) {
        await mostrarAlerta('error', 'Valor inválido', 'El precio asociado al combo seleccionado no es válido.');
        this.value = ultimoActComValido;
        limpiarValores();
        return;
    }

    const tienePrecio = precio > 0;

    precioVigente = tienePrecio ? precio : 0;
    ultimoActComValido = this.value;

    precioVigenteInput.value = tienePrecio ? precio : 'Sin asignar';
    actualizarPagina();
});

nuevoPrecioInput.addEventListener('input', function() {
    transformarIngresoMonto(this);
    actualizarPagina();
});

document.addEventListener('DOMContentLoaded', function() {
    if (actComSelect.value === '') {
        actualizarPagina();
    } else {
        actComSelect.dispatchEvent(new Event('change'));
    }
});
