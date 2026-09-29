<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Adjunto\Models\Adjunto;
use App\Domain\Adjunto\SubirAdjunto;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

/**
 * El diploma de quien asistió a una sesión.
 *
 * **Es un adjunto y no una evidencia**, y la decisión es de protección de datos:
 * un diploma lleva el nombre —y a veces el documento— de la persona, y el bucket
 * de evidencias tiene Object Lock en modo compliance, así que ahí no se podría
 * borrar nunca, ni al suprimir a la persona. La prueba de la medida sigue siendo
 * la hoja de firmas de la sesión.
 *
 * **Sin tabla ni columna propia**: el diploma es el adjunto que cuelga a la vez
 * de la sesión y de la persona. Las dos pivotes ya existen, y una columna
 * `diploma_id` al lado diría lo mismo en un segundo sitio que puede discrepar.
 * Por eso se sube colgado de los dos anfitriones en la misma llamada, y por eso
 * sale en la ficha de la persona sin hacer nada más.
 */
final readonly class DiplomasDeFormacion
{
    public function __construct(private SubirAdjunto $subir) {}

    public function subir(AccionFormativa $accion, Persona $persona, UploadedFile $fichero, ?User $subidoPor = null): Adjunto
    {
        return ($this->subir)(
            $accion,
            $fichero,
            "Diploma {$accion->codigo} · {$persona->nombre}",
            null,
            $subidoPor,
            [$persona],
        );
    }

    /**
     * Los diplomas de una sesión, por persona.
     *
     * @return array<int, list<Adjunto>> persona_id => sus diplomas en esta sesión
     */
    public function deSesion(AccionFormativa $accion): array
    {
        return $this->agrupar(
            Adjunto::query()
                ->join('accion_formativa_adjunto as aa', 'aa.adjunto_id', '=', 'adjuntos.id')
                ->join('persona_adjunto as pa', 'pa.adjunto_id', '=', 'adjuntos.id')
                ->where('aa.accion_formativa_id', $accion->id)
                ->select('adjuntos.*', 'pa.persona_id as clave'),
        );
    }

    /**
     * Los diplomas de una persona, por sesión.
     *
     * @return array<int, list<Adjunto>> accion_formativa_id => sus diplomas en esa sesión
     */
    public function dePersona(Persona $persona): array
    {
        return $this->agrupar(
            Adjunto::query()
                ->join('persona_adjunto as pa', 'pa.adjunto_id', '=', 'adjuntos.id')
                ->join('accion_formativa_adjunto as aa', 'aa.adjunto_id', '=', 'adjuntos.id')
                ->where('pa.persona_id', $persona->id)
                ->select('adjuntos.*', 'aa.accion_formativa_id as clave'),
        );
    }

    /**
     * @param  Builder<Adjunto>  $consulta
     * @return array<int, list<Adjunto>>
     */
    private function agrupar(Builder $consulta): array
    {
        $grupos = [];

        foreach ($consulta->orderBy('adjuntos.created_at')->get() as $adjunto) {
            $grupos[(int) $adjunto->getAttribute('clave')][] = $adjunto;
        }

        return $grupos;
    }
}
