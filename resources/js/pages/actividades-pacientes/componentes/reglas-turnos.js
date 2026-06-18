import { checkboxes, inicioContainer } from '../componentes/dom-turnos.js';
import { estado } from './estado-formulario.js';

export function frecuenciaAlcanzada() {
    return Array.from(checkboxes)
        .filter(cb => cb.checked)
        .length === estado.frecuenciaSemanal;
}

export function obtenerSemanaSeleccionada() {
    return inicioContainer?.querySelector('input[name="inicio"]:checked') ?? null;
}

export function tieneSemanaSeleccionada() {
    return Boolean(obtenerSemanaSeleccionada());
}
