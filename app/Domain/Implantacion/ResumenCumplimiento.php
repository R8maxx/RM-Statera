<?php

declare(strict_types=1);

namespace App\Domain\Implantacion;

use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Http\Resources\Panel\AvanceMarco;
use App\Http\Resources\Panel\SegmentoEstado;

/**
 * Las cifras de cumplimiento de la organización activa.
 *
 * Vive en el dominio y no en el controlador porque son las mismas preguntas que
 * contestarán el informe de estado y la Declaración de Aplicabilidad: «cuánto
 * hay implantado», «de qué marco» y «con qué madurez». El controlador sólo las
 * hace y las devuelve.
 *
 * Todas las consultas pasan por el scope de organización. Se cuenta siempre
 * sobre lo **exigible** (`aplica = true`): un requisito que no se le exige al
 * sistema no está pendiente, sencillamente no cuenta.
 */
final class ResumenCumplimiento
{
    /**
     * El reparto por estado de lo exigible.
     *
     * Devuelve los cuatro estados aunque alguno esté a cero: una barra a la que
     * le falta un tramo según el día se lee peor que una con un tramo vacío.
     *
     * @return list<SegmentoEstado>
     */
    public function porEstado(): array
    {
        $conteos = [];

        $filas = Implantacion::query()
            ->where('aplica', true)
            ->groupBy('estado')
            ->selectRaw('estado, count(*) as total')
            ->get();

        foreach ($filas as $fila) {
            $conteos[$fila->estado->value] = (int) $fila->getAttribute('total');
        }

        /*
         * El orden no es el del flujo, y es una decisión medida: el verde de
         * `implantado` y el ámbar de `en_progreso` pegados no se distinguen con
         * protanopia (ΔE 5.7, por debajo del suelo de 6), y basta con meter el
         * azul de `planificado` entre los dos para subir a 14.0. DESIGN.md §3
         * lo recoge. `no_aplica` no entra: por la restricción de la base no
         * puede coexistir con `aplica = true`.
         */
        $orden = [
            EstadoImplantacion::Implantado,
            EstadoImplantacion::Planificado,
            EstadoImplantacion::EnProgreso,
            EstadoImplantacion::NoIniciado,
        ];

        $segmentos = [];

        foreach ($orden as $estado) {
            $segmentos[] = new SegmentoEstado(
                $estado->value,
                $estado->etiqueta(),
                $conteos[$estado->value] ?? 0,
            );
        }

        return $segmentos;
    }

    /**
     * El avance por marco normativo, que es lo que pide la especificación §4.14.
     *
     * El marco cuelga del requisito, no de la implantación: el catálogo es
     * global y compartido, así que hay que llegar por el join.
     *
     * @return list<AvanceMarco>
     */
    public function porMarco(): array
    {
        $filas = Implantacion::query()
            ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
            ->join('marcos', 'marcos.id', '=', 'requisitos.marco_id')
            ->where('implantaciones.aplica', true)
            ->groupBy('marcos.id', 'marcos.codigo', 'marcos.nombre')
            ->orderBy('marcos.nombre')
            ->selectRaw('marcos.codigo as marco_codigo, marcos.nombre as marco_nombre, count(*) as aplicables')
            // `count(*) filter (where …)` es de PostgreSQL y aquí no hay otra
            // base: cuenta las implantadas en la misma pasada que el total, sin
            // una segunda consulta por marco.
            ->selectRaw(
                'count(*) filter (where implantaciones.estado = ?) as implantadas',
                [EstadoImplantacion::Implantado->value],
            )
            ->get();

        return $filas
            ->map(fn (Implantacion $fila): AvanceMarco => new AvanceMarco(
                (string) $fila->getAttribute('marco_codigo'),
                (string) $fila->getAttribute('marco_nombre'),
                (int) $fila->getAttribute('aplicables'),
                (int) $fila->getAttribute('implantadas'),
            ))
            ->values()
            ->all();
    }

    /**
     * La madurez media y sobre cuántos requisitos se calcula.
     *
     * El nivel se guarda como `l0`…`l5` y la restricción `CHECK` de la tabla
     * garantiza ese formato, así que el dígito es el valor de la escala.
     *
     * @return array{media: ?float, evaluadas: int}
     */
    public function madurez(): array
    {
        $fila = Implantacion::query()
            ->where('aplica', true)
            ->whereNotNull('nivel_madurez')
            ->selectRaw('count(*) as evaluadas, avg(cast(substring(nivel_madurez from 2) as integer)) as media')
            ->first();

        $evaluadas = (int) ($fila?->getAttribute('evaluadas') ?? 0);
        $media = $fila?->getAttribute('media');

        return [
            // Sin ninguna valorada la media no es cero: es que no se sabe.
            'media' => $evaluadas === 0 || $media === null ? null : round((float) $media, 1),
            'evaluadas' => $evaluadas,
        ];
    }

    /** Lo exigible que todavía no está implantado. */
    public function pendientes(): int
    {
        return Implantacion::query()
            ->where('aplica', true)
            ->whereNot('estado', EstadoImplantacion::Implantado->value)
            ->count();
    }

    /**
     * El estado del repositorio de pruebas.
     *
     * Las tres cifras que un auditor mira antes que ninguna otra: cuántas
     * pruebas hay, cuántas han caducado y cuántas están a punto. Una evidencia
     * caducada deja sin prueba al requisito que sostenía, así que cuenta como
     * incumplimiento aunque el estado siga diciendo «implantado».
     *
     * @return array{total: int, caducadas: int, porCaducar: int}
     */
    public function evidencias(): array
    {
        return [
            'total' => Evidencia::query()->count(),
            'caducadas' => Evidencia::query()->caducadas()->count(),
            'porCaducar' => Evidencia::query()->porCaducar()->count(),
        ];
    }

    /**
     * Requisitos exigibles que no tienen ninguna prueba detrás.
     *
     * Es la pregunta que separa «lo tenemos hecho» de «lo podemos demostrar», y
     * el motivo de que la Declaración de Aplicabilidad se entregue con una
     * columna de evidencia. Se cuenta sólo sobre lo exigible: un requisito que
     * no se le exige al sistema no necesita prueba.
     */
    public function implantadasSinEvidencia(): int
    {
        return Implantacion::query()
            ->where('aplica', true)
            ->where('estado', EstadoImplantacion::Implantado->value)
            ->whereDoesntHave('evidencias')
            ->count();
    }
}
