<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Persona\Models\Persona;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enlaza una cuenta de Statera con la ficha de una persona del § 4.8.
 *
 * Son dos mundos y el enlace es opcional: una cuenta es quien entra, una
 * persona es alguien de la plantilla, y un auditor externo tiene lo primero y
 * no lo segundo. `personas.user_id` es único, así que la persona que ya
 * estuviera enlazada con esta cuenta se suelta antes de enlazar la nueva.
 *
 * La persona pasa por su scope de organización al buscarla, y la traza la
 * escribe `Persona`, que sí lleva `RegistraTraza`.
 */
final class VincularPersona
{
    public function __invoke(User $cuenta, ?Persona $persona): void
    {
        DB::transaction(function () use ($cuenta, $persona): void {
            Persona::query()
                ->where('user_id', $cuenta->id)
                ->when($persona !== null, fn ($consulta) => $consulta->whereKeyNot($persona?->id))
                ->get()
                ->each(fn (Persona $anterior) => $anterior->update(['user_id' => null]));

            $persona?->update(['user_id' => $cuenta->id]);
        });
    }
}
