function actualizarContadorNotas(idTurno, operacion) {
    const turnoButton = document.querySelector(`.turno-button[data-id-turno="${idTurno}"]`);
    if (!turnoButton) return;

    const contadorDiv = turnoButton.querySelector('.contador');
    const cantidadNotasSpan = contadorDiv?.querySelector('span');

    if (contadorDiv && cantidadNotasSpan) {

        let cantidad = parseInt(cantidadNotasSpan.textContent.trim(), 10);

        if (!isNaN(cantidad)) {

            cantidad = operacion === 'incrementar' ? cantidad + 1 : cantidad - 1;

            if (cantidad <= 0) {
                contadorDiv.remove();
            } else {
                cantidadNotasSpan.textContent = cantidad;
            }
        }
    } else if (operacion === 'incrementar') {

        const nuevoContadorDiv = document.createElement('div');
        nuevoContadorDiv.className = 'contador relative';
        nuevoContadorDiv.innerHTML = `
            <i class="fa-solid fa-comment"></i>
            <span class="absolute inset-0 flex justify-center items-center text-sm text-gray-800 font-bold">1</span>
        `;
        turnoButton.appendChild(nuevoContadorDiv);
    }
}

function crearNotaCero() {
    listaNotas.innerHTML = `
        <div class="text-center text-gray-500 py-6" id="nota-cero">
            Todavía no has registrado ninguna nota para este turno.
        </div>
    `;
}

function crearNotaDiv(nota) {
    const notaDiv = document.createElement('div');
    notaDiv.className = 'nota p-3 flex justify-between items-center gap-5 bg-gray-100 rounded-lg hover:bg-gray-200 transition';
    notaDiv.innerHTML = `
        <div>
            <p class="text-gray-500 text-md">${nota.fecha_realizada}</p>
            <p class="text-gray-700 text-md">${nota.contenido}</p>
        </div>
        <button class="eliminar-nota" data-id-nota="${nota.id}">
            <i class="fa-solid fa-trash text-lg cursor-pointer hover:text-red-500"></i>
        </button>
    `;

    return notaDiv;
}

async function abrirModal(idTurno) {
    try {
        const respuesta = await fetch(`/turnos/${idTurno}/notas`);
        if (!respuesta.ok) throw new Error('Hubo un problema al obtener las notas del turno.');

        const notas = await respuesta.json();
        if (notas.length === 0) {
            crearNotaCero();
        } else {
            listaNotas.innerHTML = ''; // Vaciar
            notas.forEach(nota => {
                listaNotas.appendChild(crearNotaDiv(nota));
            });
        }

        registrarButton.dataset.idTurno = idTurno;
        modalNotas.classList.remove('hidden');

    } catch (error) {
        console.error(error);
        alert(error.message);
        modalNotas.classList.add('hidden');
    }
};

async function registrarNotaTurno(idTurno, contenidoNota) {
    try {
        const respuesta = await fetch(`/turnos/${idTurno}/notas`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({ contenidoNota })
        });
        const data = await respuesta.json();

        if (!respuesta.ok) throw new Error(data.mensaje || 'Error al procesar la solicitud.');

        const notaCero = document.getElementById('nota-cero');
        if (notaCero) notaCero.remove();

        const nota = data.nota;
        listaNotas.appendChild(crearNotaDiv(nota));

        actualizarContadorNotas(idTurno, INCREMENTAR);

        modalAgregarNota.classList.add('hidden');
        contenidoNotaTextArea.value = '';

    } catch (error) {
        console.error(error);
        alert(error.message);
    }
}

async function eliminarNota(idNota) {
    try {
        const respuesta = await fetch(`/notas/${idNota}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            }
        });
        const data = await respuesta.json();

        if (!respuesta.ok) throw new Error(data.mensaje || 'Error al procesar la solicitud.');

    } catch (error) {
        console.error(error);
        alert(error.message);
    }
}

const INCREMENTAR = 'incrementar';
const DECREMENTAR = 'decrementar';

const actividadSelect = document.getElementById('actividad-select');
const anteriorButton = document.getElementById('anterior-button');
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const formulario = document.getElementById('filtros-form');
const horarioSelect = document.getElementById('horario-select');
const semanaInput = document.getElementById('semana-input');
const siguienteButton = document.getElementById('siguiente-button');

const modalNotas = document.getElementById('modal-notas-turno');
const listaNotas = document.getElementById('modal-notas-lista');
const agregarNotaButton = document.getElementById('modal-notas-agregar');
const cerrarNotasButton = modalNotas.querySelectorAll('.modal-notas-cerrar');

const modalAgregarNota = document.getElementById('modal-agregar-nota');
const contenidoNotaTextArea = document.getElementById('contenido-nota-textarea');
const registrarButton = document.getElementById('registrar-button');
const volverButton = document.getElementById('volver-button');

actividadSelect.addEventListener('change', function() {
    formulario.submit();
});

anteriorButton.addEventListener('click', function() {
    semanaInput.value = parseInt(semanaInput.value) - 1;
    formulario.submit();
});

horarioSelect.addEventListener('change', function() {
    formulario.submit();
});

siguienteButton.addEventListener('click', function() {
    semanaInput.value = parseInt(semanaInput.value) + 1;
    formulario.submit();
});

agregarNotaButton.onclick = () => modalAgregarNota.classList.remove('hidden');

cerrarNotasButton.forEach(btn => btn.onclick = () => modalNotas.classList.add('hidden'));

document.querySelectorAll('.turno-button').forEach(btn => {
    btn.addEventListener('click', () => {
        const idTurno = btn.getAttribute('data-id-turno');
        abrirModal(idTurno);
    });
});

modalNotas.addEventListener('click', (e) => {
    if (e.target === modalNotas) modalNotas.classList.add('hidden');
});

listaNotas.addEventListener('click', async function (e) {

    const botonEliminar = e.target.closest('button.eliminar-nota');

    if (!botonEliminar) return;

    if (!confirm("¿Está seguro de que desea eliminar la nota del turno?")) return;

    const idNota = botonEliminar.dataset.idNota;
    await eliminarNota(idNota);

    const notaDiv = botonEliminar.closest('.nota');
    if (notaDiv) notaDiv.remove();

    const notasRestantes = this.querySelectorAll('.nota');
    if (notasRestantes.length === 0) {
        crearNotaCero();
    }

    const idTurno = registrarButton.dataset.idTurno;
    actualizarContadorNotas(idTurno, DECREMENTAR);
});

modalAgregarNota.addEventListener('click', (e) => {
    if (e.target === modalAgregarNota) {
        modalAgregarNota.classList.add('hidden');
        contenidoNotaTextArea.value = '';
    }
});

registrarButton.addEventListener('click', async function() {

    const contenidoNota = contenidoNotaTextArea.value;

    if (contenidoNota.trim() === '') {
        alert('Por favor, ingrese el contenido de la nueva nota para poder registrarla.');
        return;
    }

    await registrarNotaTurno(this.dataset.idTurno, contenidoNota);
});

volverButton.addEventListener('click', function() {
    modalAgregarNota.classList.add('hidden');
    contenidoNotaTextArea.value = '';
});
