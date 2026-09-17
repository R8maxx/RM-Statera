<?php

declare(strict_types=1);

namespace App\Domain\Auditoria;

use App\Domain\Auditoria\Models\Auditoria;
use App\Http\Resources\Panel\Indicador;

/**
 * Lo que pide atención en el registro de auditorías.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y
 * la clave del indicador es la del filtro: eso es lo que garantiza que pulsar la
 * cifra enseñe exactamente esa cifra. Con la condición escrita dos veces, el día
 * que cambie una el registro dirá 3 y la lista enseñará 1, y a partir de ahí
 * nadie se fía del número.
 *
 * Igual que `RegistroRiesgos` y `ResumenDocumental`, vive en el dominio y no en
 * `Http/Resources`: son preguntas del negocio, no de una pantalla.
 */
final readonly class RegistroAuditorias
{
    public function total(): int
    {
        return Auditoria::query()->count();
    }

    /**
     * Lo que va mal o está a medias.
     *
     * **Ninguno gasta rojo.** Una auditoría sin cerrar no es un incumplimiento —es
     * trabajo en curso— y una con no conformidades es exactamente lo que una
     * auditoría tiene que producir: el rojo se lo quedan los hallazgos, que es
     * donde `TipoHallazgo::NcMayor` lo gasta.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'con_no_conformidades',
                'Con no conformidades',
                'conNoConformidades',
                'en_progreso',
                'Auditorías cerradas que encontraron al menos una no conformidad.',
            ),
        ];
    }

    /**
     * Lo que está sin terminar.
     *
     * @return list<Indicador>
     */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'abiertas',
                'Sin cerrar',
                'abiertas',
                'planificado',
                'Mientras no se cierra, su checklist y sus hallazgos se pueden cambiar.',
            ),
        ];
    }

    /**
     * La clave del indicador **es** la del filtro, y el scope **es** el tercer
     * argumento de su `Filtro::porScope()`. No es una coincidencia que haya que
     * mantener: es lo que hace que la cifra y la lista no puedan divergir.
     */
    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Auditoria::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/auditorias',
            ayuda: $ayuda,
        );
    }
}
