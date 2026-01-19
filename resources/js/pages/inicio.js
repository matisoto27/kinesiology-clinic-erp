import { habilitarElemento } from '@compartido/general.js';
import { inicializarSugerenciasListeners } from '@compartido/buscador-pacientes.js';
import { obtenerElementosBuscador } from '@compartido/referencias-dom.js';

function actualizarFechaHora() {
    const ahora = new Date();
    const dia = String(ahora.getDate()).padStart(2, '0');
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const anio = ahora.getFullYear();
    const horas = String(ahora.getHours()).padStart(2, '0');
    const minutos = String(ahora.getMinutes()).padStart(2, '0');

    fecha.textContent = `${dia}/${mes}/${anio}`;
    horaActual.textContent = `${horas}:${minutos}`;
};

function sincronizarHora() {
    actualizarFechaHora();
    const ahora = new Date();
    const msHastaProximoMinuto = (60 - ahora.getSeconds()) * 1000 - ahora.getMilliseconds();

    setTimeout(() => {
        actualizarFechaHora();
        setInterval(actualizarFechaHora, 60000);
    }, msHastaProximoMinuto);
};

function enviarFormulario() {
    formulario.submit();
}

function crearLiPaciente(pac, esUltimo) {

    const li = document.createElement('li');

    li.classList.add('p-2', 'cursor-pointer', 'text-left', 'bg-white', 'hover:bg-[#F5D500]', 'text-black');
    if (esUltimo) li.classList.add('rounded-b-md'); // Último paciente se redondean los bordes inferiores
    li.textContent = `${pac.apellido} ${pac.nombre}`;
    li.dataset.idPaciente = pac.id;

    li.addEventListener('click', function() {
        idPacienteInput.value = parseInt(this.dataset.idPaciente);
        enviarFormulario();
    })

    return li;
};

const actividadSelect = document.getElementById('actividad-select');
const fecha = document.getElementById('fecha');
const formulario = document.getElementById('filtros-form');
const { eliminarButton } = obtenerElementosBuscador();
const horaActual = document.getElementById('hora-actual');
const idPacienteInput = document.getElementById('id-paciente-input');
const tabla = document.getElementById('turnos-tbody');

actividadSelect.addEventListener('change', enviarFormulario);

if (eliminarButton) {
    eliminarButton.addEventListener('click', function() {
        idPacienteInput.value = 0;
        enviarFormulario();
    });
};

tabla.addEventListener('click', async (event) => {
    const boton = event.target.closest('.turno-button');
    if (!boton) return;

    if (!confirm("¿Está seguro de que desea confirmar la asistencia del turno?")) return;

    const url = boton.dataset.url;
    if (!url) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    habilitarElemento(boton, false);

    try {
        const respuesta = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            }
        });

        if (!respuesta.ok) {
            const data = await respuesta.json();
            throw new Error(data.mensaje || 'Error al procesar la solicitud.');
        }

        boton.classList.remove('bg-[#F5D500]');
        boton.classList.add('bg-green-300');
        boton.textContent = 'Confirmada';
    } catch (error) {
        console.error(error);
        alert(error.message);
        habilitarElemento(boton, true);
    }
});

document.addEventListener('DOMContentLoaded', function() {
    inicializarSugerenciasListeners(crearLiPaciente);
    sincronizarHora();
});
