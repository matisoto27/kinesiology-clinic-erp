const modalExito = document.getElementById('modal-exito');
const volverButton = document.getElementById('volver-button');

if (modalExito) {
    modalExito.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
}

if (volverButton) {
    volverButton.addEventListener('click', function() {
        modalExito.classList.add('hidden');
    });
}
