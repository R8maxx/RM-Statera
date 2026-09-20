<?php

declare(strict_types=1);

namespace App\Domain\Persona\Excepciones;

use App\Domain\Persona\Enums\RolEns;
use DomainException;

/**
 * El nombramiento que la cláusula 5.3 pide **impedir**.
 *
 * La especificación no dice «avisar» ni «señalar»: dice que el sistema debe
 * impedir que el responsable de seguridad y el responsable del sistema recaigan en
 * la misma persona. Quien decide qué protección hace falta no puede ser quien
 * responde de haberla puesto, y una organización que designa a la misma persona
 * para las dos cosas no tiene separación de funciones por mucho que lo declare.
 *
 * **Es una excepción y no una contradicción señalada**, a diferencia del residual
 * sin respaldo de un riesgo o del equipo retirado sin borrar. La diferencia: allí
 * el dato es de la organización y la herramienta no opina; aquí la regla viene de
 * la guía y no admite matiz.
 */
final class DesignacionIncompatible extends DomainException
{
    public function __construct(
        public readonly RolEns $rol,
        public readonly RolEns $conflicto,
        string $persona,
    ) {
        parent::__construct(sprintf(
            '%s ya es «%s» de este sistema, y ese rol es incompatible con «%s»: quien decide qué '
            .'protección hace falta no puede ser quien responde de haberla puesto. Revoca la '
            .'designación anterior o nombra a otra persona.',
            $persona,
            $conflicto->etiqueta(),
            $rol->etiqueta(),
        ));
    }
}
