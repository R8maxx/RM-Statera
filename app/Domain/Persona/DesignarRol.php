<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Enums\RolEns;
use App\Domain\Persona\Excepciones\DesignacionIncompatible;
use App\Domain\Persona\Excepciones\PersonaNoDesignable;
use App\Domain\Persona\Excepciones\RolYaDesignado;
use App\Domain\Persona\Models\DesignacionRol;
use App\Domain\Persona\Models\Persona;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Nombra a una persona en un rol ENS de un sistema. Cláusula 5.3.
 *
 * **Aquí vive la incompatibilidad que la especificación pide impedir**, y vive
 * aquí y no en un `CHECK` por un motivo estructural: es una condición **entre
 * filas** —dos designaciones vigentes de la misma persona en el mismo sistema— y
 * un `CHECK` sólo ve una. Es el precedente exacto de `RegistrarDependencia`, que
 * rechaza los ciclos del grafo de activos porque el `CHECK` de
 * `activo_dependencias` sólo cubre el bucle de un salto.
 *
 * Y vive en el dominio y no en el `FormRequest` porque vale igual para un
 * importador y para el seeder.
 *
 * **Lo que sí impone la base es el titular único**: un índice único parcial sobre
 * `(sistema_id, rol) WHERE hasta IS NULL` para los tres roles singulares. La
 * comprobación de aquí existe para que el mensaje sea legible y no el nombre del
 * índice.
 *
 * **Designar a quien ya no está en plantilla se rechaza**, y conviene decirlo
 * porque parece un detalle: un nombramiento vigente sobre alguien que se fue es
 * exactamente el hallazgo que `mp.per.*` busca, y dejarlo entrar convertiría el
 * registro en algo que hay que auditar aparte.
 */
final class DesignarRol
{
    /**
     * @throws DesignacionIncompatible
     * @throws PersonaNoDesignable
     * @throws RolYaDesignado
     */
    public function __invoke(
        Persona $persona,
        Sistema $sistema,
        RolEns $rol,
        ?Carbon $desde = null,
        ?User $designadaPor = null,
        ?string $nota = null,
    ): DesignacionRol {
        if (! $persona->estaActiva()) {
            throw PersonaNoDesignable::porEstarDeBaja($persona->nombre);
        }

        $this->comprobarIncompatibilidad($persona, $sistema, $rol);
        $this->comprobarTitularUnico($sistema, $rol);

        return DesignacionRol::query()->create([
            'organizacion_id' => $persona->organizacion_id,
            'persona_id' => $persona->id,
            'sistema_id' => $sistema->id,
            'rol' => $rol->value,
            'desde' => $desde ?? Carbon::today(),
            'designada_por_id' => $designadaPor?->id,
            'nota' => $nota,
        ]);
    }

    /**
     * Revoca un nombramiento: le pone fecha de fin y **no lo borra**.
     *
     * Borrarlo dejaría al sistema sin poder demostrar quién respondía el año
     * pasado, que es la mitad de para qué existe esta tabla (invariante 7).
     */
    public function revocar(DesignacionRol $designacion, ?Carbon $hasta = null): DesignacionRol
    {
        $fin = $hasta ?? Carbon::today();

        // Nunca antes de empezar: el `CHECK` lo rechaza, y el error hablaría de
        // una restricción en vez de decir que las fechas están al revés.
        if ($fin->isBefore($designacion->desde)) {
            $fin = $designacion->desde;
        }

        $designacion->update(['hasta' => $fin]);

        return $designacion->refresh();
    }

    /**
     * @throws DesignacionIncompatible
     */
    private function comprobarIncompatibilidad(Persona $persona, Sistema $sistema, RolEns $rol): void
    {
        $choques = $rol->incompatibleCon();

        if ($choques === []) {
            return;
        }

        $conflicto = DesignacionRol::query()
            ->vigentes()
            ->where('persona_id', $persona->id)
            ->where('sistema_id', $sistema->id)
            ->whereIn('rol', array_map(static fn (RolEns $otro): string => $otro->value, $choques))
            ->first();

        if ($conflicto instanceof DesignacionRol) {
            throw new DesignacionIncompatible($rol, $conflicto->rol, $persona->nombre);
        }
    }

    /**
     * @throws RolYaDesignado
     */
    private function comprobarTitularUnico(Sistema $sistema, RolEns $rol): void
    {
        if (! $rol->esUnicoPorSistema()) {
            return;
        }

        $titular = DesignacionRol::query()
            ->vigentes()
            ->where('sistema_id', $sistema->id)
            ->where('rol', $rol->value)
            ->with('persona')
            ->first();

        if ($titular instanceof DesignacionRol) {
            throw new RolYaDesignado($rol, $titular->persona->nombre);
        }
    }
}
