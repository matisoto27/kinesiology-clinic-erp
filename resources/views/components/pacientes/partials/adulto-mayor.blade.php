<div
    class="space-y-5"
    x-data="{
        esAdultoMayor: $wire.entangle('esAdultoMayor'),
        viveSolo: $wire.entangle('viveSolo'),
        viveCon: $wire.entangle('viveCon'),
        contactos: $wire.entangle('contactos'),
        errores: $wire.entangle('erroresJs'),
        error(campo) {
            return this.errores[campo] ?? null;
        },
        alCambiarAdultoMayor() {
            if (!this.esAdultoMayor) {
                this.viveSolo = true;
                this.viveCon = null;
                this.contactos = [];
            }
        },
        alCambiarViveSolo() {
            if (this.viveSolo) {
                this.viveCon = null;
            }
        },
        agregar() {
            if (this.contactos.length >= 3) {
                return;
            }

            this.contactos.push({
                id: null,
                clave: crypto.randomUUID ? crypto.randomUUID() : String(Date.now()),
                nombre: '',
                telefono: '',
                vinculo: '',
            });
        },
        quitar(indice) {
            this.contactos.splice(indice, 1);
        },
    }"
>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="flex items-center gap-1">
        <input
            id="checkbox-adulto-mayor"
            type="checkbox"
            class="checkbox-formulario"
            x-model="esAdultoMayor"
            @change="alCambiarAdultoMayor()"
        >
        <label for="checkbox-adulto-mayor" class="etiqueta-formulario">¿Es adulto mayor?</label>
    </div>

    <div class="space-y-5" x-show="esAdultoMayor" x-cloak>
        <div class="flex items-center gap-1">
            <input
                id="checkbox-vive-solo"
                class="checkbox-formulario"
                type="checkbox"
                x-model="viveSolo"
                @change="alCambiarViveSolo()"
            >
            <label for="checkbox-vive-solo" class="etiqueta-formulario">¿Vive solo?</label>
        </div>

        <div class="columna-campo" x-show="!viveSolo" x-cloak>
            <label for="input-vive-con" class="etiqueta-formulario">¿Con quién vive?</label>
            <input
                id="input-vive-con"
                type="text"
                placeholder="Ejemplo: Juan (esposo), Mariana (hija)"
                class="entrada-simple"
                :class="{ 'border-red-500 border-2': error('viveCon') }"
                x-model="viveCon"
            >
            <span class="mt-1 text-red-500 text-sm" x-show="error('viveCon')" x-text="error('viveCon')"></span>
        </div>

        <template x-for="(contacto, indice) in contactos" :key="contacto.clave">
            <div class="mb-5 pb-5 border-[#F5D500] border-b">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-[#F5D500] text-xl font-medium" x-text="'Contacto de emergencia ' + (indice + 1)"></h3>
                    <button
                        type="button"
                        class="text-red-500 text-md hover:text-red-400"
                        @click="quitar(indice)"
                    >Eliminar</button>
                </div>

                <div class="mb-4 columna-campo">
                    <label class="etiqueta-formulario" :for="'contacto_' + indice + '_nombre'">Nombre</label>
                    <input
                        type="text"
                        placeholder="Ingrese nombre del contacto"
                        class="entrada-simple"
                        :id="'contacto_' + indice + '_nombre'"
                        :class="{ 'border-red-500 border-2': error('contactos.' + indice + '.nombre') }"
                        x-model="contacto.nombre"
                    >
                    <span
                        class="text-red-500 text-sm"
                        x-show="error('contactos.' + indice + '.nombre')"
                        x-text="error('contactos.' + indice + '.nombre')"
                    ></span>
                </div>

                <div class="mb-4 columna-campo">
                    <label class="etiqueta-formulario" :for="'contacto_' + indice + '_telefono'">Teléfono</label>
                    <input
                        type="text"
                        placeholder="Ingrese teléfono del contacto"
                        class="entrada-simple"
                        :id="'contacto_' + indice + '_telefono'"
                        :class="{ 'border-red-500 border-2': error('contactos.' + indice + '.telefono') }"
                        x-model="contacto.telefono"
                    >
                    <span
                        class="text-red-500 text-sm"
                        x-show="error('contactos.' + indice + '.telefono')"
                        x-text="error('contactos.' + indice + '.telefono')"
                    ></span>
                </div>

                <div class="columna-campo">
                    <label class="etiqueta-formulario" :for="'contacto_' + indice + '_vinculo'">Vínculo</label>
                    <select
                        class="entrada-simple"
                        :id="'contacto_' + indice + '_vinculo'"
                        :class="{ 'border-red-500 border-2': error('contactos.' + indice + '.vinculo') }"
                        x-model="contacto.vinculo"
                    >
                        <option value="">¿Qué vínculo tiene con el paciente?</option>
                        <option value="Cónyuge">Cónyuge</option>
                        <option value="Hijo/a">Hijo/a</option>
                        <option value="Hermano/a">Hermano/a</option>
                        <option value="Otro">Otro</option>
                    </select>
                    <span
                        class="text-red-500 text-sm"
                        x-show="error('contactos.' + indice + '.vinculo')"
                        x-text="error('contactos.' + indice + '.vinculo')"
                    ></span>
                </div>
            </div>
        </template>

        <div class="flex justify-center">
            <button
                type="button"
                class="px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white rounded"
                x-show="contactos.length < 3"
                @click="agregar()"
            >Añadir Contacto de Emergencia</button>
            <p class="mt-2 text-red-500 text-sm" x-show="contactos.length >= 3">
                Has alcanzado el máximo de contactos de emergencia.
            </p>
        </div>
    </div>
</div>
