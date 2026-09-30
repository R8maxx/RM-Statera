<?php

declare(strict_types=1);

namespace App\Domain\Cambio;

use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Models\CambioSgsi;
use App\Http\Resources\Panel\Indicador;

/**
 * Lo que pide atención en el registro de cambios del SGSI.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y la
 * clave del indicador es la del filtro: pulsar la cifra enseña exactamente esa
 * cifra.
 *
 * **Un solo rojo, y es de plazo**: un cambio aprobado cuya fecha pasó sin
 * implantarlo. Es la dirección que firmó un compromiso con fecha y la fecha que
 * pasó, el mismo reparto que en objetivos. Lo demás —esperar firma, no haber
 * mirado todavía si sirvió— es trabajo a medias y no incumple nada.
 */
final readonly class RegistroCambios
{
    public function total(): int
    {
        return CambioSgsi::query()->count();
    }

    /** @return list<Indicador> */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'fuera_de_plazo',
                'Fuera de plazo',
                'fueraDePlazo',
                'caducada',
                'Aprobados cuya fecha prevista ya pasó y que siguen sin implantar.',
            ),
        ];
    }

    /** @return list<Indicador> */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'abiertos',
                'Abiertos',
                'abiertos',
                'en_progreso',
                'Propuestos, aprobados e implantados sin revisar.',
            ),
            $this->indicador(
                'sin_aprobar',
                'Esperando firma',
                'sinAprobar',
                'en_revision',
                'Propuestos: la 6.3 pide que el cambio se haga de forma planificada, y eso empieza por decidirlo.',
            ),
            $this->indicador(
                'sin_trabajo',
                'Sin actuación',
                'sinTrabajo',
                'planificado',
                'Aprobados sin ninguna tarea viva detrás.',
            ),
            $this->indicador(
                'sin_revisar',
                'Sin revisar',
                'sinRevisar',
                'no_iniciado',
                'Implantados sin haber mirado todavía si consiguieron lo que pretendían.',
            ),
        ];
    }

    /**
     * El reparto por estado, con los cerrados dentro.
     *
     * @return array<string, int>
     */
    public function porEstado(): array
    {
        /** @var array<string, int> $conteos */
        $conteos = CambioSgsi::query()
            ->selectRaw('estado as clave, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'clave')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $reparto = [];

        foreach (EstadoCambio::cases() as $estado) {
            $reparto[$estado->value] = $conteos[$estado->value] ?? 0;
        }

        return $reparto;
    }

    /**
     * La clave del indicador **es** la del filtro, y el scope **es** el tercer
     * argumento de su `Filtro::porScope()`.
     */
    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: CambioSgsi::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/cambios-sgsi',
            ayuda: $ayuda,
        );
    }
}
