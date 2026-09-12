<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoCuerpo;
use Illuminate\Support\Facades\DB;

/**
 * De dónde sale el cuerpo que se va a imprimir.
 *
 * Un documento nace sin cuerpo y lo estrena la primera vez que se genera o se
 * abre en el editor: el esqueleto de `CuerpoDeFabrica` materializado con lo que
 * dice el registro. A partir de ahí el cuerpo es del documento, y volver a
 * generarlo **no lo pisa**: sólo se recalculan los dos bloques que no pueden
 * quedar a merced de quien edita.
 *
 * Ese matiz es el que sostiene toda la entrega. Si generar volviera a
 * materializar el documento entero, editarlo no serviría de nada —el trabajo se
 * perdería en la siguiente generación— y si no recalculara nada, las
 * limitaciones y el control de versiones se quedarían congelados en el estado
 * del día que se creó el documento.
 */
final readonly class ResolverCuerpo
{
    /**
     * El título con el que vuelve un bloque blindado que alguien borró.
     *
     * @var array<string, string>
     */
    private const TITULOS = [
        'limitaciones_sistema' => 'Limitaciones de esta declaración',
        'control_versiones' => 'Control de versiones',
    ];

    public function __construct(
        private MaterializarCuerpo $materializar,
        private HtmlANodos $narrativa,
    ) {}

    /**
     * El cuerpo listo para imprimir, con los bloques de siempre al día.
     *
     * @return array<string, mixed>
     */
    public function paraGenerar(Documento $documento, ContenidoDocumento $contenido): array
    {
        $fila = $this->fila($documento, $contenido);

        return $this->materializar->soloEstos(
            $this->asegurarBlindados($fila->cuerpo),
            $contenido,
            $documento->tipo,
            EsquemaCuerpo::SIEMPRE_RECALCULADOS,
            $fila->estaEditado(),
            GuardarCuerpo::bloquesEditados($fila->cuerpo),
        );
    }

    /**
     * Devuelve al cuerpo los bloques que no se pueden quitar.
     *
     * **Recalcular sólo repone lo que sigue estando.** Un bloque borrado en el
     * editor no tiene dónde recalcularse, así que sin esto bastaba con
     * seleccionar el apartado de limitaciones y pulsar Suprimir para que el PDF
     * entregado dejara de declarar lo que el documento no puede afirmar —y de
     * declarar que se ha editado a mano, que es lo que se declara ahí—. La
     * promesa de que esos bloques no se pueden quitar «desde ninguna parte» la
     * cumple este método, no la buena voluntad del editor.
     *
     * Vuelven al final y con su título, no en el sitio donde estaban: quien los
     * borró no dejó dicho dónde los quería.
     *
     * @param  array<string, mixed>  $cuerpo
     * @return array<string, mixed>
     */
    private function asegurarBlindados(array $cuerpo): array
    {
        $presentes = $this->fuentesPresentes($cuerpo);
        $hijos = is_array($cuerpo['content'] ?? null) ? array_values($cuerpo['content']) : [];

        foreach (self::TITULOS as $fuente => $titulo) {
            if (in_array($fuente, $presentes, true)) {
                continue;
            }

            $hijos[] = Nodo::de('seccion', [], [
                Nodo::encabezado(2, $titulo),
                Nodo::hueco($fuente),
            ]);
        }

        // El pie de portada no es una sección: es la frase de la portada que
        // dice si el documento se ha editado a mano.
        if (! in_array('portada_pie', $presentes, true)) {
            $hijos = $this->conPieDePortada($hijos);
        }

        $cuerpo['content'] = $hijos;

        return $cuerpo;
    }

    /**
     * @param  list<mixed>  $hijos
     * @return list<mixed>
     */
    private function conPieDePortada(array $hijos): array
    {
        foreach ($hijos as $indice => $hijo) {
            if (is_array($hijo) && ($hijo['type'] ?? null) === 'portada') {
                $dentro = is_array($hijo['content'] ?? null) ? array_values($hijo['content']) : [];
                $dentro[] = Nodo::hueco('portada_pie');
                $hijo['content'] = $dentro;
                $hijos[$indice] = $hijo;

                return $hijos;
            }
        }

        // Sin portada tampoco hay dónde ponerlo, así que se estrena una.
        return [Nodo::de('portada', [], [Nodo::hueco('portada_pie')]), ...$hijos];
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @return list<string>
     */
    private function fuentesPresentes(array $nodo): array
    {
        $fuente = $nodo['attrs']['fuente'] ?? null;

        if (is_string($fuente)) {
            return [$fuente];
        }

        $hijos = $nodo['content'] ?? [];

        if (! is_array($hijos)) {
            return [];
        }

        $fuentes = [];

        foreach ($hijos as $hijo) {
            if (is_array($hijo)) {
                $fuentes = [...$fuentes, ...$this->fuentesPresentes($hijo)];
            }
        }

        return $fuentes;
    }

    /**
     * La fila del cuerpo, creándola con el esqueleto de fábrica si no la hay.
     *
     * `firstOrCreate` no vale: el cuerpo de fábrica cuesta materializarlo y no
     * tiene sentido construirlo para tirarlo en la carrera. Se busca primero y
     * se inserta con `insertOrIgnore` para que dos generaciones simultáneas del
     * mismo documento no se peleen por el índice único.
     */
    public function fila(Documento $documento, ContenidoDocumento $contenido): DocumentoCuerpo
    {
        $fila = DocumentoCuerpo::query()->where('documento_id', $documento->id)->first();

        if ($fila instanceof DocumentoCuerpo) {
            return $fila;
        }

        return $this->crear($documento, $contenido);
    }

    /**
     * Lo que la organización ya tenía escrito, como nodos.
     *
     * `ContenidoDocumento::$textos` trae la narrativa **ya resuelta** por la
     * cadena de siempre —documento, plantilla de la organización, fábrica—, así
     * que aquí no se vuelve a decidir de dónde sale cada texto: se convierte lo
     * que haya. Lo que coincida con el texto de fábrica llegará igualmente y
     * dará el mismo resultado, que es justo lo que se quiere.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function narrativaHeredada(ContenidoDocumento $contenido): array
    {
        $nodos = [];

        foreach ($contenido->textos->html as $seccion => $html) {
            $bloques = $this->narrativa->bloques($html);

            if ($bloques !== []) {
                $nodos[$seccion] = $bloques;
            }
        }

        return $nodos;
    }

    /**
     * Estrena el cuerpo de un documento.
     *
     * `cuerpo` y `generado` nacen iguales: no hay nada que declarar todavía
     * porque nadie ha tocado nada. La diferencia entre los dos aparece la
     * primera vez que alguien guarda en el editor, y es exactamente lo que el
     * documento entregado tiene que ser capaz de contar.
     */
    public function crear(Documento $documento, ContenidoDocumento $contenido): DocumentoCuerpo
    {
        $materializado = ($this->materializar)(
            CuerpoDeFabrica::para($documento->tipo, $this->narrativaHeredada($contenido)),
            $contenido,
            $documento->tipo,
        );

        return DB::transaction(function () use ($documento, $materializado): DocumentoCuerpo {
            $fila = DocumentoCuerpo::query()->where('documento_id', $documento->id)->lockForUpdate()->first();

            if ($fila instanceof DocumentoCuerpo) {
                return $fila;
            }

            return DocumentoCuerpo::query()->create([
                'documento_id' => $documento->id,
                'cuerpo' => $materializado,
                'generado' => $materializado,
                'generado_en' => now(),
            ]);
        });
    }
}
