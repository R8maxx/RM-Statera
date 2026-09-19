<?php

declare(strict_types=1);

use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| El aislamiento del cuadro de indicadores
|--------------------------------------------------------------------------
|
| Una serie histórica filtrada dice cuánto ha mejorado un competidor y a qué
| ritmo. Es información de negocio, no un descuido de privacidad.
|
| Se responde **404 y no 403**: decir «existe pero no es tuyo» ya sería filtrar
| información, y es la regla de todo el producto.
|
*/

beforeEach(function (): void {
    $this->propia = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->ajena = Organizacion::factory()->create();
});

it('no lista los indicadores de otra organización', function (): void {
    Indicador::factory()->create(['codigo' => 'IND-PROPIO']);

    app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->ajena,
        fn () => Indicador::factory()->create(['codigo' => 'IND-AJENO']),
    );

    app(ContextoOrganizacion::class)->establecer($this->propia);

    expect(Indicador::query()->pluck('codigo')->all())->toBe(['IND-PROPIO']);
});

it('responde 404 al pedir el indicador de otra organización', function (): void {
    $ajeno = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->ajena,
        fn (): Indicador => Indicador::factory()->create(),
    );

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)->get("/indicadores/{$ajeno->id}")->assertNotFound();
    $this->actingAs($this->usuario)->post("/indicadores/{$ajeno->id}/medicion")->assertNotFound();
});

/**
 * `mediciones` lleva su propia RLS aunque cuelgue de `indicadores`: es la que se
 * olvida, y una serie es tan confidencial como el indicador que la sostiene.
 */
it('no deja ver ni contar las mediciones de otra organización', function (): void {
    app(ContextoOrganizacion::class)->paraOrganizacion($this->ajena, function (): void {
        $indicador = Indicador::factory()->create();

        // Periodos distintos: un indicador mide un periodo una vez, y el índice
        // único lo impone. Tres filas del mismo trimestre no son una serie.
        foreach (['2026-01-15', '2026-04-15', '2026-07-15'] as $dia) {
            Medicion::factory()->for($indicador)->enPeriodo(Carbon::parse($dia))->create();
        }
    });

    app(ContextoOrganizacion::class)->establecer($this->propia);

    expect(Medicion::query()->count())->toBe(0);
});

/**
 * El eje que las tres capas no tapan: dos indicadores de la **misma**
 * organización. `scopeBindings()` es lo que impide borrar desde uno la medición
 * del otro, y es la misma guarda que lleva la checklist de una auditoría.
 */
it('no borra desde un indicador la medición de otro de la misma organización', function (): void {
    $uno = Indicador::factory()->create();
    $otro = Indicador::factory()->create();

    $medicion = Medicion::factory()->for($otro)->create();

    $this->actingAs($this->usuario)
        ->delete("/indicadores/{$uno->id}/mediciones/{$medicion->id}")
        ->assertNotFound();

    expect(Medicion::query()->whereKey($medicion->id)->exists())->toBeTrue();
});
