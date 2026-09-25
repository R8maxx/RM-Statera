<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Proveedor\Models\Proveedor;

/*
|--------------------------------------------------------------------------
| Los proveedores no cruzan de organización
|--------------------------------------------------------------------------
|
| Con quién trabaja un cliente y qué le falla en el contrato dice por dónde
| entrar. Se responde 404 y no 403.
|
*/

beforeEach(function (): void {
    $this->propia = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->ajena = Organizacion::factory()->create();
});

it('no lista ni abre los proveedores de otra organización', function (): void {
    Proveedor::factory()->create(['codigo' => 'PRV-PROPIO']);

    $ajeno = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->ajena,
        fn (): Proveedor => Proveedor::factory()->create(['codigo' => 'PRV-AJENO']),
    );

    comoOrganizacion($this->propia);

    expect(Proveedor::query()->pluck('codigo')->all())->toBe(['PRV-PROPIO']);

    $this->actingAs($this->usuario)->get("/proveedores/{$ajeno->id}")->assertNotFound();
    $this->actingAs($this->usuario)->get("/proveedores/{$ajeno->id}/editar")->assertNotFound();
    $this->actingAs($this->usuario)->get("/proveedores/{$ajeno->id}/evaluar")->assertNotFound();
    $this->actingAs($this->usuario)->post("/proveedores/{$ajeno->id}/retirar", ['motivo' => 'x'])->assertNotFound();
    $this->actingAs($this->usuario)->post("/proveedores/{$ajeno->id}/certificaciones", ['tipo' => 'iso27001'])->assertNotFound();
    $this->actingAs($this->usuario)->post("/proveedores/{$ajeno->id}/tareas", ['titulo' => 'x', 'prioridad' => 'media'])->assertNotFound();
});

it('un activo no se puede asignar a un proveedor de otra organización', function (): void {
    $ajeno = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->ajena,
        fn (): Proveedor => Proveedor::factory()->create(),
    );

    comoOrganizacion($this->propia);

    $this->actingAs($this->usuario)->post('/activos', [
        'codigo' => 'ACT-9001',
        'nombre' => 'Servidor',
        'tipo' => 'hardware',
        'proveedor_id' => $ajeno->id,
    ])->assertSessionHasErrors('proveedor_id');
});
