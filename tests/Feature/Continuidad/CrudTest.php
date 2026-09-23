<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Models\BiaServicio;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El BIA por la interfaz
|--------------------------------------------------------------------------
|
| **Tres verbos y el tercero de supervisión.** `continuidad.ver` lee,
| `continuidad.gestionar` registra y edita, y `continuidad.aprobar` es lo
| único que el técnico no tiene: aceptar un RTO es aceptar un riesgo.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('lista, crea y enseña un BIA', function (): void {
    $servicio = Activo::factory()->create(['tipo' => 'servicios']);

    $this->actingAs($this->usuario)
        ->post('/continuidad/bia', BiaServicio::factory()->raw(['activo_id' => $servicio->id]))
        ->assertRedirect();

    $this->actingAs($this->usuario)
        ->get('/continuidad/bia')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->component('continuidad/bia/Index'));

    $bia = BiaServicio::query()->sole();

    $this->actingAs($this->usuario)
        ->get("/continuidad/bia/{$bia->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('continuidad/bia/Ficha')
            ->has('umbral')
            ->has('dependencias'));
});

it('el técnico no aprueba un BIA', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $bia = BiaServicio::factory()->create();

    $this->actingAs($tecnico)
        ->post("/continuidad/bia/{$bia->id}/estado", ['estado' => 'aprobado'])
        ->assertForbidden();
});

it('el auditor ve pero no escribe', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $bia = BiaServicio::factory()->create();

    $this->actingAs($auditor)->get('/continuidad/bia')->assertOk();
    $this->actingAs($auditor)->get("/continuidad/bia/{$bia->id}")->assertOk();
    $this->actingAs($auditor)
        ->post("/continuidad/bia/{$bia->id}/estado", ['estado' => 'aprobado'])
        ->assertForbidden();
});

it('rechaza un activo que no es servicio en el formulario', function (): void {
    $servidor = Activo::factory()->create(['tipo' => 'hardware']);

    $this->actingAs($this->usuario)
        ->post('/continuidad/bia', BiaServicio::factory()->raw(['activo_id' => $servidor->id]))
        ->assertSessionHasErrors('activo_id');
});

it('edita un BIA sólo con los campos de contenido y lo deja en borrador', function (): void {
    $bia = BiaServicio::factory()->enEstado(EstadoBia::Aprobado)->create();

    $this->actingAs($this->usuario)
        ->put("/continuidad/bia/{$bia->id}", [
            'impacto_4h' => 'bajo',
            'impacto_1d' => 'bajo',
            'impacto_3d' => 'bajo',
            'impacto_1s' => 'bajo',
            'impacto_1m' => 'bajo',
            'rto_horas' => 48,
            'rpo_horas' => 8,
        ])
        ->assertRedirect();

    expect($bia->fresh()->estado)->toBe(EstadoBia::Borrador)
        ->and($bia->fresh()->rto_horas)->toBe(48);
});
