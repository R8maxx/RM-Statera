<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\Models\RevisionInventario;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Http\Requests\Concerns\SeleccionVacia;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El registro de revisiones del inventario
|--------------------------------------------------------------------------
|
| A.5.9 de ISO y op.exp.1 del ENS no piden un inventario, piden un inventario
| mantenido. Esto es lo que demuestra la diferencia, y lo que un auditor pide
| cuando el listado le parece demasiado bonito.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->valida = [
        'fecha' => Carbon::today()->toDateString(),
        'responsable_id' => SeleccionVacia::VALOR,
        'alcance' => 'Parque de puestos de trabajo',
        'altas' => 3,
        'bajas' => 1,
        'desviaciones' => 'Dos equipos sin nº de serie.',
        'acciones' => 'Completarlos antes de la próxima revisión.',
    ];
});

it('registra una revisión y le pone la organización activa', function (): void {
    $this->actingAs($this->usuario)
        ->post('/revisiones', $this->valida)
        ->assertRedirect('/revisiones');

    $revision = RevisionInventario::query()->firstOrFail();

    expect($revision->organizacion_id)->toBe($this->organizacion->id)
        ->and($revision->alcance)->toBe('Parque de puestos de trabajo')
        ->and($revision->altas)->toBe(3)
        ->and($revision->tieneHallazgos())->toBeTrue();
});

it('exige decir qué se revisó', function (): void {
    // Una revisión parcial es legítima; una que no dice su alcance no demuestra
    // nada, porque no se sabe qué quedó fuera.
    $this->actingAs($this->usuario)
        ->post('/revisiones', [...$this->valida, 'alcance' => ''])
        ->assertSessionHasErrors('alcance');
});

it('rechaza una revisión con fecha futura', function (): void {
    // Con fecha futura no es una revisión, es un plan.
    $this->actingAs($this->usuario)
        ->post('/revisiones', [...$this->valida, 'fecha' => Carbon::tomorrow()->toDateString()])
        ->assertSessionHasErrors('fecha');
});

it('rechaza recuentos negativos', function (): void {
    $this->actingAs($this->usuario)
        ->post('/revisiones', [...$this->valida, 'altas' => -1])
        ->assertSessionHasErrors('altas');
});

it('distingue la revisión limpia de la que nadie completó', function (): void {
    $conHallazgos = RevisionInventario::factory()->de($this->organizacion)->conHallazgos()->create();
    $sinHallazgos = RevisionInventario::factory()->de($this->organizacion)->create();

    expect($conHallazgos->tieneHallazgos())->toBeTrue()
        ->and($sinHallazgos->tieneHallazgos())->toBeFalse();
});

it('enseña la última arriba', function (): void {
    RevisionInventario::factory()->de($this->organizacion)
        ->el(Carbon::today()->subYear())->create(['alcance' => 'La vieja']);
    RevisionInventario::factory()->de($this->organizacion)
        ->el(Carbon::today()->subDay())->create(['alcance' => 'La reciente']);

    $this->actingAs($this->usuario)
        ->get('/revisiones')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('revisiones/Index')
            ->where('filas.0.alcance', 'La reciente')
            ->where('meta.orden', '-fecha')
        );
});

it('edita y elimina una revisión', function (): void {
    $revision = RevisionInventario::factory()->de($this->organizacion)->create();

    $this->actingAs($this->usuario)
        ->put("/revisiones/{$revision->id}", [...$this->valida, 'alcance' => 'Corregido'])
        ->assertRedirect('/revisiones');

    expect($revision->fresh()?->alcance)->toBe('Corregido');

    $this->actingAs($this->usuario)
        ->delete("/revisiones/{$revision->id}")
        ->assertRedirect('/revisiones');

    expect(RevisionInventario::query()->count())->toBe(0);
});

it('responde 404 ante una revisión de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();
    comoOrganizacion($ajena);
    $revisionAjena = RevisionInventario::factory()->de($ajena)->create();
    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)->delete("/revisiones/{$revisionAjena->id}")->assertNotFound();
});

it('no deja al auditor registrar revisiones', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)->get('/revisiones')->assertOk();
    $this->actingAs($auditor)->post('/revisiones', $this->valida)->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Marcar activos como revisados
|--------------------------------------------------------------------------
|
| Es lo que conecta el registro con el inventario sin una tabla pivote de por
| medio: la revisión anota qué se miró, y esto pone la fecha en las filas.
|
*/

it('marca como revisados hoy los activos seleccionados', function (): void {
    $uno = Activo::factory()->de($this->organizacion)->create(['ultima_revision' => null]);
    $otro = Activo::factory()->de($this->organizacion)->create(['ultima_revision' => null]);
    $ajeno = Activo::factory()->de($this->organizacion)->create(['ultima_revision' => null]);

    $this->actingAs($this->usuario)
        ->post('/activos/revision', ['activos' => [$uno->id, $otro->id]])
        ->assertRedirect();

    expect($uno->fresh()?->ultima_revision?->toDateString())->toBe(Carbon::today()->toDateString())
        ->and($otro->fresh()?->ultima_revision)->not->toBeNull()
        ->and($ajeno->fresh()?->ultima_revision)->toBeNull();
});

it('deja de contar como sin revisar en cuanto se marca', function (): void {
    $activo = Activo::factory()->de($this->organizacion)->create(['ultima_revision' => null]);

    expect($activo->sinRevisar())->toBeTrue();

    $this->actingAs($this->usuario)->post('/activos/revision', ['activos' => [$activo->id]]);

    expect($activo->fresh()?->sinRevisar())->toBeFalse();
});

it('no marca activos de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();
    comoOrganizacion($ajena);
    $ajeno = Activo::factory()->de($ajena)->create(['ultima_revision' => null]);
    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->post('/activos/revision', ['activos' => [$ajeno->id]])
        ->assertSessionHasErrors('activos.0');
});

it('exige al menos un activo', function (): void {
    $this->actingAs($this->usuario)
        ->post('/activos/revision', ['activos' => []])
        ->assertSessionHasErrors('activos');
});
