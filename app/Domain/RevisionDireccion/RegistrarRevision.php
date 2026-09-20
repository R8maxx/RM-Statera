<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion;

use App\Domain\RevisionDireccion\Models\RevisionDireccion;

/**
 * Da de alta una revisión por la dirección.
 *
 * El `refresh()` es lo mismo que hacen `CrearTarea`, `RegistrarAuditoria`,
 * `RegistrarNoConformidad`, `RegistrarObjetivo` y `RegistrarMejora`: `estado` lo
 * pone la base con su valor por defecto —repetirlo en el modelo sería el mismo
 * dato en dos sitios que pueden desincronizarse—, así que la instancia recién
 * creada llega **sin estado**, y lo primero que lo lee revienta con un «call to a
 * member function on null» que no menciona la palabra «estado».
 *
 * **Sin transición de alta**, a diferencia de sus hermanas: este módulo no lleva
 * tabla de transiciones. Ver `CambiarEstadoRevision`.
 *
 * Nace siempre **planificada**: celebrarla y firmarla son dos gestos aparte, y
 * dejar crear una ya aprobada permitiría saltarse el congelado de las entradas,
 * que es lo único que este módulo hace de verdad.
 */
final class RegistrarRevision
{
    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos): RevisionDireccion
    {
        $revision = RevisionDireccion::query()->create($atributos);

        return $revision->refresh();
    }
}
