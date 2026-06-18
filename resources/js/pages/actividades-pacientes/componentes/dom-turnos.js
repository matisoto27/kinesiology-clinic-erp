export const formulario = document.getElementById('formulario');

export const actividadSelect = document.getElementById('actividad-select');
export const cantidadSelect = document.getElementById('cantidad-select');
export const precioInput = document.getElementById('precio-input');
export const frecuenciaSelect = document.getElementById('frecuencia-select');
export const mesSelect = document.getElementById('mes-select');
export const diaSelect = document.getElementById('dia-select');
export const turnosCheckbox = document.getElementById('turnos-checkbox');

export const diasContainer = document.getElementById('dias-container');
export const checkboxes = diasContainer?.querySelectorAll('input[type="checkbox"]') ?? [];

export const inicioContainer = document.getElementById('inicio-container');
export const radioButtons = inicioContainer?.querySelectorAll('input[name="inicio"]') ?? [];
export const primerTurnoSelect = document.getElementById('primer-turno-select');

export const turnosContainer = document.getElementById('turnos-container');
