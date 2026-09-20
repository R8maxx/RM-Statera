<?php

declare(strict_types=1);

namespace App\Domain\Incidente;

use App\Domain\Incidente\Models\Incidente;
use Illuminate\Support\Carbon;

/**
 * El reloj de la notificación, y **sólo donde la ley pone un número**.
 *
 * Son **72 horas desde que se tiene constancia**, y el número sale del artículo
 * 33.1 del RGPD, citado aquí porque un plazo sin su fuente es una opinión. Corre
 * únicamente si el incidente está marcado como notificable a la AEPD, es decir,
 * si hubo datos personales de por medio.
 *
 * **Para el CCN-CERT no hay cuenta atrás, y es la decisión del módulo.** El
 * RD 311/2022 no fija horas: dice «sin dilación». Inventarse un número sería
 * exactamente lo que este producto se niega a hacer con el riesgo residual —una
 * opinión de la herramienta disfrazada de cálculo— y además sería un número que
 * alguien acabaría defendiendo delante de un auditor. Se registra si es
 * notificable y cuándo se notificó, y la ficha lo dice por escrito.
 *
 * Hermano de `Tarea\Plazo`, y separado de él a conciencia: aquél cuenta días
 * contra una fecha que alguien se puso, y esto cuenta **horas** contra una que
 * pone la ley. Por eso tampoco entra en el calendario de obligaciones: una
 * rejilla de meses no es donde se mira un reloj de 72 horas.
 */
final readonly class PlazoNotificacion
{
    /** Las horas del artículo 33.1 del RGPD. */
    public const HORAS_AEPD = 72;

    private function __construct(
        public bool $aplica,
        public bool $notificado,
        public bool $vencido,
        public ?int $horasRestantes,
        public string $etiqueta,
        public string $tono,
    ) {}

    public static function aepd(Incidente $incidente, ?Carbon $ahora = null): self
    {
        if (! $incidente->notificable_aepd) {
            return new self(
                aplica: false,
                notificado: false,
                vencido: false,
                horasRestantes: null,
                etiqueta: 'Sin datos personales afectados',
                tono: 'no_aplica',
            );
        }

        $limite = $incidente->fecha_deteccion->copy()->addHours(self::HORAS_AEPD);

        if ($incidente->notificado_aepd_en !== null) {
            // **A tiempo o fuera de plazo, pero notificado**: la distinción vale,
            // porque notificar tarde es un incumplimiento que ya no se puede
            // deshacer y esconderlo al notificar sería borrar la prueba.
            $aTiempo = ! $incidente->notificado_aepd_en->isAfter($limite);

            return new self(
                aplica: true,
                notificado: true,
                vencido: ! $aTiempo,
                horasRestantes: null,
                etiqueta: $aTiempo
                    ? 'Notificada a la AEPD dentro de plazo'
                    : 'Notificada a la AEPD fuera de plazo',
                tono: $aTiempo ? 'implantado' : 'caducada',
            );
        }

        $momento = $ahora ?? Carbon::now();

        if ($momento->isAfter($limite)) {
            // **El único rojo del módulo.** Ni los estados ni la peligrosidad lo
            // gastan: esto es un plazo legal incumplido y ahora mismo.
            return new self(
                aplica: true,
                notificado: false,
                vencido: true,
                horasRestantes: 0,
                etiqueta: 'Plazo de la AEPD vencido sin notificar',
                tono: 'caducada',
            );
        }

        // Hacia arriba: decir «quedan 0 horas» cuando quedan cuarenta minutos es
        // peor que decir «queda 1».
        $restantes = (int) ceil($momento->diffInMinutes($limite) / 60);

        return new self(
            aplica: true,
            notificado: false,
            vencido: false,
            horasRestantes: $restantes,
            etiqueta: sprintf('Quedan %d h para notificar a la AEPD', $restantes),
            tono: $restantes <= 24 ? 'en_progreso' : 'planificado',
        );
    }

    /**
     * El CCN-CERT, **sin reloj**. Ver la cabecera.
     */
    public static function ccnCert(Incidente $incidente): self
    {
        if (! $incidente->notificable_ccn_cert) {
            return new self(
                aplica: false,
                notificado: false,
                vencido: false,
                horasRestantes: null,
                etiqueta: 'No procede notificar al CCN-CERT',
                tono: 'no_aplica',
            );
        }

        if ($incidente->notificado_ccn_cert_en !== null) {
            return new self(
                aplica: true,
                notificado: true,
                vencido: false,
                horasRestantes: null,
                etiqueta: 'Notificado al CCN-CERT el '.$incidente->notificado_ccn_cert_en->format('d/m/Y H:i'),
                tono: 'implantado',
            );
        }

        return new self(
            aplica: true,
            notificado: false,
            vencido: false,
            horasRestantes: null,
            // Sin rojo y sin cuenta atrás, a propósito: el ENS dice «sin
            // dilación» y no pone horas.
            etiqueta: 'Pendiente de notificar al CCN-CERT (sin dilación)',
            tono: 'en_progreso',
        );
    }
}
