<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Enums;

/**
 * Las cinco dimensiones de seguridad del ENS. La valoración de estas cinco es
 * la única entrada del motor de categorización: la aplicabilidad se deriva de
 * ella, nunca se selecciona a mano.
 */
enum Dimension: string
{
    case Confidencialidad = 'C';
    case Integridad = 'I';
    case Disponibilidad = 'D';
    case Autenticidad = 'A';
    case Trazabilidad = 'T';

    public function nombre(): string
    {
        return match ($this) {
            self::Confidencialidad => 'Confidencialidad',
            self::Integridad => 'Integridad',
            self::Disponibilidad => 'Disponibilidad',
            self::Autenticidad => 'Autenticidad',
            self::Trazabilidad => 'Trazabilidad',
        };
    }

    /**
     * La pregunta que hay que contestar para valorar la dimensión.
     *
     * El Anexo I no se razona por el nombre de la dimensión sino por el
     * perjuicio: quien valora no tiene que saber qué significa «trazabilidad»,
     * tiene que saber qué pasa si no se puede reconstruir quién hizo qué.
     */
    public function pregunta(): string
    {
        return match ($this) {
            self::Confidencialidad => '¿Qué perjuicio causa que la información la conozca quien no debe?',
            self::Integridad => '¿Qué perjuicio causa que la información se altere sin autorización?',
            self::Disponibilidad => '¿Qué perjuicio causa que el servicio o la información no estén cuando se necesitan?',
            self::Autenticidad => '¿Qué perjuicio causa no poder asegurar quién es el autor de un dato o el origen de una petición?',
            self::Trazabilidad => '¿Qué perjuicio causa no poder reconstruir quién hizo qué y cuándo?',
        };
    }
}
