import { configurarBuscador, limpiarSugerencias } from '@compartido/buscador.js';
import { mostrarElemento, obtenerValor } from '@compartido/general.js';

function crearLiObra(obra, esUltima) {
    const li = document.createElement('li');
    const idObra = obra.id;
    const esObraPaciente = idObraPaciente !== null && idObra === idObraPaciente;

    li.classList.add('p-2', 'text-left', 'bg-white');

    if (esObraPaciente) {
        li.classList.add('pointer-events-none', 'text-gray-500');
        li.textContent = `${obra.nombre} (Afiliación vigente del paciente)`;
    } else {
        li.classList.add('cursor-pointer', 'hover:bg-[#F5D500]', 'text-black');
        li.textContent = obra.nombre;
    }

    if (esUltima) li.classList.add('rounded-b-md');
    li.dataset.idObra = idObra;

    return li;
}

function crearLiPaciente(paciente, esUltimo) {
    const li = document.createElement('li');

    li.classList.add('p-2', 'cursor-pointer', 'text-left', 'bg-white', 'hover:bg-[#F5D500]', 'text-black');
    if (esUltimo) li.classList.add('rounded-b-md');
    li.textContent = `${paciente.apellido} ${paciente.nombre}`;
    li.dataset.idPaciente = paciente.id;
    li.dataset.idObra = paciente.afiliacion_vigente?.id_obra_social ?? 0;

    return li;
}

const botonRegistrar = document.getElementById('boton-registrar');
const {
    elementos: {
        idSeleccionado: idObraSeleccionada,
        quitarButton: quitarObraButton,
        input: obraInput,
        sugerencias: sugerenciasObra
    },
    habilitarBuscador: habilitarBuscadorObra
} = configurarBuscador('obra-social', '/buscar-obras-sociales', crearLiObra);
const {
    elementos: {
        idSeleccionado: idPacienteSeleccionado,
        quitarButton: quitarPacienteButton,
        input: pacienteInput,
        sugerencias: sugerenciasPaciente
    },
    habilitarBuscador: habilitarBuscadorPaciente
} = configurarBuscador('paciente', '/buscar-pacientes?incluir_obra=true', crearLiPaciente);

let idObraPaciente = null;

sugerenciasObra.addEventListener('click', async function(e) {
    const elementoClickeado = e.target.closest('li');
    if (!elementoClickeado) return;

    const idObra = obtenerValor(elementoClickeado.dataset.idObra);
    if (idObra === null) return;

    idObraSeleccionada.value = idObra;
    obraInput.value = elementoClickeado.textContent;
    habilitarBuscadorObra(false);
    limpiarSugerencias(this);

    botonRegistrar.disabled = false;
});

sugerenciasPaciente.addEventListener('click', function(e) {
    const elementoClickeado = e.target.closest('li');
    if (!elementoClickeado) return;

    const idPaciente = obtenerValor(elementoClickeado.dataset.idPaciente);
    if (idPaciente === null) return;

    idObraPaciente = obtenerValor(elementoClickeado.dataset.idObra);

    idPacienteSeleccionado.value = idPaciente;
    pacienteInput.value = elementoClickeado.textContent;
    habilitarBuscadorPaciente(false);
    limpiarSugerencias(this);

    habilitarBuscadorObra(true);
});

quitarObraButton.addEventListener('click', function() {
    idObraSeleccionada.value = '';
    habilitarBuscadorObra(true);

    botonRegistrar.disabled = true;
});

quitarPacienteButton.addEventListener('click', function() {
    idObraPaciente = null;

    idPacienteSeleccionado.value = '';
    habilitarBuscadorPaciente(true);

    idObraSeleccionada.value = '';
    obraInput.value = '';
    habilitarBuscadorObra(false);
    mostrarElemento(quitarObraButton, false);

    botonRegistrar.disabled = true;
});
