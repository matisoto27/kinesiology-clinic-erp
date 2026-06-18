import { cantidadSelect, precioInput, diasContainer, checkboxes, inicioContainer, radioButtons, primerTurnoSelect, turnosContainer } from '../componentes/dom-turnos.js';
import { crearOpcionPorDefecto, habilitarElemento } from '../../../compartido/general.js';

export function limpiarFrecuenciaPrecioTurnos() {

    if (cantidadSelect) {
        habilitarElemento(cantidadSelect, false);
        cantidadSelect.innerHTML = crearOpcionPorDefecto('Seleccione una frecuencia');
    }

    if (precioInput) {
        precioInput.value = '$0,00';
    }

    limpiarConfiguracionTurnos();
}

export function limpiarConfiguracionTurnos() {
    ocultarDiasCheckBoxes();
    ocultarSemanasButtons();
    turnosContainer.innerHTML = '';
}

function ocultarDiasCheckBoxes() {

    if (!diasContainer) {
        return;
    }

    diasContainer.classList.add('hidden');
    actualizarDiasCheckBoxes(false, true);
}

export function actualizarDiasCheckBoxes(checked, disabled) {
    checkboxes.forEach(cb => {
        cb.checked = checked;
        cb.disabled = disabled;
    });
}

export function ocultarSemanasButtons() {

    if (!inicioContainer) {
        return;
    }

    inicioContainer.classList.add('hidden');
    radioButtons.forEach(radio => {
        radio.checked = false;
    });

    if (primerTurnoSelect) {
        primerTurnoSelect.innerHTML = crearOpcionPorDefecto('Seleccione una fecha');
    }
}
