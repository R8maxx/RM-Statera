<?php

declare(strict_types=1);

namespace App\Domain\Documento\Narrativa\Reglas;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * El texto de un documento se escribe en Markdown, no en HTML.
 *
 * **Rechaza en vez de escapar en silencio**, y ésa es la decisión. Escapar
 * dejaría un `<b>hola</b>` impreso tal cual en el PDF que se le entrega al
 * auditor, y quien lo escribió no sabría de dónde ha salido ni cómo quitarlo.
 * Un error de validación en el momento de guardar se entiende; un documento con
 * etiquetas visibles dentro, no.
 *
 * El editor nunca produce HTML —el esquema de ProseMirror no lo admite—, así que
 * esto sólo salta al pegar desde otra parte o al llamar a la ruta a mano. Que
 * salte poco no lo hace prescindible: es la única superficie de inyección del
 * módulo, y detrás hay un PDF que alguien firma.
 */
final class SinHtml implements ValidationRule
{
    /**
     * Se exige que PAREZCA una etiqueta entera: `<`, nombre, y su `>` de cierre.
     *
     * Con un patrón más laxo —`<` seguido de letra— caía prosa perfectamente
     * legítima: «el riesgo residual < bajo» o «a < b». Un `<` suelto no es HTML
     * y no lo interpreta ningún navegador, así que pasa.
     */
    private const ETIQUETA = '/<\/?[a-z][a-z0-9]*(\s[^<>]*)?\/?>/i';

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (preg_match(self::ETIQUETA, $value) === 1) {
            $fail('El texto se guarda en Markdown y no admite etiquetas HTML. Usa la barra de herramientas para dar formato.');
        }
    }
}
