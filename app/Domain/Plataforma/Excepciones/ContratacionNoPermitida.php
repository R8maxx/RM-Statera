<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Excepciones;

use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\PresupuestoCambioPlan;
use DomainException;

/**
 * La organización no puede pasar a ese plan por su cuenta (punto 51).
 *
 * El `FormRequest` ya lo comprueba para que el error salga junto al plan; esto
 * es la segunda barrera, dentro de la transacción y con la fila bloqueada, por
 * si entre la validación y el guardado alguien invitó a otra cuenta.
 */
final class ContratacionNoPermitida extends DomainException
{
    public static function noContratable(Plan $plan): self
    {
        return new self("El plan {$plan->nombre} no se contrata desde aquí: lo asigna el equipo de Statera.");
    }

    public static function porPresupuesto(PresupuestoCambioPlan $presupuesto): self
    {
        if ($presupuesto->bloqueo === PresupuestoCambioPlan::ES_EL_ACTUAL) {
            return new self("Ya tenéis el plan {$presupuesto->plan->nombre} con ese periodo.");
        }

        $sobra = array_filter([
            $presupuesto->sobranCuentas > 0 ? self::cuantas($presupuesto->sobranCuentas, 'cuenta', 'cuentas') : null,
            $presupuesto->sobranSistemas > 0 ? self::cuantas($presupuesto->sobranSistemas, 'sistema', 'sistemas') : null,
        ]);

        $unaSola = $presupuesto->sobranCuentas + $presupuesto->sobranSistemas === 1;

        return new self(
            'Lo que usáis no cabe en el plan '.$presupuesto->plan->nombre.': os '.($unaSola ? 'sobra ' : 'sobran ').implode(' y ', $sobra)
            .($unaSola ? '. Dadlo de baja antes de cambiar.' : '. Dadlos de baja antes de cambiar.')
        );
    }

    private static function cuantas(int $numero, string $singular, string $plural): string
    {
        return $numero.' '.($numero === 1 ? $singular : $plural);
    }
}
