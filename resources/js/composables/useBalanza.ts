import { ref, type Ref } from 'vue';

/**
 * Los dos gestos que la balanza del acceso hace a petición.
 *
 * El estado vive en el módulo y no en un `provide`, porque quien pide el gesto
 * —`pages/auth/Login.vue`— y quien lo pinta —`BalanzaPixeles`, dentro de
 * `AuthLayout`— están en ramas distintas del árbol y no se conocen. Un `provide`
 * obligaría a que todas las pantallas de acceso supieran que existe una balanza
 * detrás, que es justo el acoplamiento que no interesa: hoy la balanza se puede
 * quitar y ninguna de las cinco pantallas se entera.
 *
 * Es un contador y no un booleano a propósito: dos intentos fallidos seguidos
 * son dos sacudidas, y con una bandera el segundo no dispararía nada.
 */
const sacudidas = ref(0);
const asentamientos = ref(0);

export function useBalanza(): {
    sacudidas: Ref<number>;
    asentamientos: Ref<number>;
    sacudir: () => void;
    asentar: () => void;
} {
    return {
        sacudidas,
        asentamientos,
        /** Se desequilibra una vez y vuelve. Para un intento que no ha valido. */
        sacudir: () => (sacudidas.value += 1),
        /** Se queda plana. Para el instante en que se envían credenciales. */
        asentar: () => (asentamientos.value += 1),
    };
}
