<?php

declare(strict_types=1);

namespace App\Domain\Usuario\Enums;

use App\Models\User;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Adónde lleva la entrada, según la cuenta.
 *
 * Quien trabaja por encargo empieza el día por sus tareas y no por el panel de
 * la organización, que es de quien responde de todo. Son tres porque son las
 * tres pantallas que responden a «¿qué tengo que hacer hoy?».
 */
#[TypeScript]
enum PaginaInicio: string
{
    case Panel = 'panel';
    case Tareas = 'tareas';
    case Calendario = 'calendario';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Panel => 'El panel',
            self::Tareas => 'Mis tareas',
            self::Calendario => 'El calendario',
        };
    }

    /**
     * La URL a la que se llega.
     *
     * «Mis tareas» es la tabla de tareas con el filtro de responsable puesto, el
     * mismo que pone `EmpiezaPorLoMio` al técnico: no hay una segunda pantalla.
     */
    public function url(User $cuenta): string
    {
        return match ($this) {
            self::Panel => '/panel',
            self::Tareas => '/tareas?'.http_build_query(['filter' => ['responsable_id' => (string) $cuenta->id, 'abiertas' => '1']]),
            self::Calendario => '/calendario',
        };
    }
}
