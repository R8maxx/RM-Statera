/**
 * La hoja de estilos del documento, dentro del editor.
 *
 * **`resources/documentos/documento.css` no se copia: se usa.** Antes el editor
 * llevaba su propia versión escrita a mano con tokens de la interfaz, y dos
 * hojas que describen el mismo documento divergen — es cuestión de semanas. Lo
 * que se ve al escribir tiene que ser literalmente lo mismo que se imprime, y
 * eso sólo lo garantiza una sola hoja.
 *
 * Llega con `?raw`, así que **no pasa por la tubería de CSS de Vite**: no se
 * mezcla con Tailwind ni con `app.css`, y los hex sRGB llegan tal cual.
 *
 * ## Por qué hay que acotarla
 *
 * `documento.css` define `--card`, `--muted`, `--border` y `--texto` en `:root`,
 * que son nombres que la aplicación también usa. Sin acotar, abrir el editor
 * repintaría media interfaz. El aislamiento no es cosmético: es obligatorio.
 *
 * Se acota con `@scope`, que es lo que permite envolver una hoja ajena sin
 * reescribir un solo selector.
 *
 * ## Las cuatro sustituciones, y no hay más
 *
 * 1. `@page { size: A4 landscape }` — fuera. Anidado no es válido, y el tamaño
 *    de la hoja lo pinta el lienzo con las medidas de `GeometriaPagina`.
 * 2. `@media screen { body { … } }` — fuera. Existe sólo para mirar el volcado
 *    de `documentos:generar --html` en un navegador, y aquí sumaría un segundo
 *    juego de márgenes encima del de la hoja.
 * 3. `:root` → `:scope`. Las variables cuelgan de la hoja, no del documento.
 * 4. `body` → `:scope`. Es donde viven la familia, el cuerpo de 8,5 pt, la
 *    interlínea y el color del texto; dentro del ámbito, `body` no casa con
 *    nada y el documento saldría con la tipografía de la aplicación.
 *
 * `tests/Unit/Documento/HojaDelEditorTest.php` afirma que `documento.css` sigue
 * teniendo exactamente un `:root`, un `@page`, un `@media screen` y dos `body`.
 * Si alguien reorganiza la hoja y estas sustituciones dejan de casar, **el
 * editor se despintaría sin ningún error**, que es la peor forma de romperse.
 */
import hojaCruda from '../../documentos/documento.css?raw';

/** La clase de la raíz de ProseMirror, que es también la raíz del ámbito. */
export const RAIZ = 'documento-editor';

const ID = 'statera-hoja-documento';

/**
 * Quita un bloque de nivel superior emparejando llaves.
 *
 * Con una expresión regular no se puede: `@media screen { body { … } }` tiene
 * llaves dentro y `[^}]*` corta en la primera.
 */
function sinBloque(css: string, prefijo: string): string {
    const inicio = css.indexOf(prefijo);

    if (inicio === -1) {
        return css;
    }

    let i = css.indexOf('{', inicio);

    if (i === -1) {
        return css;
    }

    let profundidad = 0;

    for (; i < css.length; i += 1) {
        if (css[i] === '{') {
            profundidad += 1;
        } else if (css[i] === '}') {
            profundidad -= 1;

            if (profundidad === 0) {
                return css.slice(0, inicio) + css.slice(i + 1);
            }
        }
    }

    return css.slice(0, inicio);
}

/** `documento.css` acotada a la raíz del editor. Exportada para poder probarla. */
export function hojaAcotada(css: string = hojaCruda): string {
    const cuerpo = sinBloque(sinBloque(css, '@page'), '@media screen')
        .replace(/^:root(?=\s*\{)/m, ':scope')
        .replace(/^body(?=\s*\{)/m, ':scope');

    return `@scope (.${RAIZ}) {\n${cuerpo}\n\n${complemento()}\n}\n`;
}

/**
 * Lo que la hoja del documento da por puesto y en el navegador no está.
 *
 * En el camino del PDF, `AssetsDocumento` antepone los `@font-face` con las
 * fuentes incrustadas en `data:` y define `--fuente-documento`. Aquí no hacen
 * falta: `app.css` ya sirve las mismas ocho caras desde `resources/fonts/`, así
 * que basta con apuntar las dos variables a las familias de la aplicación.
 *
 * Curiosidad que conviene saber: esto hace que **el editor sea más fiel que el
 * PDF final**, porque la conversión a PDF/A resustituye Instrument Sans por Noto
 * Sans. Es un desvío aceptado a conciencia y está escrito en `CLAUDE.md`.
 */
function complemento(): string {
    return `:scope {
    --fuente-documento: var(--font-sans);
    --fuente-cifra: var(--font-mono);
}`;
}

/**
 * Pone la hoja en el documento. Devuelve la función que la retira.
 *
 * Lleva cuenta de cuántos editores la piden: dos a la vez no deben inyectarla
 * dos veces, y el primero en desmontarse no puede dejar al otro sin estilos.
 */
let usos = 0;

export function montarHojaDocumento(): () => void {
    usos += 1;

    if (document.getElementById(ID) === null) {
        const estilo = document.createElement('style');
        estilo.id = ID;
        estilo.textContent = hojaAcotada();
        document.head.append(estilo);
    }

    return () => {
        usos -= 1;

        if (usos <= 0) {
            document.getElementById(ID)?.remove();
            usos = 0;
        }
    };
}
