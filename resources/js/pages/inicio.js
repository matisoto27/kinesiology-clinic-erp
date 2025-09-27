let debounceTimeout = null;
let indiceSeleccionado = -1;

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

function limpiarSugerencias() {
    indiceSeleccionado = -1;
    nombreInput.classList.remove('rounded-t-md');
    nombreInput.classList.add('rounded-md');
    sugerencias.innerHTML = '';
    sugerencias.classList.add('hidden');
};

async function obtenerPacientes(nombreIngresado) {
    try {
        const respuesta = await fetch(`/buscar-pacientes?query=${encodeURIComponent(nombreIngresado)}`);
        if (!respuesta.ok) throw new Error('Hubo un problema al obtener los pacientes.');
        return await respuesta.json();
    } catch (error) {
        console.error(error);
        alert(error.message);
        return [];
    }
};

function crearLiPaciente(pac, esUltimo) {
    const li = document.createElement('li');

    li.classList.add('p-2', 'cursor-pointer', 'text-left', 'bg-white', 'hover:bg-[#F5D500]', 'text-black');
    if (esUltimo) li.classList.add('rounded-b-md'); // Último paciente se redondean los bordes inferiores
    li.textContent = `${pac.apellido} ${pac.nombre}`;
    li.dataset.idPaciente = pac.id;

    li.addEventListener('click', function() {
        idPacienteInput.value = parseInt(this.dataset.idPaciente);
        formulario.submit();
    })

    return li;
};

function actualizarSeleccion(pacientes) {
    pacientes.forEach((pac, indice) => {
        if (indice === indiceSeleccionado) {
            pac.classList.add('bg-yellow-400');
        } else {
            pac.classList.remove('bg-yellow-400');
        }
    })
};

const actividadSelect = document.getElementById('actividad-select');
const eliminarButton = document.getElementById('eliminar-button');
const fecha = document.getElementById('fecha');
const formulario = document.getElementById('filtros-form');
const horaActual = document.getElementById('hora-actual');
const idPacienteInput = document.getElementById('id-paciente-input');
const nombreInput = document.getElementById('nombre-input');
const sugerencias = document.getElementById('sugerencias');
const tabla = document.getElementById('turnos-tbody');

actividadSelect.addEventListener('change', function() {
    formulario.submit();
});

if (eliminarButton) {
    eliminarButton.addEventListener('click', function() {
        idPacienteInput.value = 0;
        formulario.submit();
    });
};

nombreInput.addEventListener('input', function() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(async () => {

        let nombreIngresado = this.value.trim();

        if (nombreIngresado.length < 2) {
            limpiarSugerencias();
            return;
        }

        const pacientes = await obtenerPacientes(nombreIngresado);
        if (pacientes.length === 0) {

            limpiarSugerencias();

            const li = document.createElement('li');
            li.classList.add('bg-white', 'cursor-pointer', 'p-2', 'rounded-b-md', 'text-gray-500', 'text-left');
            li.textContent = 'No se encontraron pacientes';
            sugerencias.appendChild(li);

            nombreInput.classList.remove('rounded-md');
            nombreInput.classList.add('rounded-t-md');
            sugerencias.classList.remove('hidden');

            return;
        }

        sugerencias.innerHTML = ''; // Limpiar las sugerencias previas

        nombreInput.classList.remove('rounded-md');
        nombreInput.classList.add('rounded-t-md');
        sugerencias.classList.remove('hidden');

        pacientes.forEach((pac, i) => {
            sugerencias.appendChild(crearLiPaciente(pac, i === pacientes.length - 1));
        })

    }, 80);
});

nombreInput.addEventListener('keydown', function(event) {
    const pacientes = sugerencias.querySelectorAll('li');
    const cantidad = pacientes.length;

    if (cantidad === 0) return;

    switch(event.key) {
        case 'ArrowDown':
        case 'Tab':
            event.preventDefault();
            indiceSeleccionado = (indiceSeleccionado + 1) % cantidad;
            actualizarSeleccion(pacientes);
            break;
        case 'ArrowUp':
            event.preventDefault();
            indiceSeleccionado = (indiceSeleccionado - 1 + cantidad) % cantidad;
            actualizarSeleccion(pacientes);
            break;
        case 'Enter':
            event.preventDefault();
            if (indiceSeleccionado >= 0 && indiceSeleccionado < cantidad) {
                pacientes[indiceSeleccionado].click();
            }
            break;
    }
});

tabla.addEventListener('click', async (event) => {
    const boton = event.target.closest('.turno-button');
    if (!boton) return;

    if (!confirm("¿Está seguro de que desea confirmar la asistencia del turno?")) return;

    const idTurno = boton.dataset?.idTurno;
    if (!idTurno) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    boton.disabled = true;

    try {
        const respuesta = await fetch(`/turnos/${idTurno}/confirmar-asistencia`, {
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
        boton.disabled = false;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    sincronizarHora();
});

document.addEventListener('click', function(event) {
    if (!nombreInput.contains(event.target) && !sugerencias.contains(event.target)) {
        limpiarSugerencias();
    }
});