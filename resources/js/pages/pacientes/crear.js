import { mostrarElemento } from '@compartido/general.js';

const modalExito = document.getElementById('modal-exito');
const volverButton = document.getElementById('volver-button');

if (modalExito) {
    modalExito.addEventListener('click', function(event) {
        if (event.target === this) {
            mostrarElemento(this, false);
        }
    });
}

if (volverButton) {
    volverButton.addEventListener('click', function() {
        mostrarElemento(modalExito, false);
    });
}
