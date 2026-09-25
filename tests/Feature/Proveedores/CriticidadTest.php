<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Proveedor\Enums\Criticidad;
use App\Domain\Proveedor\Models\Proveedor;

/*
|--------------------------------------------------------------------------
| El mínimo de criticidad derivado de lo que presta (§ 4.9)
|--------------------------------------------------------------------------
|
| Se deriva y no se elige: la valoración más alta de los activos que presta.
| Declararla más alta es libre; más baja, sólo con justificación.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->proveedor = Proveedor::factory()->create(['criticidad_declarada' => null, 'criticidad_derivada' => 'baja']);
});

it('se deriva de la valoración más alta de los activos que presta', function (): void {
    Activo::factory()->create(['proveedor_id' => $this->proveedor->id, 'valor_c' => 'bajo']);
    Activo::factory()->create(['proveedor_id' => $this->proveedor->id, 'valor_d' => 'alto']);

    expect($this->proveedor->fresh()?->criticidad_derivada)->toBe(Criticidad::Alta)
        ->and($this->proveedor->fresh()?->criticidad())->toBe(Criticidad::Alta);
});

it('se recalcula al cambiar la valoración o al quitar el activo', function (): void {
    $activo = Activo::factory()->create(['proveedor_id' => $this->proveedor->id, 'valor_i' => 'medio']);
    expect($this->proveedor->fresh()?->criticidad_derivada)->toBe(Criticidad::Media);

    $activo->update(['valor_i' => 'alto']);
    expect($this->proveedor->fresh()?->criticidad_derivada)->toBe(Criticidad::Alta);

    $activo->update(['proveedor_id' => null]);

    // Sin activos no hay derivada, y la última se congela como declarada para
    // que el proveedor no se quede sin criticidad.
    $fresco = $this->proveedor->fresh();
    expect($fresco?->criticidad_derivada)->toBeNull()
        ->and($fresco?->criticidad_declarada)->toBe(Criticidad::Alta);
});

it('declararla por debajo de sus activos exige justificarlo', function (): void {
    Activo::factory()->create(['proveedor_id' => $this->proveedor->id, 'valor_c' => 'alto']);
    $proveedor = $this->proveedor->fresh();

    $datos = [
        'codigo' => $proveedor->codigo,
        'nombre' => $proveedor->nombre,
        'servicio_prestado' => 'x',
        'ubicacion_datos' => 'ue_eee',
        'criticidad_declarada' => 'baja',
    ];

    $this->actingAs($this->responsable)->put("/proveedores/{$proveedor->id}", $datos)
        ->assertSessionHasErrors('criticidad_declarada');

    $this->actingAs($this->responsable)->put("/proveedores/{$proveedor->id}", [...$datos, 'justificacion_criticidad' => 'Replicado con otro proveedor.'])
        ->assertSessionHasNoErrors();

    expect($proveedor->fresh()?->criticidad())->toBe(Criticidad::Baja)
        ->and($proveedor->fresh()?->rebajaLaDerivada())->toBeTrue();
});

it('declararla por encima es libre', function (): void {
    Activo::factory()->create(['proveedor_id' => $this->proveedor->id, 'valor_c' => 'bajo']);
    $proveedor = $this->proveedor->fresh();

    $this->actingAs($this->responsable)->put("/proveedores/{$proveedor->id}", [
        'codigo' => $proveedor->codigo,
        'nombre' => $proveedor->nombre,
        'servicio_prestado' => 'x',
        'ubicacion_datos' => 'ue_eee',
        'criticidad_declarada' => 'alta',
    ])->assertSessionHasNoErrors();

    expect($proveedor->fresh()?->criticidad())->toBe(Criticidad::Alta);
});
