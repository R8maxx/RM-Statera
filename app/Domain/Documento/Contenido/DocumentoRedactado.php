<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\ResolverNarrativa;

/**
 * Un documento que escribe la organización: política, norma o procedimiento.
 *
 * **Es la otra familia de documentos, y no se parece a las declaraciones.** Una
 * Declaración de Aplicabilidad es una consulta sobre `implantaciones` congelada
 * en un PDF; una política no sale de ninguna consulta —la redacta alguien— y por
 * eso esta clase no hereda de `DocumentoCalculado`: no tiene filas que
 * construir, ni tabla que componer, ni correspondencias cruzadas que resolver.
 * Lo único que comparte con ellas es la portada, el historial y las limitaciones,
 * que están en un trait.
 *
 * **Una sola clase para los tres tipos.** Lo que separa una política de una norma
 * no es cómo se genera el documento, es qué dice y a qué nivel de la jerarquía
 * está; en la tubería son idénticos. Duplicar la clase tres veces para cambiar
 * una etiqueta es exactamente cómo se acaba teniendo tres tuberías que divergen.
 *
 * Son los que dan sentido al acuse de lectura: nadie acusa recibo de una SoA, y
 * de la política de seguridad tiene que acusar recibo todo el mundo (cláusula 7.3
 * de ISO y `org.2` del ENS).
 */
final class DocumentoRedactado implements GeneradorDocumento
{
    use Concerns\ArmaContenidoComun;

    public function __construct(
        private readonly ResolverNarrativa $narrativa,
        private readonly MarkdownDocumento $markdown,
    ) {}

    /**
     * El tipo lo pone el documento, no la clase.
     *
     * Es el único generador que sirve a más de un tipo, así que `tipo()` no
     * puede devolver una constante. Devuelve `Politica` como el representante de
     * la familia; quien necesite el tipo exacto lo tiene en el propio documento,
     * que es de donde sale de verdad.
     */
    public function tipo(): TipoDocumento
    {
        return TipoDocumento::Politica;
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento
    {
        return new ContenidoDocumento(
            titulo: $documento->titulo,
            subtitulo: $documento->tipo->etiqueta(),
            portada: [
                ...$this->portadaBase($documento, $version),
                // El alcance del sistema sólo entra si el documento cuelga de
                // uno. Una política de seguridad es de la organización entera y
                // normalmente no cuelga de ninguno.
                'alcance' => $documento->sistema?->alcance_declarado,
            ],

            // Sin cifras y sin filas: no hay nada que contar. `ContenidoDocumento`
            // ya sabe devolver totales a cero, y `MaterializarCuerpo` no llega a
            // pedir ningún bloque de tabla porque el esqueleto no los declara.
            resumen: [],
            filas: [],

            limitaciones: $this->limitacionesBase($version),
            historial: $this->historialDe($documento),
            textos: $this->textosDe($documento),
        );
    }

    /**
     * Los textos que la organización ha redactado para este documento.
     *
     * Resuelve por la cadena documento → plantilla → fábrica, igual que en las
     * declaraciones. Aquí es **todo** el documento y no el envoltorio, que es la
     * diferencia de fondo entre las dos familias.
     */
    private function textosDe(Documento $documento): TextosDocumento
    {
        return TextosDocumento::desdeMarkdown(
            $this->narrativa->paraDocumento($documento),
            $this->markdown,
        );
    }
}
