<?php

declare(strict_types=1);

use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Models\Documento;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| El aislamiento del BIA
|--------------------------------------------------------------------------
|
| Se responde 404 y no 403, como en el resto del producto: decir «existe pero
| no es tuyo» ya sería filtrar. Y el desplegable de responsables se acota a
| mano, como el de incidentes: `User` no lleva el scope de organización.
|
*/

beforeEach(function (): void {
    $this->propia = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->ajena = Organizacion::factory()->create();

    $this->enLaAjena = fn (callable $callback) => app(ContextoOrganizacion::class)
        ->paraOrganizacion($this->ajena, $callback);
});

it('un BIA de otra organización devuelve 404', function (): void {
    $ajeno = ($this->enLaAjena)(fn (): BiaServicio => BiaServicio::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)->get("/continuidad/bia/{$ajeno->id}")->assertNotFound();
    $this->actingAs($this->usuario)->get("/continuidad/bia/{$ajeno->id}/editar")->assertNotFound();
    $this->actingAs($this->usuario)
        ->put("/continuidad/bia/{$ajeno->id}", ['rto_horas' => 10])
        ->assertNotFound();
    $this->actingAs($this->usuario)
        ->post("/continuidad/bia/{$ajeno->id}/estado", ['estado' => 'aprobado'])
        ->assertNotFound();
});

it('no lista los BIA de otra organización', function (): void {
    BiaServicio::factory()->create();

    ($this->enLaAjena)(fn () => BiaServicio::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    expect(BiaServicio::query()->count())->toBe(1);
});

it('el desplegable de responsables no muestra usuarios de otra organización', function (): void {
    $ajeno = ($this->enLaAjena)(fn (): User => User::factory()->create(['organizacion_id' => $this->ajena->id]));

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $props = $this->actingAs($this->usuario)->get('/continuidad/bia/crear')->viewData('page')['props'];

    expect(collect($props['responsables'])->pluck('valor'))->not->toContain((string) $ajeno->id);
});

it('una prueba de otra organización devuelve 404', function (): void {
    $ajena = ($this->enLaAjena)(fn (): PruebaContinuidad => PruebaContinuidad::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)->get("/continuidad/pruebas/{$ajena->id}")->assertNotFound();
    $this->actingAs($this->usuario)->get("/continuidad/pruebas/{$ajena->id}/editar")->assertNotFound();
    $this->actingAs($this->usuario)
        ->put("/continuidad/pruebas/{$ajena->id}", ['titulo' => 'x'])
        ->assertNotFound();
    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$ajena->id}/resultado", ['fecha_realizacion' => now()->toDateString(), 'resultado' => 'superada'])
        ->assertNotFound();
    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$ajena->id}/cancelar", ['motivo' => 'x'])
        ->assertNotFound();
});

it('rechaza planificar una prueba sobre el plan de otra organización', function (): void {
    $planAjeno = ($this->enLaAjena)(fn (): Documento => Documento::factory()->planContinuidad()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)
        ->post('/continuidad/pruebas', [
            'codigo' => 'PC-2026-99',
            'titulo' => 'Prueba sobre un plan ajeno',
            'documento_id' => $planAjeno->id,
            'tipo' => 'sobremesa',
            'fecha_prevista' => now()->addDays(15)->toDateString(),
            'servicios' => [],
        ])
        ->assertSessionHasErrors('documento_id');

    expect(PruebaContinuidad::query()->count())->toBe(0);
});
