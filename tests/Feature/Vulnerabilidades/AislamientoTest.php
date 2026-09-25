<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Usuario\Models\CuentaSistema;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;

/*
|--------------------------------------------------------------------------
| Las vulnerabilidades no cruzan de organización ni de alcance
|--------------------------------------------------------------------------
|
| Es lo más sensible que guarda el producto: una lista de fallos sin corregir
| con los activos donde están. 404 y no 403.
|
*/

beforeEach(function (): void {
    $this->propia = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->ajena = Organizacion::factory()->create();
});

it('no lista ni abre las de otra organización', function (): void {
    Vulnerabilidad::factory()->create(['codigo' => 'VUL-PROPIA']);

    $ajena = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->ajena,
        fn (): Vulnerabilidad => Vulnerabilidad::factory()->create(['codigo' => 'VUL-AJENA']),
    );

    comoOrganizacion($this->propia);

    expect(Vulnerabilidad::query()->pluck('codigo')->all())->toBe(['VUL-PROPIA']);

    $this->actingAs($this->usuario)->get("/vulnerabilidades/{$ajena->id}")->assertNotFound();
    $this->actingAs($this->usuario)->get("/vulnerabilidades/{$ajena->id}/editar")->assertNotFound();
    $this->actingAs($this->usuario)->post("/vulnerabilidades/{$ajena->id}/estado", ['estado' => 'en_remediacion'])->assertNotFound();
    $this->actingAs($this->usuario)->post("/vulnerabilidades/{$ajena->id}/tareas", ['titulo' => 'x', 'prioridad' => 'alta'])->assertNotFound();
});

it('no enlaza un activo de otra organización', function (): void {
    $ajeno = app(ContextoOrganizacion::class)->paraOrganizacion($this->ajena, fn (): Activo => Activo::factory()->create());

    comoOrganizacion($this->propia);

    $this->actingAs($this->usuario)->post('/vulnerabilidades', [
        'codigo' => 'VUL-X', 'titulo' => 'x', 'origen' => 'interna', 'severidad' => 'baja',
        'fecha_deteccion' => today()->toDateString(), 'activos' => [$ajeno->id],
    ])->assertSessionHasErrors('activos.0');
});

it('el auditor externo ve las de su sistema y no las del otro', function (): void {
    $suyo = Sistema::factory()->create();
    $otro = Sistema::factory()->create();

    $activoSuyo = Activo::factory()->create();
    $activoSuyo->sistemas()->attach($suyo->id, ['organizacion_id' => $this->propia->id]);
    $activoOtro = Activo::factory()->create();
    $activoOtro->sistemas()->attach($otro->id, ['organizacion_id' => $this->propia->id]);

    $visible = Vulnerabilidad::factory()->create();
    $visible->activos()->attach($activoSuyo->id, ['organizacion_id' => $this->propia->id]);
    $oculta = Vulnerabilidad::factory()->create();
    $oculta->activos()->attach($activoOtro->id, ['organizacion_id' => $this->propia->id]);

    $auditor = usuarioCon(Rol::Auditor);
    CuentaSistema::query()->create(['user_id' => $auditor->id, 'sistema_id' => $suyo->id]);

    $this->actingAs($auditor)->get("/vulnerabilidades/{$visible->id}")->assertOk();
    $this->actingAs($auditor)->get("/vulnerabilidades/{$oculta->id}")->assertNotFound();
});
