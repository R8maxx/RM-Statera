<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Excepciones\ServicioNoValido;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Da de alta el BIA de un servicio, con su primera transición.
 *
 * **Comprueba que el activo es un servicio antes de escribir nada**, y lo hace
 * en el dominio y no sólo en el `FormRequest`: la regla vale igual para un
 * importador. La base no puede imponerla con un `CHECK` —comprobar una
 * columna de otra tabla desde una restricción no es portable—, así que aquí es
 * donde vive. **Y que no tenga ya su BIA**: eso sí lo impone la base, con el
 * índice único, pero con un `QueryException` que no dice qué pasa.
 *
 * El `refresh()` no es opcional: `estado` lo pone la base con su valor por
 * defecto y la instancia recién creada llega sin él, así que lo primero que
 * lea `$bia->estado` revienta con un error que no menciona la palabra
 * «estado». Es lo mismo que ya le pasó a `RegistrarIncidente`.
 */
final class RegistrarBia
{
    public function __construct(private readonly RegistroTransicionesBia $registro) {}

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function __invoke(array $atributos, ?User $usuario = null, ?string $nota = null): BiaServicio
    {
        $this->exigirServicio($atributos);

        return DB::transaction(function () use ($atributos, $usuario, $nota): BiaServicio {
            $bia = BiaServicio::query()->create($atributos);
            $bia->refresh();

            $this->registro->registrar($bia, null, $bia->estado, $usuario, $nota);

            return $bia;
        });
    }

    /**
     * @param  array<string, mixed>  $atributos
     *
     * @throws ServicioNoValido
     */
    private function exigirServicio(array $atributos): void
    {
        $activo = Activo::query()->find($atributos['activo_id'] ?? null);

        if (! $activo instanceof Activo || $activo->tipo !== TipoActivo::Servicios) {
            throw ServicioNoValido::noEsServicio();
        }

        // Un servicio, un BIA. El índice único `(organizacion_id, activo_id)`
        // sigue siendo la última línea; esto lo dice antes y con nombre.
        if (BiaServicio::query()->where('activo_id', $activo->id)->exists()) {
            throw ServicioNoValido::yaTieneBia();
        }
    }
}
