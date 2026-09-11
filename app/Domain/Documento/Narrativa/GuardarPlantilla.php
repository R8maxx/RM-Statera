<?php

declare(strict_types=1);

namespace App\Domain\Documento\Narrativa;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\PlantillaSeccion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Guarda los textos base de la organización.
 *
 * Lo que se escribe aquí es el punto de partida de **todos los documentos que se
 * creen a partir de ahora**; los que ya existen conservan el texto con el que se
 * crearon. La interfaz lo avisa, porque sin ese aviso cualquiera daría por hecho
 * que acaba de cambiar su SoA.
 *
 * Restablecer aquí **borra la fila**, y ahí está la diferencia con el documento:
 * una plantilla sin fila resuelve al texto de fábrica de Statera, así que borrar
 * es exactamente «vuelve a lo que traía el producto» — y además hace que una
 * mejora futura de ese texto llegue sola a quien no lo haya tocado.
 */
final readonly class GuardarPlantilla
{
    public function __construct(private MarkdownDocumento $markdown) {}

    /**
     * @param  array<string, string|null>  $textos  Indexados por clave de sección.
     */
    public function __invoke(TipoDocumento $tipo, array $textos, ?User $autor = null): void
    {
        DB::transaction(function () use ($tipo, $textos, $autor): void {
            foreach (SeccionNarrativa::paraTipo($tipo) as $seccion) {
                if (! array_key_exists($seccion->value, $textos)) {
                    continue;
                }

                $contenido = $this->markdown->normalizar($textos[$seccion->value]);

                /*
                 * Si coincide con el texto de fábrica, **no se guarda fila: se
                 * borra**. Y no es una optimización.
                 *
                 * Una fila ausente significa «vale lo que traiga Statera», así
                 * que sin esto bastaría con abrir la pantalla y darle a guardar
                 * —sin tocar nada— para que las nueve secciones quedaran
                 * congeladas y esa organización dejara de recibir cualquier
                 * mejora futura del texto, sin haberlo decidido y sin enterarse.
                 *
                 * Con esto, «no lo he tocado» y «no hay fila» son lo mismo.
                 */
                if ($contenido === TextosDeFabrica::para($tipo, $seccion)) {
                    PlantillaSeccion::query()
                        ->delTipo($tipo)
                        ->where('seccion', $seccion->value)
                        ->delete();

                    continue;
                }

                PlantillaSeccion::query()->updateOrCreate(
                    ['tipo' => $tipo->value, 'seccion' => $seccion->value],
                    [
                        'contenido_md' => $contenido,
                        'actualizado_por_id' => $autor?->id,
                    ],
                );
            }
        });
    }

    /** Vuelve al texto con el que viene Statera. */
    public function restablecer(TipoDocumento $tipo, SeccionNarrativa $seccion): void
    {
        PlantillaSeccion::query()
            ->delTipo($tipo)
            ->where('seccion', $seccion->value)
            ->delete();
    }
}
