<?php

declare(strict_types=1);

namespace App\Domain\Documento\Narrativa;

use App\Domain\Documento\Enums\OrigenTexto;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoSeccion;
use Illuminate\Support\Facades\DB;

/**
 * Guarda los textos que alguien ha redactado para un documento.
 *
 * **El origen se calcula, no se declara.** Al guardar se compara cada texto con
 * lo que diría la plantilla: si coincide es `plantilla`, si no es `propio`. Así
 * el badge de la interfaz dice la verdad aunque alguien pegue exactamente el
 * texto de la plantilla, y «Restablecer» sólo se ofrece cuando hay algo que
 * restablecer.
 *
 * Todo esto afecta a la **siguiente** generación. Las versiones ya emitidas son
 * inmutables —lo impone un trigger— y llevan su narrativa congelada dentro.
 */
final readonly class GuardarNarrativa
{
    public function __construct(
        private ResolverNarrativa $resolver,
        private MarkdownDocumento $markdown,
    ) {}

    /**
     * @param  array<string, string|null>  $textos  Indexados por clave de sección.
     */
    public function __invoke(Documento $documento, array $textos): void
    {
        DB::transaction(function () use ($documento, $textos): void {
            foreach (SeccionNarrativa::paraTipo($documento->tipo) as $seccion) {
                if (! array_key_exists($seccion->value, $textos)) {
                    continue;
                }

                $contenido = $this->markdown->normalizar($textos[$seccion->value]);
                $deLaPlantilla = $this->resolver->desdeLaPlantilla($documento->tipo, $seccion);

                DocumentoSeccion::query()->updateOrCreate(
                    ['documento_id' => $documento->id, 'seccion' => $seccion->value],
                    [
                        'contenido_md' => $contenido,
                        'origen' => $contenido === $deLaPlantilla
                            ? OrigenTexto::Plantilla->value
                            : OrigenTexto::Propio->value,
                    ],
                );
            }
        });
    }

    /**
     * Devuelve un hueco al texto de la plantilla.
     *
     * Se reescribe la fila en vez de borrarla: una fila ausente resolvería a la
     * plantilla igual, pero dejaría de distinguirse de «nunca se materializó», y
     * el día que la plantilla cambie ese documento se movería con ella. Un
     * documento no se mueve.
     */
    public function restablecer(Documento $documento, SeccionNarrativa $seccion): void
    {
        DocumentoSeccion::query()->updateOrCreate(
            ['documento_id' => $documento->id, 'seccion' => $seccion->value],
            [
                'contenido_md' => $this->resolver->desdeLaPlantilla($documento->tipo, $seccion),
                'origen' => OrigenTexto::Plantilla->value,
            ],
        );
    }
}
