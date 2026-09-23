<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Http\Resources\Panel\Indicador;

/**
 * Lo que pide atención en los dos listados de continuidad: el BIA de cada
 * servicio y las pruebas que lo contrastan.
 *
 * **Un solo registro para dos tablas**, porque las dos cuelgan del mismo
 * permiso (`continuidad.ver`) y de la misma pestaña del panel. `alertas()` es
 * la unión de las dos —lo que lee `AlertasDelPanel`—, y cada `Index` pide
 * sólo la suya con `alertasDeBia()` / `alertasDePruebas()` y
 * `pendientesDeBia()` / `pendientesDePruebas()`.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**,
 * y la clave del indicador es la del filtro: eso es lo que garantiza que
 * pulsar la cifra enseñe exactamente esa cifra. Ver `BiaServicioRecurso` y
 * `PruebaContinuidadRecurso`.
 *
 * **El rojo es sólo lo vencido.** `revision_vencida` (BIA) y `vencidas`
 * (pruebas) son `caducada`; `rto_incoherente` es `en_progreso` porque es una
 * contradicción que corregir y no un plazo ya incumplido —nadie ha dejado de
 * cumplir nada todavía—. `borrador` y `planificadas` tampoco son rojo: un
 * borrador o algo por planificar no está incumplido, está a medias.
 */
final readonly class RegistroContinuidad
{
    /**
     * Lo que va mal de verdad, en los dos listados.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            ...$this->alertasDeBia(),
            ...$this->alertasDePruebas(),
        ];
    }

    /**
     * Lo que va mal de verdad, y lo que está a medias, en el BIA.
     *
     * @return list<Indicador>
     */
    public function alertasDeBia(): array
    {
        return [
            /*
             * `en_progreso` (ámbar) y no `caducada` (rojo), a propósito: un
             * RTO incoherente con el umbral tolerable es una contradicción
             * que corregir, no un plazo ya incumplido.
             */
            $this->indicadorBia(
                'rto_incoherente',
                'Con un RTO por encima del umbral tolerable',
                'rtoIncoherente',
                'en_progreso',
                'El RTO declarado promete más de lo que el propio BIA tolera.',
            ),
            $this->indicadorBia(
                'revision_vencida',
                'Con la revisión vencida',
                'revisionVencida',
                'caducada',
                'El BIA lleva más de doce meses sin volver a aprobarse.',
            ),
        ];
    }

    /**
     * @return list<Indicador>
     */
    public function pendientesDeBia(): array
    {
        return [
            new Indicador(
                clave: 'borrador',
                etiqueta: 'la aprobación',
                valor: BiaServicio::query()->where('estado', EstadoBia::Borrador->value)->count(),
                tono: 'no_iniciado',
                filtro: 'filter[estado]=borrador',
                base: '/continuidad/bia',
                ayuda: 'Todavía sin aprobar: el RTO que declara no cuenta como vigente.',
            ),
        ];
    }

    /**
     * Lo que va mal de verdad en las pruebas.
     *
     * @return list<Indicador>
     */
    public function alertasDePruebas(): array
    {
        return [
            $this->indicadorPrueba(
                'vencidas',
                'Vencidas',
                'vencidas',
                'caducada',
                'Planificadas cuya fecha prevista ya ha pasado sin registrar resultado.',
            ),
        ];
    }

    /**
     * @return list<Indicador>
     */
    public function pendientesDePruebas(): array
    {
        return [
            new Indicador(
                clave: 'planificadas',
                etiqueta: 'el resultado',
                valor: PruebaContinuidad::query()->where('estado', EstadoPrueba::Planificada->value)->count(),
                tono: 'planificado',
                filtro: 'filter[estado]=planificada',
                base: '/continuidad/pruebas',
                ayuda: 'Todavía sin resultado registrado.',
            ),
        ];
    }

    private function indicadorBia(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: BiaServicio::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/continuidad/bia',
            ayuda: $ayuda,
        );
    }

    private function indicadorPrueba(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: PruebaContinuidad::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/continuidad/pruebas',
            ayuda: $ayuda,
        );
    }
}
