<?php

declare(strict_types=1);

namespace App\Domain\Documento\Narrativa;

use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoSeccion;
use App\Domain\Documento\Models\PlantillaSeccion;

/**
 * Qué texto le toca a cada hueco, y de dónde sale.
 *
 * Es la única fuente de verdad de la narrativa, y la cadena tiene tres eslabones:
 *
 *     documento_secciones  →  documento_plantilla_secciones  →  TextosDeFabrica
 *
 * **Gana el primero que EXISTA, incluida la cadena vacía.** Esa distinción es
 * todo el diseño: una fila vacía dice «aquí no va nada, lo he decidido yo» y una
 * fila ausente dice «vale lo que venga de más atrás». Sin ella, borrar un texto
 * lo resucitaría en la siguiente generación, que es el fallo silencioso clásico.
 *
 * Y es lo que hace que **no haga falta ninguna migración de datos**: los
 * documentos que existían antes de todo esto no tienen filas, resuelven hasta el
 * texto de fábrica y su PDF sale idéntico al de siempre.
 */
final class ResolverNarrativa
{
    /**
     * Todos los huecos de un documento, indexados por su clave.
     *
     * Dos consultas, no una por hueco: son diez u once secciones y esto se llama
     * en cada generación.
     *
     * @return array<string, string>
     */
    public function paraDocumento(Documento $documento): array
    {
        /*
         * Se indexa a mano y no con `pluck('contenido_md', 'seccion')`: `seccion`
         * está casteada a enum, así que `pluck` devolvería objetos donde hacen
         * falta claves de texto y el `array_key_exists` de abajo nunca acertaría.
         * El síntoma —el documento ignora sus propios textos— aparece lejos de
         * la causa.
         */
        $propias = [];

        foreach ($documento->secciones()->get() as $fila) {
            $propias[$fila->seccion->value] = $fila->contenido_md;
        }

        $plantilla = $this->paraPlantilla($documento->tipo);

        $textos = [];

        foreach (SeccionNarrativa::paraTipo($documento->tipo) as $seccion) {
            $textos[$seccion->value] = match (true) {
                array_key_exists($seccion->value, $propias) => (string) $propias[$seccion->value],
                default => $plantilla[$seccion->value] ?? '',
            };
        }

        return $textos;
    }

    /**
     * Los huecos de la plantilla de la organización activa, ya con el texto de
     * fábrica por debajo.
     *
     * @return array<string, string>
     */
    public function paraPlantilla(TipoDocumento $tipo): array
    {
        $suyas = [];

        foreach (PlantillaSeccion::query()->delTipo($tipo)->get() as $fila) {
            $suyas[$fila->seccion->value] = $fila->contenido_md;
        }

        $textos = [];

        foreach (SeccionNarrativa::paraTipo($tipo) as $seccion) {
            $textos[$seccion->value] = array_key_exists($seccion->value, $suyas)
                ? (string) $suyas[$seccion->value]
                : TextosDeFabrica::para($tipo, $seccion);
        }

        return $textos;
    }

    /**
     * Lo que diría la plantilla para un hueco, ignorando lo que tenga el
     * documento. Es contra esto contra lo que se compara para decidir si un
     * texto está «retocado», y es a esto a lo que vuelve «Restablecer».
     */
    public function desdeLaPlantilla(TipoDocumento $tipo, SeccionNarrativa $seccion): string
    {
        $suya = PlantillaSeccion::query()
            ->delTipo($tipo)
            ->where('seccion', $seccion->value)
            ->value('contenido_md');

        return $suya ?? TextosDeFabrica::para($tipo, $seccion);
    }

    /**
     * Las filas propias del documento, indexadas por sección.
     *
     * La interfaz las necesita enteras —no sólo el texto— para poder pintar el
     * badge de origen y decidir si ofrece «Restablecer».
     *
     * @return array<string, DocumentoSeccion>
     */
    public function filasDe(Documento $documento): array
    {
        return $documento->secciones()
            ->get()
            ->keyBy(fn (DocumentoSeccion $fila): string => $fila->seccion->value)
            ->all();
    }
}
