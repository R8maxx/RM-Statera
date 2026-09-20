<?php

declare(strict_types=1);

use App\Domain\Incidente\Models\Incidente;
use App\Domain\Incidente\Models\IncidenteTransicion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;

/*
|--------------------------------------------------------------------------
| El aislamiento del registro de incidentes
|--------------------------------------------------------------------------
|
| Qué le ha pasado a un cliente y cuándo es de lo más sensible que guarda este
| producto: es información que su propio SGSI clasifica como confidencial, y
| filtrarla entre organizaciones sería el peor fallo posible de la herramienta
| que gestiona su seguridad.
|
| Se responde **404 y no 403**: decir «existe pero no es tuyo» ya sería filtrar.
|
*/

beforeEach(function (): void {
    $this->propia = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->ajena = Organizacion::factory()->create();

    $this->enLaAjena = fn (callable $callback) => app(ContextoOrganizacion::class)
        ->paraOrganizacion($this->ajena, $callback);
});

it('no lista los incidentes de otra organización', function (): void {
    Incidente::factory()->create(['codigo' => 'INC-PROPIO']);

    ($this->enLaAjena)(fn () => Incidente::factory()->create(['codigo' => 'INC-AJENO']));

    app(ContextoOrganizacion::class)->establecer($this->propia);

    expect(Incidente::query()->pluck('codigo')->all())->toBe(['INC-PROPIO']);
});

it('responde 404 en todo lo que cuelga del incidente de otra organización', function (): void {
    $ajeno = ($this->enLaAjena)(fn (): Incidente => Incidente::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)->get("/incidentes/{$ajeno->id}")->assertNotFound();
    $this->actingAs($this->usuario)->get("/incidentes/{$ajeno->id}/editar")->assertNotFound();
    $this->actingAs($this->usuario)->delete("/incidentes/{$ajeno->id}")->assertNotFound();
    $this->actingAs($this->usuario)
        ->post("/incidentes/{$ajeno->id}/estado", ['estado' => 'en_tratamiento'])
        ->assertNotFound();
    $this->actingAs($this->usuario)
        ->put("/incidentes/{$ajeno->id}/leccion", ['leccion_aprendida' => 'Nada.'])
        ->assertNotFound();
    $this->actingAs($this->usuario)
        ->post("/incidentes/{$ajeno->id}/notificaciones", ['destinatario' => 'aepd'])
        ->assertNotFound();
});

it('no deja abrir una no conformidad desde el incidente de otra organización', function (): void {
    $ajeno = ($this->enLaAjena)(fn (): Incidente => Incidente::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)
        ->get("/no-conformidades/crear?incidente={$ajeno->id}")
        ->assertNotFound();

    $this->actingAs($this->usuario)
        ->get("/mejoras/crear?incidente={$ajeno->id}")
        ->assertNotFound();
});

/** El histórico tampoco cruza: lleva `organizacion_id` y su política de RLS. */
it('no lista el histórico de otra organización', function (): void {
    ($this->enLaAjena)(fn () => Incidente::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    expect(IncidenteTransicion::query()->count())->toBe(0);
});
