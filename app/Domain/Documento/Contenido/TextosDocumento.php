<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Narrativa\MarkdownDocumento;

/**
 * Los textos que la organización ha redactado, listos para pintarse.
 *
 * Guarda **las dos formas a propósito**: el Markdown es lo que se congela en
 * `documento_versiones.instantanea` —es lo diffeable, y es lo que contesta «¿qué
 * frase cambió entre la v3 y la v4?»— y el HTML es lo que consume la plantilla.
 * El HTML no se almacena nunca: es una función determinista del Markdown, y el
 * PDF entregado ya está guardado, así que un cambio futuro del renderizador no
 * altera ningún documento emitido.
 *
 * Se renderiza al construir y no al pintar para que la plantilla no necesite
 * conocer al renderizador. Sale barato: casi todos los huecos están vacíos y
 * `MarkdownDocumento::aHtml()` los contesta sin montar ningún árbol.
 */
final readonly class TextosDocumento
{
    /**
     * @param  array<string, string>  $markdown  Lo que se guarda y se congela.
     * @param  array<string, string>  $html  Lo que se pinta.
     */
    public function __construct(
        public array $markdown = [],
        public array $html = [],
    ) {}

    /**
     * @param  array<string, string>  $markdown
     */
    public static function desdeMarkdown(array $markdown, MarkdownDocumento $renderizador): self
    {
        $html = [];

        foreach ($markdown as $clave => $texto) {
            $html[$clave] = $renderizador->aHtml($texto);
        }

        return new self($markdown, $html);
    }

    /**
     * Rehidrata desde la instantánea de una versión emitida.
     *
     * Es lo que permite que el `.docx` de la v3 diga lo que decía la v3 y no lo
     * que diga hoy la base de datos.
     *
     * @param  array<string, mixed>  $instantanea
     */
    public static function desdeArray(array $instantanea, MarkdownDocumento $renderizador): self
    {
        $markdown = [];

        foreach ($instantanea as $clave => $texto) {
            // Lo que se rehidrata viene de un jsonb: puede traer cualquier cosa
            // si alguien tocó la fila a mano. Lo que no sea texto, fuera.
            if (is_string($texto)) {
                $markdown[$clave] = $texto;
            }
        }

        return self::desdeMarkdown($markdown, $renderizador);
    }

    /**
     * Las secciones que abren el documento, en orden.
     *
     * Viven aquí y no en la plantilla para que la Blade no tenga que nombrar el
     * enum: con el nombre completo, el `@foreach` ocupaba tres líneas y no se
     * leía. Y el orden es parte del documento, así que es cosa del dominio.
     *
     * @return list<SeccionNarrativa>
     */
    public function aperturas(): array
    {
        return [
            SeccionNarrativa::Introduccion,
            SeccionNarrativa::ObjetoYAlcance,
            SeccionNarrativa::Metodologia,
        ];
    }

    /**
     * Las que lo cierran, antes de las limitaciones.
     *
     * La aprobación va la última a propósito: es lo que se firma, y va justo
     * antes de la sección que aclara que Statera no valida esa firma.
     *
     * @return list<SeccionNarrativa>
     */
    public function cierres(): array
    {
        return [
            SeccionNarrativa::Conclusiones,
            SeccionNarrativa::Aprobacion,
        ];
    }

    /** El HTML de un hueco, o cadena vacía. */
    public function html(SeccionNarrativa|string $seccion): string
    {
        $clave = $seccion instanceof SeccionNarrativa ? $seccion->value : $seccion;

        return $this->html[$clave] ?? '';
    }

    /**
     * Si hay algo que pintar.
     *
     * Lo usa la plantilla para no imprimir una sección con su `<h2>` y nada
     * debajo, que se lee como un documento roto.
     */
    public function tiene(SeccionNarrativa|string $seccion): bool
    {
        return $this->html($seccion) !== '';
    }
}
