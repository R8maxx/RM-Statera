<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Persona\Models\Persona;
use App\Models\User;

/**
 * Quién es la cuenta dentro de la organización: rol, puesto y desde cuándo.
 *
 * El puesto no es de la cuenta: es de la **persona** vinculada (§ 4.8), y sólo
 * si la hay. Una cuenta sin persona —el auditor externo, que no es plantilla—
 * no tiene puesto, y la pantalla no se lo inventa.
 */
final class FichaPropia
{
    /**
     * @return array{rol: ?string, puesto: ?string, organizacion: ?string, desde: ?string, passwordCambiadaEn: ?string}
     */
    public function de(User $cuenta): array
    {
        $persona = Persona::query()
            ->where('user_id', $cuenta->id)
            ->with('asignaciones.puesto')
            ->first();

        return [
            'rol' => $cuenta->rol()?->etiqueta(),
            'puesto' => $persona?->puestoVigente()?->titulo,
            'organizacion' => $cuenta->organizacion?->nombre,
            // La cuenta existe desde que se aceptó la invitación, no desde que
            // alguien la creó: hasta entonces nadie podía entrar con ella.
            'desde' => ($cuenta->activada_en ?? $cuenta->created_at)?->toIso8601String(),
            'passwordCambiadaEn' => $cuenta->password_cambiada_en?->toIso8601String(),
        ];
    }
}
