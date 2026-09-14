<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

/**
 * La metodología con la que una organización empieza a trabajar el primer día.
 *
 * Hermano de `TextosDeFabrica`, y con el mismo contrato: **existe para que no
 * haga falta materializar una fila**. Una organización que no ha decidido nada
 * resuelve hasta aquí, puede registrar y valorar riesgos desde el minuto uno, y
 * el día que la dirección apruebe la suya se guarda la fila y ésta deja de
 * aplicarse. Sin fábrica, la pantalla de metodología sería un trámite obligatorio
 * antes de poder apuntar el primer riesgo.
 *
 * Cinco por cinco porque es lo que usan MAGERIT y la práctica habitual de ISO
 * 27005, y porque con menos escalones dos riesgos que no se parecen en nada
 * acaban en la misma casilla. Los umbrales dejan la zona aceptable en 1-7, la
 * franja que hay que tratar en 8-14 y la inasumible en 15-25.
 *
 * **Las escalas describen la frecuencia y el perjuicio con palabras, no sólo con
 * números.** Un «3» sin descripción lo interpreta cada persona a su manera, y a
 * la tercera valoración el análisis deja de ser comparable consigo mismo — que es
 * exactamente el defecto de la hoja de cálculo que esto sustituye.
 */
final class MetodologiaDeFabrica
{
    public const NOMBRE = 'Metodología de partida de Statera';

    public const REFERENCIA = 'Inspirada en MAGERIT v3 e ISO/IEC 27005';

    public const UMBRAL_ACEPTACION = 8;

    public const UMBRAL_CRITICO = 15;

    public const PERIODICIDAD_MESES = 12;

    public static function metodologia(): Metodologia
    {
        return new Metodologia(
            nombre: self::NOMBRE,
            referencia: self::REFERENCIA,
            probabilidad: self::probabilidad(),
            impacto: self::impacto(),
            umbralAceptacion: self::UMBRAL_ACEPTACION,
            umbralCritico: self::UMBRAL_CRITICO,
            periodicidadRevisionMeses: self::PERIODICIDAD_MESES,
            esDeFabrica: true,
        );
    }

    /** Cada cuánto cabe esperar que ocurra. */
    public static function probabilidad(): EscalaRiesgo
    {
        return EscalaRiesgo::desdeArray([
            ['valor' => 1, 'etiqueta' => 'Muy baja', 'descripcion' => 'No ha pasado nunca aquí y no se conoce ningún caso cercano.'],
            ['valor' => 2, 'etiqueta' => 'Baja', 'descripcion' => 'Cabe esperarlo alguna vez en varios años.'],
            ['valor' => 3, 'etiqueta' => 'Media', 'descripcion' => 'Cabe esperarlo una vez al año.'],
            ['valor' => 4, 'etiqueta' => 'Alta', 'descripcion' => 'Cabe esperarlo varias veces al año.'],
            ['valor' => 5, 'etiqueta' => 'Muy alta', 'descripcion' => 'Ocurre de forma habitual, todos los meses o más a menudo.'],
        ], 'escala de probabilidad');
    }

    /**
     * Qué perjuicio causaría.
     *
     * Se describe en términos de servicio, de obligación legal y de recuperación,
     * que son los tres que el Anexo I del ENS usa para valorar el perjuicio. No
     * se copia su redacción: aquí basta con que dos personas distintas elijan el
     * mismo escalón ante el mismo caso.
     */
    public static function impacto(): EscalaRiesgo
    {
        return EscalaRiesgo::desdeArray([
            ['valor' => 1, 'etiqueta' => 'Muy bajo', 'descripcion' => 'Molestia puntual. Se resuelve sin que nadie de fuera se entere.'],
            ['valor' => 2, 'etiqueta' => 'Bajo', 'descripcion' => 'Afecta a un servicio de apoyo o a una persona. Se recupera en el día.'],
            ['valor' => 3, 'etiqueta' => 'Medio', 'descripcion' => 'Interrumpe un servicio o expone información interna. Se recupera en días.'],
            ['valor' => 4, 'etiqueta' => 'Alto', 'descripcion' => 'Incumplimiento legal o contractual, o parada seria de un servicio esencial.'],
            ['valor' => 5, 'etiqueta' => 'Muy alto', 'descripcion' => 'Daño grave y duradero: sanción, pérdida de datos sin recuperación o parada prolongada.'],
        ], 'escala de impacto');
    }
}
