<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Documento\Narrativa\MarkdownDocumento;

/**
 * Todo lo que la plantilla necesita para pintarse, y nada más.
 *
 * **La plantilla recibe esto, nunca modelos de Eloquent.** No es purismo: lo que
 * hay aquí es literalmente lo que se pinta *y* lo que se congela en
 * `documento_versiones.instantanea`. Si la Blade pudiera navegar relaciones,
 * el PDF podría contener datos que la instantánea no recoge, y entonces la
 * instantánea dejaría de demostrar lo que existe para demostrar.
 *
 * @phpstan-type Totales array{requisitos: int, excluidos: int, implantados: int}
 */
final readonly class ContenidoDocumento
{
    /**
     * @param  array<string, mixed>  $portada  Organización, sistema, alcance, versión, fecha, clasificación.
     * @param  array<string, mixed>  $resumen  Las cifras, cada una con su denominador.
     * @param  list<FilaRequisito>  $filas  En el orden en que se imprimen.
     * @param  list<string>  $limitaciones  Lo que este documento NO puede decir hoy, y por qué.
     * @param  list<array<string, mixed>>  $historial  Versiones anteriores con su huella.
     * @param  array<string, mixed>  $extras  Lo propio de cada tipo: la derivación de la categoría, las notas del Anexo II.
     * @param  TextosDocumento  $textos  Lo que ha redactado la organización.
     */
    public function __construct(
        public string $titulo,
        public string $subtitulo,
        public array $portada,
        public array $resumen,
        public array $filas,
        public array $limitaciones = [],
        public array $historial = [],
        public array $extras = [],
        public TextosDocumento $textos = new TextosDocumento,
    ) {}

    /**
     * Rehidrata el contenido desde la instantánea de una versión.
     *
     * Con esto la instantánea deja de ser sólo un archivo y pasa a ser un
     * documento reconstruible. Es lo que permite generar el `.docx` de una
     * versión emitida **sin volver a consultar la base**: si se consultara, el
     * Word de una versión de marzo enseñaría los datos de octubre y
     * contradiría al PDF que lo acompaña, con la huella de ese PDF impresa
     * dentro. Y es lo que mañana habilita una pantalla de diff entre la v3 y la
     * v4.
     *
     * @param  array<string, mixed>  $instantanea
     */
    public static function desdeInstantanea(array $instantanea, MarkdownDocumento $markdown): self
    {
        $array = static function (string $clave) use ($instantanea): array {
            $valor = $instantanea[$clave] ?? [];

            return is_array($valor) ? $valor : [];
        };

        return new self(
            titulo: is_string($instantanea['titulo'] ?? null) ? $instantanea['titulo'] : '',
            subtitulo: is_string($instantanea['subtitulo'] ?? null) ? $instantanea['subtitulo'] : '',
            portada: $array('portada'),
            resumen: $array('resumen'),
            filas: array_values(array_map(
                static fn (mixed $fila): FilaRequisito => FilaRequisito::desdeArray(is_array($fila) ? $fila : []),
                $array('filas'),
            )),
            limitaciones: array_values(array_filter($array('limitaciones'), 'is_string')),
            historial: array_values(array_filter($array('historial'), 'is_array')),
            extras: $array('extras'),
            textos: TextosDocumento::desdeArray($array('textos'), $markdown),
        );
    }

    /**
     * Escapa el texto y le da el realce mínimo: negrita y código.
     *
     * Las limitaciones y las notas del Anexo II llevan una o dos palabras
     * destacadas —«exige de más, **nunca de menos**»— y códigos de medida entre
     * acentos graves. Meterlas como HTML crudo en una cadena que también
     * contiene esos códigos sería abrir la puerta a inyectar marcado desde un
     * campo de la base, así que **se escapa primero y se realza después**, en
     * ese orden.
     *
     * Los acentos graves se convertían en nada y salían IMPRESOS en el PDF: el
     * documento que se le entregaba al auditor decía «(`op.acc.1`)» con las
     * comillas dentro. Ahora van con la misma clase monoespaciada que el resto
     * de códigos del documento.
     *
     * Esto es sólo para literales escritos en PHP. El texto que redacta la
     * organización pasa por `MarkdownDocumento`, que es un parser de verdad.
     */
    public static function realce(string $texto): string
    {
        $realzado = (string) preg_replace(
            '/\*\*(.+?)\*\*/u',
            '<strong>$1</strong>',
            e($texto),
        );

        return (string) preg_replace(
            '/`([^`]+)`/u',
            '<span class="cifra">$1</span>',
            $realzado,
        );
    }

    /**
     * Las filas agrupadas por su epígrafe, conservando el orden de llegada.
     *
     * El orden lo fija el generador —que es quien sabe que `op.acc.10` va
     * después de `op.acc.2` aunque el texto diga lo contrario—, así que aquí no
     * se reordena nada.
     *
     * @return array<string, list<FilaRequisito>>
     */
    public function filasPorGrupo(): array
    {
        $grupos = [];

        foreach ($this->filas as $fila) {
            $grupos[$fila->grupo][] = $fila;
        }

        return $grupos;
    }

    /** @return list<FilaRequisito> */
    public function excluidas(): array
    {
        return array_values(array_filter(
            $this->filas,
            static fn (FilaRequisito $fila): bool => ! $fila->aplica,
        ));
    }

    /**
     * Lo que se guarda en `documento_versiones.instantanea`.
     *
     * @return array<string, mixed>
     */
    public function paraInstantanea(): array
    {
        return [
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'portada' => $this->portada,
            'resumen' => $this->resumen,
            'limitaciones' => $this->limitaciones,
            'extras' => $this->extras,
            // El MARKDOWN, no el HTML: es lo diffeable, y es lo que contesta
            // «¿qué frase cambió entre la v3 y la v4?».
            'textos' => $this->textos->markdown,
            'filas' => array_map(
                static fn (FilaRequisito $fila): array => (array) $fila,
                $this->filas,
            ),
        ];
    }

    /**
     * Los tres recuentos que se denormalizan en la fila de la versión, para que
     * la tabla no tenga que abrir el jsonb.
     *
     * @return array{requisitos: int, excluidos: int, implantados: int}
     */
    public function totales(): array
    {
        return [
            'requisitos' => count($this->filas),
            'excluidos' => count($this->excluidas()),
            'implantados' => count(array_filter(
                $this->filas,
                static fn (FilaRequisito $fila): bool => $fila->estado === 'implantado',
            )),
        ];
    }
}
