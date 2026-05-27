let desdeActual = false;
let primeraFechaFueSeleccionada = false;
let totalAPagar = 0;
let ultimaActividadValida = '';
let ultimaFrecuenciaValida = '';

export const obtenerDesdeActual = () => desdeActual;
export const actualizarDesdeActual = (valor) => desdeActual = valor;

export const obtenerPrimeraFechaFueSeleccionada = () => primeraFechaFueSeleccionada;
export const actualizarPrimeraFechaFueSeleccionada = (valor) => primeraFechaFueSeleccionada = valor;

export const obtenerUltimaActividadValida = () => ultimaActividadValida;
export const actualizarUltimaActividadValida = (valor) => ultimaActividadValida = valor;

export const obtenerUltimaFrecuenciaValida = () => ultimaFrecuenciaValida;
export const actualizarUltimaFrecuenciaValida = (valor) => ultimaFrecuenciaValida = valor;
