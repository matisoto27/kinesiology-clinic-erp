document.addEventListener('alpine:init', () => {
    Alpine.data('formularioPaciente', (esAdultoInicial, viveSoloInicial, contactosInicial) => ({
        esAdulto: esAdultoInicial,
        viveSolo: viveSoloInicial,
        contactos: contactosInicial,

        init() {
            this.$watch('esAdulto', (val) => {
                if (!val) {
                    this.contactos = [];
                    this.viveSolo = true;
                }
            });
        },

        agregarContacto() {
            if (this.contactos.length < 3) {
                this.contactos.push({
                    nombre: '',
                    telefono: '',
                    vinculo: ''
                });
            }
        }
    }))
})
