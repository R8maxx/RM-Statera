<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Implantacion\ResumenCumplimiento;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\VincularTarea;
use Inertia\Testing\AssertableInertia;

/**
 * La cifra del panel y el filtro de la tabla cuentan lo mismo.
 *
 * Es la regla que ya rige en el inventario y en el plan de acción, y que aquí
 * faltaba: «Pendientes» era una cifra del panel que no se podía pulsar, y llegar
 * a esa lista exigía marcar a mano «aplica» y tres de los cuatro estados. Con la
 * condición escrita dos veces, el día que cambie una el panel dice 12 y la lista
 * enseña 9, y a partir de ahí nadie se fía del panel.
 *
 * El molde es `ResumenInventarioTest`, que recorre sus nueve indicadores
 * comparando cada uno con su filtro.
 */
function escenarioPendientes(): array
{
    $organizacion = comoOrganizacion();
    $marco = Marco::factory()->create();
    $sistema = Sistema::factory()->de($organizacion)->conMarco($marco)->create();

    $crear = function (string $codigo, int $orden, array $atributos) use ($marco, $sistema): Implantacion {
        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => $codigo,
            'tipo' => TipoRequisito::Medida->value,
            'orden' => $orden,
        ]);

        return Implantacion::factory()->for($sistema)->create([
            'requisito_id' => $requisito->id,
            ...$atributos,
        ]);
    };

    return [
        'usuario' => usuarioCon(),
        // Una implantada: no está pendiente.
        'implantada' => $crear('op.acc.1', 1, ['estado' => EstadoImplantacion::Implantado->value]),
        // Una excluida: `aplica = false` va con `no_aplica`, y eso tampoco es
        // trabajo pendiente. Lo garantiza el `CHECK` de la tabla.
        'excluida' => $crear('op.acc.2', 2, [
            'aplica' => false,
            'estado' => EstadoImplantacion::NoAplica->value,
            'justificacion' => 'El sistema no almacena información de ese tipo.',
        ]),
        'sinEmpezar' => $crear('op.acc.3', 3, ['estado' => EstadoImplantacion::NoIniciado->value]),
        'enMarcha' => $crear('op.acc.4', 4, ['estado' => EstadoImplantacion::EnProgreso->value]),
        'conFechaPasada' => $crear('op.acc.5', 5, [
            'estado' => EstadoImplantacion::Planificado->value,
            'fecha_objetivo' => now()->subMonth()->toDateString(),
        ]),
    ];
}

it('cuenta como pendiente lo exigible que no está implantado', function (): void {
    escenarioPendientes();

    // Tres pendientes de cinco: fuera la implantada y fuera la excluida.
    expect(app(ResumenCumplimiento::class)->pendientes())->toBe(3)
        ->toBe(Implantacion::query()->pendientes()->count());
});

it('el filtro de la tabla enseña exactamente lo que cuenta el panel', function (): void {
    $escenario = escenarioPendientes();

    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones?filter[pendientes]=1')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', app(ResumenCumplimiento::class)->pendientes())
            ->where('meta.total', 3)
        );
});

it('señala lo que se pasó de la fecha que alguien se puso', function (): void {
    $escenario = escenarioPendientes();

    expect(Implantacion::query()->objetivoVencido()->count())->toBe(1);

    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones?filter[objetivo_vencido]=1')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 1)
            ->where('filas.0.codigo', 'op.acc.5')
        );
});

it('no cuenta como implantada una medida con la fecha pasada', function (): void {
    // Una implantada con fecha objetivo antigua no está fuera de plazo: está
    // hecha. Sin el `pendientes()` de dentro del scope, saldría en la lista.
    $escenario = escenarioPendientes();
    $escenario['implantada']->update(['fecha_objetivo' => now()->subYear()->toDateString()]);

    expect(Implantacion::query()->objetivoVencido()->count())->toBe(1);
});

it('separa lo que no tiene ninguna tarea abierta detrás', function (): void {
    $escenario = escenarioPendientes();

    $vincular = app(VincularTarea::class);
    $vincular->vincular(Tarea::factory()->create(), $escenario['sinEmpezar'], null);

    // Y una cerrada no cuenta: el trabajo hecho no es trabajo planificado.
    $vincular->vincular(
        Tarea::factory()->enEstado(EstadoTarea::Hecha)->create(),
        $escenario['enMarcha'],
        null,
    );

    expect(Implantacion::query()->sinTrabajo()->count())->toBe(2);

    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones?filter[sin_trabajo]=1')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));
});
