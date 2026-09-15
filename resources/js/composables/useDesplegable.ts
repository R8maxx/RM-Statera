import { ref, watch, type Ref } from 'vue';

/**
 * El estado de un despliegue con la utilidad `.desplegable` de `app.css`.
 *
 * Son dos cosas y no una: **abierto** es lo que el usuario ha decidido, y
 * **asentado** es que la transición ya ha terminado. La segunda existe por el
 * anillo de foco: el `overflow: hidden` que recorta el contenido mientras la
 * rejilla crece también recortaría el anillo del primer y del último campo, que
 * sobresale 4 px. Mientras se mueve hay que recortar; cuando se para, no.
 *
 * Al cerrar se retira de inmediato —hay que volver a recortar antes de que
 * empiece a encoger, o el contenido se saldría—; al abrir se espera al final.
 */
export function useDesplegable(abierta: Ref<boolean>): {
    asentada: Ref<boolean>;
    alTerminarTransicion: (evento: TransitionEvent) => void;
} {
    const asentada = ref(abierta.value);

    watch(abierta, (valor) => {
        if (!valor) {
            asentada.value = false;
        }
    });

    /*
     * Sólo la propiedad que se anima. Un `transitionend` de cualquier
     * descendiente —el color de un botón, el foco de un campo— burbujea hasta
     * aquí y levantaría el recorte a mitad de la apertura.
     */
    function alTerminarTransicion(evento: TransitionEvent): void {
        if (evento.propertyName === 'grid-template-rows') {
            asentada.value = abierta.value;
        }
    }

    return { asentada, alTerminarTransicion };
}
