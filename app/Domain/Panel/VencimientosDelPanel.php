<?php

declare(strict_types=1);

namespace App\Domain\Panel;

use App\Domain\Aviso\Fuente;
use App\Domain\Aviso\ResumenVencimientos;
use App\Domain\Aviso\Vencimiento;
use App\Http\Resources\Panel\VencimientosPanel;
use App\Models\User;

/**
 * Lo que vence, recortado a una pestaña del panel.
 *
 * **No hay consulta nueva.** Las filas salen de `ResumenVencimientos`, que es lo
 * mismo que lee el correo diario y lo que pinta el calendario: con una tercera
 * forma de contar, el panel diría 4 vencidas y el correo 5.
 *
 * **Qué fuentes entran lo decide la `base` de cada una**, preguntándole a
 * `AlertasDelPanel::VISTAS`, y no una lista escrita aquí. Es lo que hace que la
 * lista de «El ciclo» enseñe los mismos rojos que cuenta el punto de su pestaña:
 * una evidencia caducada cae en «Cumplimiento» y se explica allí, y una formación
 * por renovar, en «La organización». Una fuente nueva de `Fuente` entra sola en
 * la pestaña de su módulo.
 *
 * **Cada fuente con su permiso**, por `Fuente::visiblesPara()`: la lista reúne
 * registros de varios módulos con una sola llave, igual que el calendario.
 */
final readonly class VencimientosDelPanel
{
    /**
     * Cuántas filas caben al lado del plan sin que la tarjeta crezca más que él.
     * Lo que no cabe se cuenta y se enlaza, no se esconde.
     */
    public const FILAS = 6;

    public function __construct(private ResumenVencimientos $resumen) {}

    public function paraLaVista(string $vista, User $usuario): VencimientosPanel
    {
        $fuentes = self::fuentesDe($vista, $usuario);
        $vencimientos = ($this->resumen)(ResumenVencimientos::DIAS, $fuentes);

        $pasados = [];
        $proximos = [];

        foreach ($fuentes as $fuente) {
            $pasados = [...$pasados, ...$vencimientos->pasadosDe($fuente)];
            $proximos = [...$proximos, ...$vencimientos->proximosDe($fuente)];
        }

        /*
         * Lo que lleva más tiempo vencido va primero: es lo que más ha empeorado
         * mientras nadie miraba. Lo próximo, por cercanía.
         */
        $porDias = static fn (Vencimiento $a, Vencimiento $b): int => [$a->dias, $a->titulo] <=> [$b->dias, $b->titulo];
        usort($pasados, $porDias);
        usort($proximos, $porDias);

        return new VencimientosPanel(
            pasados: count($pasados),
            proximos: count($proximos),
            dias: ResumenVencimientos::DIAS,
            filas: array_slice([...$pasados, ...$proximos], 0, self::FILAS),
        );
    }

    /**
     * Las fuentes que esta cuenta ve y cuya base cae en la vista.
     *
     * @return list<Fuente>
     */
    public static function fuentesDe(string $vista, User $usuario): array
    {
        return array_values(array_filter(
            Fuente::visiblesPara($usuario),
            static fn (Fuente $fuente): bool => AlertasDelPanel::vistaDe($fuente->base()) === $vista,
        ));
    }
}
