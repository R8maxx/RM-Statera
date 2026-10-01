<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion;

use App\Domain\Comunicacion\Models\Comunicacion;
use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use App\Http\Resources\Panel\Indicador;

/**
 * Lo que pide atención en el plan de comunicación.
 *
 * **Un solo rojo**: una línea periódica cuya fecha ya pasó sin que se
 * comunicara. Es la organización que dijo «cada trimestre» y no lo hizo, el mismo
 * reparto que un compromiso del § 4.16 fuera de plazo.
 *
 * Lo demás no incumple nada y no gasta rojo. Y lo recibido **nunca**: una queja
 * apuntada es una organización que escucha.
 *
 * Cada indicador cuenta con el scope que usa su filtro, y su clave es la del
 * filtro: pulsar la cifra enseña exactamente esa cifra.
 */
final readonly class RegistroComunicacion
{
    public const DIAS_POR_VENCER = 30;

    public function total(): int
    {
        return ComunicacionPrevista::query()->activas()->count();
    }

    /** @return list<Indicador> */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'vencidas',
                'Fuera de plazo',
                ComunicacionPrevista::query()->vencidas()->count(),
                'caducada',
                'Comunicaciones periódicas cuya fecha ya pasó sin que se comunicaran.',
            ),
        ];
    }

    /** @return list<Indicador> */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'por_vencer',
                'Tocan pronto',
                ComunicacionPrevista::query()->porVencer(self::DIAS_POR_VENCER)->count(),
                'en_progreso',
                'Comunicaciones periódicas que tocan en los próximos treinta días.',
            ),
            $this->indicador(
                'sin_destinatarios',
                'Sin destinatarios',
                ComunicacionPrevista::query()->sinDestinatarios()->count(),
                'planificado',
                'La cláusula 7.4 pide decir a quién se comunica.',
            ),
            $this->indicador(
                'sin_responsable',
                'Sin responsable',
                ComunicacionPrevista::query()->sinResponsable()->count(),
                'no_iniciado',
                'La cláusula 7.4 pide decir quién comunica.',
            ),
        ];
    }

    /**
     * Lo recibido sin contestar, para el registro de comunicaciones. No es rojo.
     */
    public function recibidasSinRespuesta(): Indicador
    {
        return new Indicador(
            clave: 'sin_respuesta',
            etiqueta: 'Recibidas sin respuesta',
            valor: Comunicacion::query()->sinRespuesta()->count(),
            tono: 'no_iniciado',
            filtro: 'filter[sin_respuesta]=1',
            base: '/comunicaciones',
            ayuda: 'Quejas, sugerencias o consultas a las que todavía no consta qué se contestó.',
        );
    }

    private function indicador(string $clave, string $etiqueta, int $valor, string $tono, string $ayuda): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: $valor,
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/plan-comunicacion',
            ayuda: $ayuda,
        );
    }
}
