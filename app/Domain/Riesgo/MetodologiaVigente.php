<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Riesgo\Models\MetodologiaRiesgo;

/**
 * Con qué mide los riesgos la organización actual.
 *
 * **Cadena de dos eslabones, y gana el primero que EXISTA:**
 *
 * ```
 * metodologias_riesgo (fila de la organización)  →  MetodologiaDeFabrica
 * ```
 *
 * Es la misma forma que tiene la narrativa de los documentos, y por el mismo
 * motivo: que no haga falta materializar una fila para que la herramienta
 * funcione. Una organización recién creada puede registrar y valorar su primer
 * riesgo sin pasar antes por una pantalla de configuración; el día que la
 * dirección apruebe la suya, se guarda la fila y ésta deja de aplicarse.
 *
 * Aquí no hay tercer eslabón ni cadena vacía, a diferencia de la narrativa: una
 * escala vacía no significa «he decidido que no haya escala», significa que no
 * se puede medir. Por eso `GuardarMetodologia` borra la fila en vez de dejarla
 * en blanco.
 */
final class MetodologiaVigente
{
    /**
     * Resuelve una vez por petición: la pantalla de riesgos la pide para pintar
     * el nivel de cada fila, y una consulta por fila sería una por fila.
     */
    private ?Metodologia $resuelta = null;

    public function para(): Metodologia
    {
        return $this->resuelta ??= MetodologiaRiesgo::query()->first()?->aValueObject()
            ?? MetodologiaDeFabrica::metodologia();
    }

    /** La fila de la organización, si la hay. `null` significa «vale la de fábrica». */
    public function fila(): ?MetodologiaRiesgo
    {
        return MetodologiaRiesgo::query()->first();
    }

    /**
     * Olvida lo resuelto.
     *
     * Lo necesita quien acaba de guardar la metodología en la misma petición, y
     * lo necesitan los tests, que cambian de organización sin cambiar de proceso.
     */
    public function olvidar(): void
    {
        $this->resuelta = null;
    }
}
