import { apiFetch } from "../../../compartido/general";

export async function obtenerPrecio(idActividadCombo) {
    try {
        const precio = await apiFetch(`/actividades-combos/${idActividadCombo}/precio-vigente`);
        return '$' + precio;
    } catch (error) {
        console.log(error);
        return "Error";
    }
}
