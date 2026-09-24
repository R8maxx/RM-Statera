<?php

declare(strict_types=1);

namespace App\Domain\Autorizacion;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;

/**
 * El técnico escribe lo suyo (§ 4.19: «un técnico ve sus tareas y las
 * implantaciones a su cargo»).
 *
 * **Lee todo y escribe lo suyo**, que es como se decidió leer esa frase: el
 * técnico sigue viendo la organización entera —una evidencia compartida con la
 * medida de otro, la tarea de la que depende la suya—, y lo que no puede es
 * mover una tarea o una implantación que está a cargo de otra persona.
 *
 * **Lo que no tiene responsable es de todos**: una implantación recién generada
 * por el motor nace sin nadie a cargo, y exigir que alguien se la asigne antes de
 * poder tocarla sería exigir un paso que en el día a día nadie da.
 *
 * El responsable de seguridad escribe todo. El auditor no escribe nada, pero eso
 * no lo decide esto sino su lista de permisos.
 */
final class EscrituraPropia
{
    /** @var array<int, ?Rol> */
    private array $roles = [];

    public function puedeEscribir(User $usuario, Tarea|Implantacion $registro): bool
    {
        if ($this->rolDe($usuario) !== Rol::Tecnico) {
            return true;
        }

        return $registro->responsable_id === null || $registro->responsable_id === $usuario->id;
    }

    /** Se resuelve una vez por cuenta: una acción masiva pregunta por cada fila. */
    private function rolDe(User $usuario): ?Rol
    {
        if (! array_key_exists($usuario->id, $this->roles)) {
            $this->roles[$usuario->id] = $usuario->rol();
        }

        return $this->roles[$usuario->id];
    }
}
