<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Riesgo\Models\Riesgo;
use App\Http\Resources\Panel\Indicador;

/**
 * Las cifras del registro de riesgos.
 *
 * Hermano de `ResumenInventario`, y con la misma regla, que es la que de verdad
 * importa: **cada indicador cuenta con el mismo scope que usa su filtro de la
 * tabla**. No es comodidad — es lo que garantiza que pulsar una cifra enseñe
 * exactamente esa cifra. Con la condición escrita dos veces, el día que cambie una
 * el panel dirá 12 y la lista enseñará 9, y a partir de ahí nadie se fía del panel.
 *
 * Aquí sólo está lo que pide acción hoy. Los repartos —por decisión, por nivel, por
 * grupo de amenaza— son perfil y no urgencia, y van al panel, que es donde se
 * pregunta cómo va la cosa; es el mismo reparto que se hizo entre `/panel` y
 * `/activos`.
 */
final class RegistroRiesgos
{
    public function total(): int
    {
        return Riesgo::query()->count();
    }

    /**
     * @return list<Indicador>
     */
    public function indicadores(): array
    {
        return [
            $this->indicador(
                'sobre_umbral',
                'Por encima del umbral',
                'sobreUmbral',
                'caducada',
                'Lo que queda después de tratar supera el umbral de aceptación de la organización.',
            ),
            $this->indicador(
                'sin_valorar',
                'Sin valorar',
                'sinValorar',
                'no_iniciado',
                'Registrados y todavía sin medir: no cuentan en ninguna cifra de exposición.',
            ),
            $this->indicador(
                'sin_aceptar',
                'Pendientes de firma',
                'sinAceptar',
                'en_progreso',
                'Medidos, con decisión tomada y sin que el propietario del riesgo la haya firmado.',
            ),
            $this->indicador(
                'residual_sin_respaldo',
                'Residual sin respaldo',
                'residualSinRespaldo',
                'caducada',
                'Se declara que el riesgo baja y ninguna salvaguarda vinculada está implantada.',
            ),
            $this->indicador(
                'revision_vencida',
                'Reevaluación vencida',
                'revisionVencida',
                'caducada',
                'Pasó la fecha en que tocaba volver a mirarlo.',
            ),
        ];
    }

    /**
     * El scope es el mismo que declara el filtro de la tabla, y por eso se invoca
     * por nombre: es la única forma de que la cifra y la lista no puedan divergir.
     */
    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Riesgo::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/riesgos',
            ayuda: $ayuda,
        );
    }
}
