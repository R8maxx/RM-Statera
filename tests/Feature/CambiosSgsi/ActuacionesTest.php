<?php

declare(strict_types=1);

use App\Domain\Cambio\Models\CambioSgsi;
use App\Domain\Cambio\VincularActuacionDeCambio;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;

/*
|--------------------------------------------------------------------------
| Lo que se hace para llevar a cabo un cambio, y el aislamiento
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('abrir una actuación le pone el origen «cambio del SGSI»', function (): void {
    $cambio = CambioSgsi::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/cambios-sgsi/{$cambio->id}/actuaciones", [
            'titulo' => 'Reescribir el procedimiento de altas',
            'prioridad' => 'media',
            // Se manda a propósito y tiene que ignorarse: lo pone el dominio.
            'origen' => OrigenTarea::Propia->value,
        ])
        ->assertRedirect();

    $tarea = Tarea::query()->where('titulo', 'Reescribir el procedimiento de altas')->sole();

    expect($tarea->origen)->toBe(OrigenTarea::CambioSgsi)
        ->and($cambio->tareas()->pluck('tareas.id')->all())->toBe([$tarea->id])
        ->and($tarea->implantaciones()->count())->toBe(0);
});

it('desvincular suelta el vínculo y deja la tarea en el plan de acción', function (): void {
    $cambio = CambioSgsi::factory()->create();
    $tarea = Tarea::factory()->create();

    app(VincularActuacionDeCambio::class)->vincular($cambio, $tarea, $this->usuario);

    $this->actingAs($this->usuario)
        ->delete("/cambios-sgsi/{$cambio->id}/actuaciones/{$tarea->id}")
        ->assertRedirect();

    expect($cambio->tareas()->count())->toBe(0)
        ->and(Tarea::query()->whereKey($tarea->id)->exists())->toBeTrue();
});

it('no lista ni deja abrir el cambio de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();

    CambioSgsi::factory()->create(['codigo' => 'CS-PROPIO']);

    $suyo = app(ContextoOrganizacion::class)->paraOrganizacion(
        $ajena,
        fn (): CambioSgsi => CambioSgsi::factory()->create(['codigo' => 'CS-AJENO']),
    );

    app(ContextoOrganizacion::class)->establecer($this->organizacion);

    expect(CambioSgsi::query()->pluck('codigo')->all())->toBe(['CS-PROPIO']);

    $this->actingAs($this->usuario)->get("/cambios-sgsi/{$suyo->id}")->assertNotFound();
    $this->actingAs($this->usuario)->post("/cambios-sgsi/{$suyo->id}/estado", ['estado' => 'descartado', 'nota' => 'x'])->assertNotFound();
    $this->actingAs($this->usuario)->delete("/cambios-sgsi/{$suyo->id}")->assertNotFound();
});

it('no desvincula desde un cambio la actuación de otro', function (): void {
    $tarea = Tarea::factory()->create();

    $mio = CambioSgsi::factory()->create();
    $otro = CambioSgsi::factory()->create();

    app(VincularActuacionDeCambio::class)->vincular($otro, $tarea, $this->usuario);

    $this->actingAs($this->usuario)
        ->delete("/cambios-sgsi/{$mio->id}/actuaciones/{$tarea->id}")
        ->assertNotFound();

    expect($otro->tareas()->count())->toBe(1);
});
