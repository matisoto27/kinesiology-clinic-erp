import { habilitarElemento, mostrarAlerta } from '@compartido/general.js';
import { configurarBuscador } from '@compartido/buscador.js';

function sincronizarHora() {
    actualizarFechaHora();
    const ahora = new Date();
    const msHastaProximoMinuto = (60 - ahora.getSeconds()) * 1000 - ahora.getMilliseconds();

    setTimeout(() => {
        actualizarFechaHora();
        setInterval(actualizarFechaHora, 60000);
    }, msHastaProximoMinuto);
}

function actualizarFechaHora() {
    const ahora = new Date();
    const dia = String(ahora.getDate()).padStart(2, '0');
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const anio = ahora.getFullYear();
    const horas = String(ahora.getHours()).padStart(2, '0');
    const minutos = String(ahora.getMinutes()).padStart(2, '0');

    fecha.textContent = `${dia}/${mes}/${anio}`;
    horaActual.textContent = `${horas}:${minutos}`;
}

function crearLiPaciente(pac, esUltimo) {
    const li = document.createElement('li');

    li.classList.add('p-2', 'cursor-pointer', 'text-left', 'bg-white', 'hover:bg-[#F5D500]', 'text-black');
    if (esUltimo) li.classList.add('rounded-b-md'); // Último paciente se redondean los bordes inferiores
    li.textContent = `${pac.apellido} ${pac.nombre}`;
    li.dataset.idPaciente = pac.id;

    li.addEventListener('click', function() {
        idPacienteSeleccionado.value = parseInt(this.dataset.idPaciente);
        enviarFormulario();
    })

    return li;
}

function enviarFormulario() {
    formulario.submit();
}

const actividadSelect = document.getElementById('actividad-select');
const fecha = document.getElementById('fecha');
const formulario = document.getElementById('filtros-form');
const horaActual = document.getElementById('hora-actual');
const tabla = document.getElementById('turnos-tbody');
const {
    elementos: {
        idSeleccionado: idPacienteSeleccionado,
        quitarButton: quitarPacienteButton,
        input: pacienteInput,
        sugerencias: sugerenciasPaciente
    },
    habilitarBuscador
} = configurarBuscador('paciente', '/buscar-pacientes', crearLiPaciente);

sincronizarHora();

actividadSelect.addEventListener('change', enviarFormulario);

if (quitarPacienteButton) {
    quitarPacienteButton.addEventListener('click', function() {
        idPacienteSeleccionado.value = 0;
        enviarFormulario();
    });
}

tabla.addEventListener('click', async (e) => {
    const boton = e.target.closest('.turno-button');
    if (!boton) return;

    const eleccion = await Swal.fire({
        title: '¿Está seguro de que desea confirmar la asistencia del turno?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar'
    });
    if (!eleccion.isConfirmed) return;

    const url = boton.dataset.url;
    if (!url) return;

    habilitarElemento(boton, false);

    try {
        await apiFetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        boton.classList.replace('bg-[#F5D500]', 'bg-green-300');
        boton.textContent = 'Confirmada';

    } catch (error) {
        habilitarElemento(boton, true);
        console.error(error);
        await mostrarAlerta('error', 'Error al confirmar la asistencia', error.message);
    }
});
