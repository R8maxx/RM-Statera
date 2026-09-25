<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Panel\AlertasDelPanel;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;

/*
|--------------------------------------------------------------------------
| Las costuras del módulo de proveedores (§ 4.9)
|--------------------------------------------------------------------------
|
| La tarea que nace de un proveedor, el activo que lo nombra y el rojo que sube
| al panel.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->proveedor = Proveedor::factory()->create();
});

it('abre una tarea con origen proveedor y enlazada', function (): void {
    $this->actingAs($this->responsable)
        ->post("/proveedores/{$this->proveedor->id}/tareas", ['titulo' => 'Firmar el encargo', 'prioridad' => 'alta'])
        ->assertSessionHasNoErrors();

    $tarea = Tarea::query()->firstOrFail();

    expect($tarea->origen)->toBe(OrigenTarea::Proveedor)
        ->and($this->proveedor->tareas()->pluck('tareas.id')->all())->toBe([$tarea->id]);
});

it('la ficha del activo enseña quién lo presta', function (): void {
    $activo = Activo::factory()->create(['proveedor_id' => $this->proveedor->id]);

    $this->actingAs($this->responsable)->get("/activos/{$activo->id}")
        ->assertInertia(fn ($pagina) => $pagina->where('activo.proveedor.id', $this->proveedor->id));
});

it('una reevaluación vencida y un certificado caducado suben al panel en rojo', function (): void {
    $this->proveedor->forceFill(['estado' => 'homologado', 'proxima_evaluacion' => today()->subDay()])->save();
    $this->proveedor->certificaciones()->create(['tipo' => 'iso27001', 'caduca_en' => today()->subDays(3)]);

    $claves = collect(app(AlertasDelPanel::class)($this->responsable))->pluck('clave')->all();

    expect($claves)->toContain('reevaluacion_vencida')->toContain('certificacion_caducada');
});

it('lo retirado no sube al panel aunque tenga fecha pasada', function (): void {
    $this->proveedor->forceFill(['estado' => 'retirado', 'proxima_evaluacion' => today()->subDay()])->save();

    $claves = collect(app(AlertasDelPanel::class)($this->responsable))->pluck('clave')->all();

    expect($claves)->not->toContain('reevaluacion_vencida');
});

it('el filtro de la tabla cuenta lo mismo que el indicador del panel', function (): void {
    $this->proveedor->forceFill(['estado' => 'homologado', 'proxima_evaluacion' => today()->subDay()])->save();
    Proveedor::factory()->create();

    $this->actingAs($this->responsable)->get('/proveedores?filter[reevaluacion_vencida]=1')
        ->assertInertia(fn ($pagina) => $pagina->where('meta.total', 1));
});
