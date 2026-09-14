<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Activo\Models\Activo;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Riesgo\Excepciones\RiesgoSinActivos;
use App\Domain\Riesgo\Models\Riesgo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un riesgo, con sus activos.
 *
 * **Un riesgo tiene que pesar sobre algo, y eso se comprueba aquí y no en el
 * `FormRequest`.** La regla vale igual para un importador, para el seeder y para
 * cualquier camino futuro, que es el mismo criterio con el que `RegistrarDependencia`
 * rechaza los ciclos del grafo de activos. Un riesgo sin activos no se puede
 * puntuar —el impacto sale de lo que valen— ni se puede tratar: no se sabe qué
 * hay que proteger.
 *
 * No deja transición ni primera valoración: registrar un riesgo y medirlo son dos
 * actos distintos y a menudo separados por días. El riesgo nace **sin valorar**,
 * y `Riesgo::scopeSinValorar()` existe justo para que eso se pueda contar.
 */
final class CrearRiesgo
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    /**
     * @param  array<string, mixed>  $atributos
     * @param  list<Activo>  $activos
     *
     * @throws RiesgoSinActivos
     */
    public function __invoke(array $atributos, array $activos, ?User $autor = null): Riesgo
    {
        if ($activos === []) {
            throw RiesgoSinActivos::alCrear();
        }

        return DB::transaction(function () use ($atributos, $activos, $autor): Riesgo {
            $riesgo = Riesgo::query()->create([
                ...$atributos,
                'codigo' => $atributos['codigo'] ?? $this->siguienteCodigo(),
            ])->refresh();

            $riesgo->activos()->attach(
                array_map(static fn (Activo $activo): int => $activo->id, $activos),
                ['organizacion_id' => $riesgo->organizacion_id, 'vinculado_por_id' => $autor?->id],
            );

            return $riesgo->refresh();
        });
    }

    /**
     * El siguiente `R-nnn` de la organización.
     *
     * Se cuenta sobre el máximo existente y no sobre el total de filas: borrar
     * R-003 y crear otro no debe reutilizar el número, porque el código aparece
     * en informes que ya se han entregado y dos riesgos distintos con el mismo
     * código en dos entregas distintas es un hallazgo.
     *
     * El hueco entre números no se rellena, y es correcto: un salto en la
     * numeración no confunde a nadie, un código repetido sí.
     */
    private function siguienteCodigo(): string
    {
        $ultimo = Riesgo::query()
            ->where('organizacion_id', $this->contexto->idObligatorio())
            ->selectRaw("MAX(NULLIF(regexp_replace(codigo, '\\D', '', 'g'), '')::int) AS maximo")
            ->value('maximo');

        return sprintf('R-%03d', ((int) $ultimo) + 1);
    }
}
