<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La tabla de implantaciones
|--------------------------------------------------------------------------
|
| La vista más ancha del producto, y la que ejercita lo que el recurso de
| Sistemas no toca: orden por un campo del catálogo, columnas ocultas y acción
| masiva delegando en el dominio.
|
*/

/**
 * @return array{usuario: User, implantaciones: array<string, Implantacion>}
 */
function escenarioDeImplantaciones(): array
{
    $organizacion = comoOrganizacion();
    $marco = Marco::factory()->create();
    $sistema = Sistema::factory()->de($organizacion)->conMarco($marco)->create(['codigo' => 'SIS-01']);

    // El orden del catálogo no coincide con el alfabético del código: es
    // justamente lo que distingue ordenar bien de ordenar por la cadena.
    $codigos = ['op.acc.2' => 1, 'op.acc.10' => 2, 'mp.if.1' => 3];
    $implantaciones = [];

    foreach ($codigos as $codigo => $orden) {
        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => $codigo,
            'tipo' => TipoRequisito::Medida->value,
            'orden' => $orden,
        ]);

        $implantaciones[$codigo] = Implantacion::factory()
            ->for($sistema)
            ->create(['requisito_id' => $requisito->id]);
    }

    return [
        'usuario' => User::factory()->create(['organizacion_id' => $organizacion->id]),
        'implantaciones' => $implantaciones,
    ];
}

it('ordena por el orden del catálogo, no por el texto del código', function (): void {
    $escenario = escenarioDeImplantaciones();

    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('implantaciones/Index')
            // Alfabéticamente `op.acc.10` iría antes que `op.acc.2`; por catálogo, no.
            ->where('filas.0.codigo', 'op.acc.2')
            ->where('filas.1.codigo', 'op.acc.10')
            ->where('filas.2.codigo', 'mp.if.1')
            ->where('meta.orden', 'codigo')
        );

    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones?sort=-codigo')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.codigo', 'mp.if.1')
            ->where('meta.orden', '-codigo')
        );
});

it('filtra por el título del requisito, que vive en el catálogo y no aquí', function (): void {
    $escenario = escenarioDeImplantaciones();
    $escenario['implantaciones']['mp.if.1']->requisito->update(['titulo' => 'Copias de seguridad verificadas']);

    // El filtro apunta a `requisitos.titulo`, y sólo puede porque la consulta
    // del recurso ya trae ese join.
    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones?filter[requisito]=copias de seguridad')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 1)
            ->where('filas.0.codigo', 'mp.if.1')
        );
});

it('filtra por marco a través del requisito', function (): void {
    $escenario = escenarioDeImplantaciones();
    $propio = $escenario['implantaciones']['mp.if.1']->requisito?->marco_id;

    $this->actingAs($escenario['usuario'])
        ->get("/implantaciones?filter[marco_id]={$propio}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 3));

    $ajeno = Marco::factory()->create();

    $this->actingAs($escenario['usuario'])
        ->get("/implantaciones?filter[marco_id]={$ajeno->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 0));
});

it('serializa madurez, exigencia y origen de forma legible', function (): void {
    $escenario = escenarioDeImplantaciones();

    $escenario['implantaciones']['op.acc.2']->update([
        'nivel_madurez' => 'l3',
        'exigencia_calculada' => 'R2',
        'origen_exigencia' => 'modulacion_dimension',
    ]);

    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            // La madurez es ordinal: viaja el nivel y el máximo, no un badge.
            ->where('filas.0.nivel_madurez.valor', 3)
            ->where('filas.0.nivel_madurez.de', 5)
            ->where('filas.0.nivel_madurez.corta', 'L3')
            ->where('filas.0.nivel_madurez.etiqueta', 'L3 — Proceso definido')
            // `R2` no significa nada fuera del Anexo II.
            ->where('filas.0.exigencia.etiqueta', 'Refuerzo 2')
            ->where('filas.0.exigencia.tono', 'reforzado')
            ->where('filas.0.origen_exigencia', 'Modulación por dimensión')
        );
});

it('lleva el estado actual y las transiciones posibles en cada fila', function (): void {
    $escenario = escenarioDeImplantaciones();

    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.estado_actual', 'no_iniciado')
            ->where('filas.0.transiciones', ['planificado', 'en_progreso', 'implantado'])
        );
});

it('cambia el estado en bloque y deja rastro del cambio', function (): void {
    $escenario = escenarioDeImplantaciones();
    $ids = collect($escenario['implantaciones'])->pluck('id')->all();

    $this->actingAs($escenario['usuario'])
        ->post('/implantaciones/estado', [
            'implantaciones' => $ids,
            'estado' => 'en_progreso',
            'nota' => 'Arranque del plan de adecuación.',
        ])
        ->assertRedirect();

    $implantaciones = Implantacion::query()->whereIn('id', $ids)->get();

    expect($implantaciones->pluck('estado')->all())->each->toBe(EstadoImplantacion::EnProgreso);

    $transicion = $implantaciones->first()->transiciones()->latest('id')->firstOrFail();

    expect($transicion->estado_anterior)->toBe(EstadoImplantacion::NoIniciado)
        ->and($transicion->estado_nuevo)->toBe(EstadoImplantacion::EnProgreso)
        ->and($transicion->nota)->toBe('Arranque del plan de adecuación.')
        ->and($transicion->usuario_id)->toBe($escenario['usuario']->id);
});

it('no deja fijar `no_aplica` a mano: lo deriva el motor', function (): void {
    $escenario = escenarioDeImplantaciones();

    $this->actingAs($escenario['usuario'])
        ->post('/implantaciones/estado', [
            'implantaciones' => [$escenario['implantaciones']['op.acc.2']->id],
            'estado' => 'no_aplica',
        ])
        ->assertSessionHasErrors('estado');
});

it('deja las filas que no admiten la transición como estaban y lo dice', function (): void {
    $escenario = escenarioDeImplantaciones();

    $puede = $escenario['implantaciones']['op.acc.2'];
    $noPuede = $escenario['implantaciones']['mp.if.1'];
    $noPuede->update(['aplica' => false, 'justificacion' => 'Fuera del alcance.', 'estado' => 'no_aplica']);

    $this->actingAs($escenario['usuario'])
        ->post('/implantaciones/estado', [
            'implantaciones' => [$puede->id, $noPuede->id],
            'estado' => 'implantado',
        ])
        ->assertRedirect();

    expect($puede->fresh()->estado)->toBe(EstadoImplantacion::Implantado)
        ->and($noPuede->fresh()->estado)->toBe(EstadoImplantacion::NoAplica);

    $this->actingAs($escenario['usuario'])
        ->get('/implantaciones')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->hasFlash('exito'));
});
