<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;

/*
|--------------------------------------------------------------------------
| El plan de continuidad como documento
|--------------------------------------------------------------------------
|
| `plan_continuidad` es un tipo más de la familia de los escritos —como
| `procedimiento`—, y lo único que le añade es la relación N:M con los
| servicios que cubre. Las dos fronteras —el documento tiene que ser un plan,
| el activo tiene que ser un servicio— se comprueban en el dominio y no sólo
| en el `FormRequest`.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('vincula un servicio a un plan de continuidad', function (): void {
    $plan = Documento::factory()->planContinuidad()->create();
    $servicio = Activo::factory()->deTipo(TipoActivo::Servicios)->create();

    $this->actingAs($this->usuario)
        ->post("/documentos/{$plan->id}/servicios", ['activo_id' => $servicio->id])
        ->assertRedirect();

    expect($plan->serviciosCubiertos()->pluck('activos.id'))->toEqual(collect([$servicio->id]));
});

it('rechaza vincular un servicio a un procedimiento', function (): void {
    $procedimiento = Documento::factory()->deTipo(TipoDocumento::Procedimiento)->create([
        'sistema_id' => null,
    ]);
    $servicio = Activo::factory()->deTipo(TipoActivo::Servicios)->create();

    $this->actingAs($this->usuario)
        ->post("/documentos/{$procedimiento->id}/servicios", ['activo_id' => $servicio->id])
        ->assertSessionHasErrors('activo_id');

    expect($procedimiento->serviciosCubiertos()->count())->toBe(0);
});

it('rechaza un activo que no es servicio', function (): void {
    $plan = Documento::factory()->planContinuidad()->create();
    $servidor = Activo::factory()->deTipo(TipoActivo::Hardware)->create();

    $this->actingAs($this->usuario)
        ->post("/documentos/{$plan->id}/servicios", ['activo_id' => $servidor->id])
        ->assertSessionHasErrors('activo_id');

    expect($plan->serviciosCubiertos()->count())->toBe(0);
});

it('vincular el mismo servicio dos veces es idempotente', function (): void {
    $plan = Documento::factory()->planContinuidad()->create();
    $servicio = Activo::factory()->deTipo(TipoActivo::Servicios)->create();

    $this->actingAs($this->usuario)
        ->post("/documentos/{$plan->id}/servicios", ['activo_id' => $servicio->id])
        ->assertRedirect();

    $this->actingAs($this->usuario)
        ->post("/documentos/{$plan->id}/servicios", ['activo_id' => $servicio->id])
        ->assertRedirect();

    expect($plan->serviciosCubiertos()->count())->toBe(1);
});

it('desvincula un servicio del plan', function (): void {
    $plan = Documento::factory()->planContinuidad()->create();
    $servicio = Activo::factory()->deTipo(TipoActivo::Servicios)->create();

    $plan->serviciosCubiertos()->attach($servicio->id, ['organizacion_id' => $plan->organizacion_id]);

    $this->actingAs($this->usuario)
        ->delete("/documentos/{$plan->id}/servicios/{$servicio->id}")
        ->assertRedirect();

    expect($plan->serviciosCubiertos()->count())->toBe(0);
});

it('la ficha del BIA lista el plan que cubre su servicio', function (): void {
    $servicio = Activo::factory()->deTipo(TipoActivo::Servicios)->create();
    $bia = BiaServicio::factory()->deActivo($servicio)->create();
    $plan = Documento::factory()->planContinuidad()->create();

    $plan->serviciosCubiertos()->attach($servicio->id, ['organizacion_id' => $plan->organizacion_id]);

    $props = $this->actingAs($this->usuario)
        ->get("/continuidad/bia/{$bia->id}")
        ->viewData('page')['props'];

    expect($props['planes'])->toHaveCount(1)
        ->and($props['planes'][0]['id'])->toBe($plan->id)
        ->and($props['planes'][0]['codigo'])->toBe($plan->codigo)
        ->and($props['planes'][0]['aprobado'])->toBeFalse();
});
