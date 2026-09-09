<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Las cifras del panel
|--------------------------------------------------------------------------
|
| El panel es lo primero que se mira y lo que se enseña en una reunión, así que
| una cifra mal contada aquí se propaga a una decisión. Lo que se comprueba es
| que sólo cuenta lo exigible, que el reparto por estado es el real y que la
| madurez media viaja con el denominador sobre el que se calcula.
|
*/

/**
 * @return array{usuario: User, ens: Marco, iso: Marco}
 */
function escenarioDePanel(): array
{
    $organizacion = comoOrganizacion();

    $ens = Marco::factory()->create(['codigo' => 'ENS', 'nombre' => 'Esquema Nacional de Seguridad']);
    $iso = Marco::factory()->create(['codigo' => 'ISO27001', 'nombre' => 'ISO/IEC 27001']);
    $sistema = Sistema::factory()->de($organizacion)->conMarco($ens)->create(['codigo' => 'SIS-01']);

    $orden = 0;

    $crear = function (Marco $marco, array $atributos) use ($sistema, &$orden): void {
        $orden++;

        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => "req.{$orden}",
            'tipo' => TipoRequisito::Medida->value,
            'orden' => $orden,
        ]);

        Implantacion::factory()
            ->for($sistema)
            ->create(['requisito_id' => $requisito->id, ...$atributos]);
    };

    $crear($ens, ['estado' => 'implantado', 'nivel_madurez' => 'l4']);
    $crear($ens, ['estado' => 'implantado', 'nivel_madurez' => 'l2']);
    $crear($ens, ['estado' => 'en_progreso']);
    $crear($ens, ['estado' => 'no_iniciado']);
    $crear($iso, ['estado' => 'implantado', 'nivel_madurez' => 'l1']);
    $crear($iso, ['estado' => 'planificado']);

    // Excluida: no se le exige al sistema, así que no cuenta en ninguna cifra.
    $crear($ens, ['estado' => 'no_aplica', 'aplica' => false, 'justificacion' => 'Fuera del alcance.']);

    return [
        'usuario' => usuarioCon(),
        'ens' => $ens,
        'iso' => $iso,
    ];
}

it('reparte por estado en el orden que fija el dominio y sin lo que no aplica', function (): void {
    $escenario = escenarioDePanel();

    $this->actingAs($escenario['usuario'])
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('Panel')
            ->has('porEstado', 4)
            // El orden separa el verde del ámbar: pegados no se distinguen con
            // protanopia. Es una decisión medida, no el orden del enum.
            ->where('porEstado.0.clave', 'implantado')
            ->where('porEstado.0.valor', 3)
            ->where('porEstado.1.clave', 'planificado')
            ->where('porEstado.1.valor', 1)
            ->where('porEstado.2.clave', 'en_progreso')
            ->where('porEstado.2.valor', 1)
            ->where('porEstado.3.clave', 'no_iniciado')
            ->where('porEstado.3.valor', 1)
        );
});

it('cuenta el avance de cada marco por separado', function (): void {
    $escenario = escenarioDePanel();

    $this->actingAs($escenario['usuario'])
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('porMarco', 2)
            // Ordenados por nombre, y la excluida no engorda el denominador.
            ->where('porMarco.0.codigo', 'ENS')
            ->where('porMarco.0.aplicables', 4)
            ->where('porMarco.0.implantadas', 2)
            ->where('porMarco.1.codigo', 'ISO27001')
            ->where('porMarco.1.aplicables', 2)
            ->where('porMarco.1.implantadas', 1)
        );
});

it('da la madurez media con el número de requisitos sobre los que se calcula', function (): void {
    $escenario = escenarioDePanel();

    $this->actingAs($escenario['usuario'])
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            // (4 + 2 + 1) / 3 = 2,333…, redondeado a un decimal. Sólo cuentan
            // las tres que alguien valoró, no las siete que hay.
            ->where('resumen.madurezMedia', 2.3)
            ->where('resumen.madurezEvaluadas', 3)
            ->where('resumen.aplicables', 6)
            ->where('resumen.implantadas', 3)
            ->where('resumen.pendientes', 3)
        );
});

it('distingue una madurez sin valorar de una madurez cero', function (): void {
    $organizacion = comoOrganizacion();
    $usuario = usuarioCon();

    $this->actingAs($usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('resumen.madurezMedia', null)
            ->where('resumen.madurezEvaluadas', 0)
        );
});
