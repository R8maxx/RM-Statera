<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad;

use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\NoConformidad\Excepciones\HallazgoNoTratable;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta una no conformidad, con su primera transición.
 *
 * Dos cosas, y ninguna puede quedarse fuera del camino:
 *
 * 1. **El `refresh()`**, que es lo mismo que hacen `CrearTarea`,
 *    `GenerarDocumento::encolar()` y `RegistrarAuditoria`: `estado` y `origen` los
 *    pone la base con su valor por defecto —repetirlos en el modelo sería el mismo
 *    dato en dos sitios que pueden desincronizarse—, así que la instancia recién
 *    creada llega **sin estado**, y lo primero que lo lee revienta con un «call to
 *    a member function on null» que no menciona la palabra «estado».
 *
 * 2. **La transición de alta**, con `estado_anterior` nulo. «¿Desde cuándo está
 *    abierta?» es la primera pregunta que se hace sobre una no conformidad, y sin
 *    esta fila se contestaría con `created_at`, que es cuándo alguien la escribió
 *    en Statera y no cuándo se abrió.
 *
 * Las dos en la misma transacción: un alta sin su fila de histórico sería un
 * agujero en la traza desde el primer segundo (invariante 7).
 *
 * **Y desde la cláusula 10.1, una puerta más:** un hallazgo de tipo «oportunidad
 * de mejora» no se trata aquí. No incumple nada, así que abrirle una no
 * conformidad lo contaría como incumplimiento en el panel, en el indicador del
 * § 4.14 y en la entrada de la 9.3. La comprobación está en el dominio y no sólo
 * en el `FormRequest` porque vale también para un importador.
 */
final class RegistrarNoConformidad
{
    public function __construct(private readonly RegistroTransicionesNoConformidad $registro) {}

    /**
     * @param  array<string, mixed>  $atributos
     *
     * @throws HallazgoNoTratable
     */
    public function __invoke(array $atributos, ?User $usuario = null, ?string $nota = null): NoConformidad
    {
        $this->comprobarHallazgo($atributos['hallazgo_id'] ?? null);

        return DB::transaction(function () use ($atributos, $usuario, $nota): NoConformidad {
            $noConformidad = NoConformidad::query()->create($atributos);
            $noConformidad->refresh();

            $this->registro->registrar($noConformidad, null, $noConformidad->estado, $usuario, $nota);

            return $noConformidad;
        });
    }

    /**
     * @throws HallazgoNoTratable
     */
    private function comprobarHallazgo(mixed $id): void
    {
        if ($id === null) {
            return;
        }

        // Por el modelo y no por una consulta cruda: así pasa por el scope de
        // organización, y el hallazgo de otro cliente sencillamente no existe.
        $hallazgo = Hallazgo::query()->find($id);

        if ($hallazgo instanceof Hallazgo && ! $hallazgo->tipo->admiteNoConformidad()) {
            throw new HallazgoNoTratable($hallazgo->tipo);
        }
    }
}
