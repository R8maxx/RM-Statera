<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido\Concerns;

use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Documento\Models\Documento;

/**
 * Las cifras de una declaración de aplicabilidad: cuánto aplica y cuánto se cumple.
 *
 * Estaba dentro de la clase base de los documentos calculados, y salió de ahí al
 * entrar el plan de adecuación. La razón no es de reparto de código: es que un
 * plan **no puede contestar estas preguntas**. Sus filas son todas pendientes por
 * construcción, así que el porcentaje implantado sería siempre cero y el número
 * de excluidos siempre cero, dos cifras que no dicen nada de la realidad y que
 * saldrían impresas al lado de una tabla que sí la cuenta.
 *
 * Como trait y no como método heredable, un hijo que no lo use no lo tiene, que
 * es la diferencia entre no ofrecerlo y confiar en que nadie lo llame.
 */
trait ResumeLaAplicabilidad
{
    /**
     * **Se cuentan sobre las filas que este documento lista**, no sobre las
     * implantaciones del sistema y menos aún sobre las de la organización. Un
     * sistema de ISO lleva, además de los 93 controles del Anexo A, las
     * cláusulas 4 a 10 —el sistema de gestión— y el documento no las enseña: la
     * barra decía 122 y la tabla que tiene debajo decía 93.
     *
     * Es el mismo criterio que ya rige en el panel de inventario: cada cifra se
     * cuenta con el mismo alcance que tiene lo que enseña al lado.
     *
     * @param  list<FilaRequisito>  $filas
     * @return array<string, mixed>
     */
    protected function resumen(Documento $documento, array $filas): array
    {
        /*
         * La madurez también se cuenta sobre las filas del documento y no sobre
         * todas las del sistema, por el mismo motivo que los tramos: un sistema
         * de ISO lleva además las cláusulas 4 a 10, y una media que las incluya
         * no es la media de los controles del Anexo A.
         */
        $valoradas = array_values(array_filter(
            $filas,
            static fn (FilaRequisito $f): bool => $f->aplica && $f->madurezValor !== null,
        ));

        $madurez = [
            'evaluadas' => count($valoradas),
            // Sin ninguna valorada la media no es cero: es que no se sabe.
            'media' => $valoradas === [] ? null : round(array_sum(array_map(
                static fn (FilaRequisito $f): int => (int) $f->madurezValor,
                $valoradas,
            )) / count($valoradas), 1),
        ];

        $aplicables = count(array_filter($filas, static fn (FilaRequisito $f): bool => $f->aplica));
        $implantados = count(array_filter($filas, static fn (FilaRequisito $f): bool => $f->estado === 'implantado'));

        return [
            'total' => count($filas),
            /*
             * Cuántos requisitos tiene el marco entero, que puede ser MÁS que
             * los que salen en el documento: en el ENS, una categoría básica
             * sólo exige 52 de las 73 medidas del Anexo II.
             *
             * Sin este denominador la tabla dice «52 medidas del Anexo II» y se
             * lee como si el Anexo II tuviera 52. Que falten veintiuna es una
             * consecuencia correcta de la categorización, pero el auditor tiene
             * que poder verla, no deducirla.
             */
            'enElMarco' => $this->requisitosDelMarco($documento),
            'aplicables' => $aplicables,
            'excluidos' => count($filas) - $aplicables,
            'implantados' => $implantados,
            // Sin nada exigible el porcentaje no es cero: es que no hay nada que
            // medir, y un 0 % ahí diría lo contrario de lo que pasa.
            'porcentaje' => $aplicables === 0 ? null : (int) round($implantados * 100 / $aplicables),
            'sinEvidencia' => count(array_filter(
                $filas,
                static fn (FilaRequisito $f): bool => $f->aplica && ! $f->tieneEvidencia(),
            )),
            'madurezMedia' => $madurez['media'],
            'madurezEvaluadas' => $madurez['evaluadas'],
            // Los tramos se cuentan sobre las MISMAS filas que lista el
            // documento, no sobre todas las del sistema.
            'segmentos' => $this->segmentosDe($filas),
        ];
    }
}
