<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Aislamiento a través de la capa de recursos
|--------------------------------------------------------------------------
|
| Prioridad 2 de la cobertura: que ninguna consulta cruce la frontera de
| organización. El scope y la política de RLS ya tienen sus propios tests; lo
| que se comprueba aquí es que la capa de recursos —filtros, ordenación,
| acciones de fila y acciones masivas— no abre ninguna puerta nueva por HTTP.
|
| Los identificadores de la organización ajena se capturan con su contexto
| puesto, nunca con `withoutGlobalScopes()`: está prohibido en el proyecto y,
| además, la política de PostgreSQL tampoco lo dejaría pasar.
|
*/

/**
 * @return array{usuario: User, ajena: Organizacion, sistemaPropio: int, sistemaAjeno: int, implantacionAjena: int}
 */
function escenarioDeDosOrganizaciones(): array
{
    $marco = Marco::factory()->create();

    $propia = Organizacion::factory()->create(['nombre' => 'Propia']);
    $ajena = Organizacion::factory()->create(['nombre' => 'Ajena']);

    comoOrganizacion($propia);
    $sistemaPropio = Sistema::factory()->de($propia)->conMarco($marco)
        ->create(['codigo' => 'PRO-01', 'nombre' => 'Propio']);
    Implantacion::factory()->for($sistemaPropio)->create();
    $usuario = usuarioCon(organizacion: $propia);

    comoOrganizacion($ajena);
    $sistemaAjeno = Sistema::factory()->de($ajena)->conMarco($marco)
        ->create(['codigo' => 'AJE-01', 'nombre' => 'Ajeno']);
    $implantacionAjena = Implantacion::factory()->for($sistemaAjeno)->create();

    sinOrganizacion();

    return [
        'usuario' => $usuario,
        'ajena' => $ajena,
        'sistemaPropio' => $sistemaPropio->id,
        'sistemaAjeno' => $sistemaAjeno->id,
        'implantacionAjena' => $implantacionAjena->id,
    ];
}

it('el índice sólo devuelve filas de la organización del usuario', function (): void {
    $escenario = escenarioDeDosOrganizaciones();

    $this->actingAs($escenario['usuario'])
        ->get('/sistemas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 1)
            ->where('filas.0.codigo', 'PRO-01')
            ->where('meta.total', 1)
        );
});

it('no deja alcanzar filas ajenas filtrando por su nombre', function (): void {
    $escenario = escenarioDeDosOrganizaciones();

    $this->actingAs($escenario['usuario'])
        ->get('/sistemas?filter[nombre]=Ajeno')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 0)->where('meta.total', 0));
});

it('no deja alcanzar filas ajenas filtrando por el identificador de su sistema', function (): void {
    $escenario = escenarioDeDosOrganizaciones();

    $this->actingAs($escenario['usuario'])
        ->get("/implantaciones?filter[sistema_id]={$escenario['sistemaAjeno']}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 0)->where('meta.total', 0));
});

it('las opciones de los filtros tampoco enumeran datos de otra organización', function (): void {
    $escenario = escenarioDeDosOrganizaciones();

    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones')
        ->assertInertia(function (AssertableInertia $pagina): void {
            // El filtro de sistema sólo enumera el propio.
            $filtro = filtroDeclarado($pagina, 'sistema_id');

            expect($filtro['opciones'])->toHaveCount(1)
                ->and($filtro['opciones'][0]['etiqueta'])->toBe('PRO-01 — Propio');
        });
});

it('editar o borrar un sistema de otra organización devuelve 404, no 403', function (): void {
    $escenario = escenarioDeDosOrganizaciones();

    // La comprobación sólo vale si el sistema PROPIO sí se alcanza: si todo
    // diera 404 —por ejemplo, porque el binding se resuelve antes de fijar el
    // contexto— este test pasaría sin demostrar nada.
    $this->actingAs($escenario['usuario'])
        ->get("/sistemas/{$escenario['sistemaPropio']}/editar")
        ->assertOk();

    // 404 y no 403: confirmar que el recurso existe ya sería filtrar información.
    $this->actingAs($escenario['usuario'])
        ->get("/sistemas/{$escenario['sistemaAjeno']}/editar")
        ->assertNotFound();

    $this->actingAs($escenario['usuario'])
        ->delete("/sistemas/{$escenario['sistemaAjeno']}")
        ->assertNotFound();
});

it('la acción masiva no toca implantaciones de otra organización', function (): void {
    $escenario = escenarioDeDosOrganizaciones();

    $this->actingAs($escenario['usuario'])
        ->post('/implantaciones/estado', [
            'implantaciones' => [$escenario['implantacionAjena']],
            'estado' => 'implantado',
        ])
        // La validación `exists` no ve la fila ajena, así que ni siquiera llega
        // al dominio: rebota como error de validación.
        ->assertSessionHasErrors('implantaciones.0');

    comoOrganizacion($escenario['ajena']);

    expect(Implantacion::query()->findOrFail($escenario['implantacionAjena'])->estado->value)
        ->toBe('no_iniciado');
});

it('un usuario sin organización no llega ni a la tabla', function (): void {
    escenarioDeDosOrganizaciones();

    $huerfano = User::factory()->create(['organizacion_id' => null]);

    /*
     * Le paran dos capas, y en este orden. La primera es el RBAC: los roles van
     * por organización (`teams = true` con `organizacion_id`), así que quien no
     * tiene organización no puede tener ningún rol y no tiene ningún permiso.
     *
     * La segunda —el scope, que sin contexto no devuelve fila alguna— sigue
     * probada aparte en `tests/Feature/Organizacion/AislamientoTest.php`: que
     * el permiso corte antes no puede ser el motivo de dejar de comprobarla,
     * porque un permiso mal dado no debe poder abrir la puerta del tenant.
     */
    $this->actingAs($huerfano)
        ->get('/sistemas')
        ->assertForbidden();
});
