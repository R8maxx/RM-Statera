<?php

declare(strict_types=1);

namespace App\Domain\Activo;

use App\Domain\Activo\Models\Activo;
use Illuminate\Support\Carbon;

/**
 * Qué software base ha dejado de recibir parches, y cuándo.
 *
 * Es `op.exp.4` del ENS y `A.8.8` de ISO, y es el hallazgo que nadie ve venir:
 * la fecha pasa un martes cualquiera, nadie recibe un aviso, y el servidor sigue
 * ahí funcionando perfectamente hasta que sale un CVE sin parche.
 *
 * Las fechas viven en `config/obsolescencia.php` — hechos del mundo, iguales
 * para todos los clientes — y aquí sólo se consultan.
 */
final class Obsolescencia
{
    /**
     * La fecha de fin de soporte conocida de un sistema operativo.
     *
     * Devuelve `null` tanto para lo que no está en la lista como para lo que va
     * por versión continua. Las dos cosas significan lo mismo de cara al aviso:
     * no hay fecha que vigilar. Distinguirlas sólo serviría para enseñar un
     * «desconocido» que nadie puede accionar.
     */
    public function finDeSoporteDe(?string $sistemaOperativo): ?Carbon
    {
        if ($sistemaOperativo === null) {
            return null;
        }

        /** @var array<string, ?string> $conocidos */
        $conocidos = config('obsolescencia.sistemas_operativos', []);
        $fecha = $conocidos[$sistemaOperativo] ?? null;

        return $fecha === null ? null : Carbon::parse($fecha);
    }

    /**
     * @return array<string, ?string> Sistema operativo => fecha ISO o null.
     */
    public function sistemasOperativos(): array
    {
        /** @var array<string, ?string> $conocidos */
        $conocidos = config('obsolescencia.sistemas_operativos', []);

        return $conocidos;
    }

    /**
     * Si al activo se le ha pasado el soporte del sistema operativo.
     *
     * Se mira `fin_soporte_so` del propio activo y no el mapa, porque la fecha
     * pudo escribirse a mano para un sistema que no está en la lista. Un activo
     * sin fecha **no** es obsoleto: no saber no es incumplir, igual que
     * «por confirmar» no es «no» en el cifrado.
     */
    public function soporteVencido(Activo $activo): bool
    {
        return $activo->fin_soporte_so !== null && $activo->fin_soporte_so->isPast();
    }

    /** Si la garantía o el contrato de soporte del equipo ya venció. */
    public function garantiaVencida(Activo $activo): bool
    {
        return $activo->fin_garantia !== null && $activo->fin_garantia->isPast();
    }

    /**
     * Lo que hay que decirle a quien mira la ficha, o `null` si no hay nada que
     * decir. Un solo aviso aunque fallen las dos fechas: dos avisos seguidos
     * sobre el mismo equipo se leen como uno solo y se ignoran igual.
     */
    public function aviso(Activo $activo): ?string
    {
        $motivos = [];

        if ($this->soporteVencido($activo)) {
            $motivos[] = sprintf(
                '%s dejó de recibir parches el %s',
                $activo->sistema_operativo ?? 'El sistema operativo',
                $activo->fin_soporte_so?->format('d/m/Y'),
            );
        }

        if ($this->garantiaVencida($activo)) {
            $motivos[] = sprintf('la garantía venció el %s', $activo->fin_garantia?->format('d/m/Y'));
        }

        return $motivos === [] ? null : ucfirst(implode(' y ', $motivos)).'.';
    }
}
