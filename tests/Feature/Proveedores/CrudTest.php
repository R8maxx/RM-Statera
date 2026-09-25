<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Models\Proveedor;

/*
|--------------------------------------------------------------------------
| El registro de proveedores (§ 4.9)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->datos = fn (array $cambios = []): array => [
        'codigo' => 'PRV-001',
        'nombre' => 'Alojamiento sintético',
        'servicio_prestado' => 'Alojamiento de la sede electrónica.',
        'criticidad_declarada' => 'alta',
        'es_nube' => '1',
        'modelo_nube' => 'iaas',
        'ubicacion_datos' => 'ue_eee',
        'es_subencargado_rgpd' => '1',
        ...$cambios,
    ];
});

it('da de alta un proveedor en evaluación, con su primera transición', function (): void {
    $this->actingAs($this->responsable)->post('/proveedores', ($this->datos)())->assertSessionHasNoErrors();

    $proveedor = Proveedor::query()->where('codigo', 'PRV-001')->firstOrFail();

    expect($proveedor->estado)->toBe(EstadoProveedor::EnEvaluacion)
        ->and($proveedor->es_nube)->toBeTrue()
        ->and($proveedor->transiciones()->count())->toBe(1)
        ->and($proveedor->proxima_evaluacion)->toBeNull();
});

it('sin activos, la criticidad hay que declararla', function (): void {
    $this->actingAs($this->responsable)
        ->post('/proveedores', ($this->datos)(['criticidad_declarada' => '__ninguno__']))
        ->assertSessionHasErrors('criticidad_declarada');

    expect(Proveedor::query()->count())->toBe(0);
});

it('si es nube, dice qué modelo', function (): void {
    $this->actingAs($this->responsable)
        ->post('/proveedores', ($this->datos)(['modelo_nube' => '']))
        ->assertSessionHasErrors('modelo_nube');
});

it('sin nube no guarda modelo, aunque llegue uno', function (): void {
    $this->actingAs($this->responsable)
        ->post('/proveedores', ($this->datos)(['es_nube' => '0', 'modelo_nube' => 'saas']))
        ->assertSessionHasNoErrors();

    expect(Proveedor::query()->firstOrFail()->modelo_nube)->toBeNull();
});

it('el estado no entra por el formulario', function (): void {
    $proveedor = Proveedor::factory()->create();

    $this->actingAs($this->responsable)
        ->put("/proveedores/{$proveedor->id}", ($this->datos)(['estado' => 'homologado']))
        ->assertSessionHasNoErrors();

    expect($proveedor->fresh()?->estado)->toBe(EstadoProveedor::EnEvaluacion);
});

it('retirar pide motivo y deja histórico; reactivar vuelve a en evaluación', function (): void {
    $proveedor = Proveedor::factory()->create();

    $this->actingAs($this->responsable)->post("/proveedores/{$proveedor->id}/retirar", ['motivo' => ''])
        ->assertSessionHasErrors('motivo');

    $this->actingAs($this->responsable)->post("/proveedores/{$proveedor->id}/retirar", ['motivo' => 'Fin del contrato']);
    expect($proveedor->fresh()?->estado)->toBe(EstadoProveedor::Retirado);

    $this->actingAs($this->responsable)->post("/proveedores/{$proveedor->id}/reactivar");
    expect($proveedor->fresh()?->estado)->toBe(EstadoProveedor::EnEvaluacion)
        ->and($proveedor->transiciones()->count())->toBe(2);
});

it('registra y quita certificaciones; la categoría sólo para el ENS', function (): void {
    $proveedor = Proveedor::factory()->create();

    $this->actingAs($this->responsable)
        ->post("/proveedores/{$proveedor->id}/certificaciones", ['tipo' => 'ens'])
        ->assertSessionHasErrors('categoria_ens');

    $this->actingAs($this->responsable)
        ->post("/proveedores/{$proveedor->id}/certificaciones", ['tipo' => 'iso27001', 'categoria_ens' => 'media'])
        ->assertSessionHasErrors('categoria_ens');

    $this->actingAs($this->responsable)
        ->post("/proveedores/{$proveedor->id}/certificaciones", ['tipo' => 'ens', 'categoria_ens' => 'media', 'caduca_en' => today()->addYear()->toDateString()])
        ->assertSessionHasNoErrors();

    $certificacion = $proveedor->certificaciones()->firstOrFail();

    $this->actingAs($this->responsable)
        ->delete("/proveedores/{$proveedor->id}/certificaciones/{$certificacion->id}")
        ->assertRedirect();

    expect($proveedor->certificaciones()->count())->toBe(0);
});

it('una certificación de otro proveedor no se borra desde éste', function (): void {
    $uno = Proveedor::factory()->create();
    $otro = Proveedor::factory()->create();
    $certificacion = $otro->certificaciones()->create(['tipo' => 'iso27001']);

    $this->actingAs($this->responsable)
        ->delete("/proveedores/{$uno->id}/certificaciones/{$certificacion->id}")
        ->assertNotFound();

    expect($certificacion->fresh())->not->toBeNull();
});

it('las pantallas se pintan', function (): void {
    $proveedor = Proveedor::factory()->create();

    $this->actingAs($this->responsable)->get('/proveedores')->assertOk();
    $this->actingAs($this->responsable)->get('/proveedores/crear')->assertOk();
    $this->actingAs($this->responsable)->get("/proveedores/{$proveedor->id}")->assertOk();
    $this->actingAs($this->responsable)->get("/proveedores/{$proveedor->id}/editar")->assertOk();
    $this->actingAs($this->responsable)->get("/proveedores/{$proveedor->id}/evaluar")->assertOk();
});

it('el técnico gestiona y no evalúa; el auditor sólo lee', function (): void {
    $proveedor = Proveedor::factory()->create();
    $tecnico = usuarioCon(Rol::Tecnico);
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($tecnico)->get("/proveedores/{$proveedor->id}/editar")->assertOk();
    $this->actingAs($tecnico)->get("/proveedores/{$proveedor->id}/evaluar")->assertForbidden();
    $this->actingAs($tecnico)->post("/proveedores/{$proveedor->id}/evaluaciones", [])->assertForbidden();

    $this->actingAs($auditor)->get("/proveedores/{$proveedor->id}")->assertOk();
    $this->actingAs($auditor)->get("/proveedores/{$proveedor->id}/editar")->assertForbidden();
});

/** Ver `CuentasAsignables`: el auditor externo no lleva un proveedor. */
it('no admite de responsable a quien no gestiona proveedores', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($this->responsable)
        ->post('/proveedores', ($this->datos)(['responsable_id' => $auditor->id]))
        ->assertSessionHasErrors('responsable_id');

    $this->actingAs($this->responsable)
        ->post('/proveedores', ($this->datos)(['responsable_id' => $this->responsable->id]))
        ->assertSessionHasNoErrors();
});
