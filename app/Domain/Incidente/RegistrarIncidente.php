<?php

declare(strict_types=1);

namespace App\Domain\Incidente;

use App\Domain\Incidente\Models\Incidente;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta un incidente, con su primera transición.
 *
 * El `refresh()` no es opcional: `estado`, `clasificacion` y `peligrosidad` los
 * pone la base con su valor por defecto y la instancia recién creada llega sin
 * ellos, así que lo primero que lea `$incidente->estado` revienta con un «call to
 * a member function on null» que no menciona la palabra «estado». Es lo mismo que
 * ya le pasó a `CrearTarea`, a `RegistrarAuditoria` y a
 * `GenerarDocumento::encolar()`.
 */
final class RegistrarIncidente
{
    public function __construct(private readonly RegistroTransicionesIncidente $registro) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos, ?User $usuario = null, ?string $nota = null): Incidente
    {
        return DB::transaction(function () use ($atributos, $usuario, $nota): Incidente {
            $incidente = Incidente::query()->create($atributos);
            $incidente->refresh();

            $this->registro->registrar($incidente, null, $incidente->estado, $usuario, $nota);

            return $incidente;
        });
    }
}
