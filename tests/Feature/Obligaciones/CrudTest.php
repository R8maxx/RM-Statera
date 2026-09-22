<?php

declare(strict_types=1);

use App\Domain\Obligacion\AsumirObligacion;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\Obligacion;
use App\Domain\Obligacion\RetirarCompromiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Compromisos: asumir, editar y retirar
|--------------------------------------------------------------------------
|
| Lo que separa este módulo de una lista de recordatorios es que el compromiso
| **no se lee del catálogo cada vez**: se copia al asumirlo. Lo que la
| organización asumió en 2026 no puede repintarse porque el catálogo cambie la
| redacción en 2028, que es el mismo criterio que congela el objetivo de una
| medición y la instantánea de una versión de documento.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('asumir copia el título y la cadencia del catálogo', function (): void {
    $obligacion = Obligacion::factory()->cada(24)->create(['nombre' => 'Renovar la conformidad']);

    $compromiso = app(AsumirObligacion::class)($obligacion, Carbon::parse('2025-03-14'));

    expect($compromiso->titulo)->toBe('Renovar la conformidad')
        ->and($compromiso->periodicidad_meses)->toBe(24)
        ->and($compromiso->obligacion_id)->toBe($obligacion->id)
        ->and($compromiso->computa_desde->toDateString())->toBe('2025-03-14');
});

it('cambiar el catálogo después no repinta lo que ya se asumió', function (): void {
    $obligacion = Obligacion::factory()->cada(12)->create(['nombre' => 'Informe anual']);

    $compromiso = app(AsumirObligacion::class)($obligacion);

    $obligacion->update(['nombre' => 'Informe del estado de la seguridad', 'periodicidad_meses_sugerida' => 24]);

    expect($compromiso->fresh()?->titulo)->toBe('Informe anual')
        ->and($compromiso->fresh()?->periodicidad_meses)->toBe(12);
});

it('la misma obligación no se asume dos veces para el mismo sistema', function (): void {
    $obligacion = Obligacion::factory()->create();

    app(AsumirObligacion::class)($obligacion);

    expect(fn () => app(AsumirObligacion::class)($obligacion))->toThrow(QueryException::class);
});

/**
 * La mitad que un `unique` corriente dejaría pasar: PostgreSQL considera por
 * defecto que dos nulos son distintos, así que sin `NULLS NOT DISTINCT` se podría
 * asumir tres veces «presentar el informe INES» —que no cuelga de ningún
 * sistema— y el calendario pintaría tres chips para un solo compromiso.
 */
it('tampoco dos veces cuando no cuelga de ningún sistema', function (): void {
    $obligacion = Obligacion::factory()->create();

    Compromiso::factory()->deObligacion($obligacion->id)->deSistema(null)->create();

    expect(fn () => Compromiso::factory()->deObligacion($obligacion->id)->deSistema(null)->create())
        ->toThrow(QueryException::class);
});

it('retirar no borra: el histórico se conserva', function (): void {
    $compromiso = Compromiso::factory()->create();
    $compromiso->cumplimientos()->create([
        'fecha' => Carbon::today()->subMonths(2),
        'cubre_hasta' => Carbon::today()->addMonths(10),
    ]);

    app(RetirarCompromiso::class)($compromiso, 'Ya no aplica');

    expect($compromiso->fresh()?->activo)->toBeFalse()
        ->and($compromiso->fresh()?->cumplimientos()->count())->toBe(1);
});

it('un compromiso retirado deja de contar en los scopes del panel', function (): void {
    Compromiso::factory()->vencido()->retirado()->create();

    expect(Compromiso::query()->vencidos()->count())->toBe(0)
        ->and(Compromiso::query()->activos()->count())->toBe(0);
});

it('no cruza la frontera de organización', function (): void {
    $otra = Organizacion::factory()->create();

    app(ContextoOrganizacion::class)->paraOrganizacion($otra, function (): void {
        Compromiso::factory()->create(['titulo' => 'De la otra casa']);
    });

    $this->actingAs($this->usuario)->get('/obligaciones')->assertOk();

    expect(Compromiso::query()->count())->toBe(0);
});

it('un compromiso de otra organización responde 404 y no 403', function (): void {
    $otra = Organizacion::factory()->create();

    $ajeno = app(ContextoOrganizacion::class)->paraOrganizacion(
        $otra,
        fn (): Compromiso => Compromiso::factory()->create(),
    );

    $this->actingAs($this->usuario)->get("/obligaciones/{$ajeno->id}")->assertNotFound();
});
