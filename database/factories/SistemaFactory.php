<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\PerfilCumplimiento;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Datos sintéticos. Ni un sistema ni un dato real de ningún cliente.
 *
 * **`organizacion_id` no se declara aquí, y eso no es un olvido.** Lo rellena el
 * evento `creating` de `PerteneceAOrganizacion` con la organización del contexto,
 * que es lo que hacen las otras veinte factories del repositorio.
 *
 * Declararlo lo rompía todo por un camino que no menciona la palabra
 * «organización»: el trait sólo rellena si el atributo viene a nulo, así que un
 * valor puesto aquí lo cortocircuita, la fila nace con un tenant distinto del que
 * fijó `comoOrganizacion()` y el `WITH CHECK` de la política RLS la rechaza con un
 * error de privilegios. Nueve tests de riesgos llevaban en rojo desde que se
 * escribieron por esto, y la barrera estaba haciendo exactamente su trabajo.
 *
 * Efecto secundario deseado: sin contexto, ahora salta la excepción de
 * `ContextoOrganizacion::idObligatorio()`, que dice la verdad, en vez de crearse
 * una organización huérfana en silencio.
 *
 * @extends Factory<Sistema>
 */
class SistemaFactory extends Factory
{
    protected $model = Sistema::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marco_id' => Marco::factory(),
            'codigo' => 'SIS-'.fake()->unique()->numerify('####'),
            'nombre' => 'Sistema '.fake()->word(),
            'descripcion' => null,
            'estado' => EstadoSistema::Activo->value,
            'alcance_declarado' => null,
            'exclusiones_justificadas' => null,
            'perfil_id' => null,
        ];
    }

    public function de(Organizacion $organizacion): self
    {
        return $this->state(fn (): array => ['organizacion_id' => $organizacion->id]);
    }

    public function conMarco(Marco $marco): self
    {
        return $this->state(fn (): array => ['marco_id' => $marco->id]);
    }

    public function conPerfil(PerfilCumplimiento $perfil): self
    {
        return $this->state(fn (): array => ['perfil_id' => $perfil->id]);
    }
}
