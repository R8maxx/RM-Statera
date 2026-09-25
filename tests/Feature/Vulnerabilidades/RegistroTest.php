<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\Severidad;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Domain\Vulnerabilidad\PlazoRemediacion;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El registro de vulnerabilidades (invariante 8, A.8.8, op.exp.4)
|--------------------------------------------------------------------------
|
| La severidad se deriva del CVSS con los tramos de FIRST, y el plazo de la
| política de la organización. Las dos cosas se comprueban aquí.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->datos = fn (array $cambios = []): array => [
        'codigo' => 'VUL-2026-01',
        'titulo' => 'Servidor web sin parchear',
        'origen' => 'escaneo',
        'fecha_deteccion' => '2026-09-01',
        'severidad' => 'media',
        ...$cambios,
    ];
});

it('la severidad sale de los tramos de FIRST', function (float $puntuacion, Severidad $esperada): void {
    expect(Severidad::desdeCvss($puntuacion))->toBe($esperada);
})->with([
    [0.0, Severidad::Informativa],
    [0.1, Severidad::Baja],
    [3.9, Severidad::Baja],
    [4.0, Severidad::Media],
    [6.9, Severidad::Media],
    [7.0, Severidad::Alta],
    [8.9, Severidad::Alta],
    [9.0, Severidad::Critica],
    [10.0, Severidad::Critica],
]);

it('con CVSS la severidad se deriva aunque llegue otra, y la coma decimal vale', function (): void {
    $this->actingAs($this->responsable)
        ->post('/vulnerabilidades', ($this->datos)(['cvss_puntuacion' => '9,8', 'severidad' => 'baja']))
        ->assertSessionHasNoErrors();

    $vulnerabilidad = Vulnerabilidad::query()->firstOrFail();

    expect($vulnerabilidad->severidad)->toBe(Severidad::Critica)
        ->and((float) $vulnerabilidad->cvss_puntuacion)->toBe(9.8);
});

it('la base no admite una puntuación con otra severidad', function (): void {
    expect(fn () => Vulnerabilidad::factory()->create(['cvss_puntuacion' => '9.8', 'severidad' => 'baja']))
        ->toThrow(QueryException::class);
});

it('sin CVSS la severidad hay que declararla', function (): void {
    $this->actingAs($this->responsable)
        ->post('/vulnerabilidades', ($this->datos)(['severidad' => '__ninguno__']))
        ->assertSessionHasErrors('severidad');
});

it('el plazo sale de la severidad y de la política de la organización', function (): void {
    $this->actingAs($this->responsable)->post('/vulnerabilidades', ($this->datos)(['severidad' => 'critica']));

    // Crítica: 7 días por defecto.
    expect(Vulnerabilidad::query()->firstOrFail()->fecha_limite?->toDateString())->toBe('2026-09-08');
});

/**
 * Detectada hace más de lo que da su severidad, llega ya vencida. Un «hay que
 * remediarla antes del» con una fecha pasada se lee como una errata.
 */
it('avisa al registrarla si ya llega fuera de plazo', function (): void {
    $this->actingAs($this->responsable)
        ->post('/vulnerabilidades', ($this->datos)([
            'severidad' => 'critica',
            'fecha_deteccion' => now()->subDays(14)->toDateString(),
        ]))
        ->assertSessionHas('inertia.flash_data.exito', fn (string $mensaje): bool => str_contains($mensaje, 'ya fuera de plazo'));

    $this->actingAs($this->responsable)
        ->post('/vulnerabilidades', ($this->datos)([
            'codigo' => 'VUL-2026-02',
            'severidad' => 'critica',
            'fecha_deteccion' => now()->toDateString(),
        ]))
        ->assertSessionHas('inertia.flash_data.exito', fn (string $mensaje): bool => str_contains($mensaje, 'Hay que remediarla antes del'));
});

it('una informativa no tiene plazo', function (): void {
    $this->actingAs($this->responsable)->post('/vulnerabilidades', ($this->datos)(['severidad' => 'informativa']));

    expect(Vulnerabilidad::query()->firstOrFail()->fecha_limite)->toBeNull();
});

it('cambiar la severidad o la política mueve el plazo', function (): void {
    $this->actingAs($this->responsable)->post('/vulnerabilidades', ($this->datos)(['severidad' => 'media']));
    $vulnerabilidad = Vulnerabilidad::query()->firstOrFail();
    expect($vulnerabilidad->fecha_limite?->toDateString())->toBe('2026-11-30');

    $this->actingAs($this->responsable)->put("/vulnerabilidades/{$vulnerabilidad->id}", ($this->datos)(['severidad' => 'alta']));
    expect($vulnerabilidad->fresh()?->fecha_limite?->toDateString())->toBe('2026-10-01');

    $this->organizacion->forceFill(['plazo_vulnerabilidad_alta_dias' => 15])->save();
    app(PlazoRemediacion::class)->todas();
    expect($vulnerabilidad->fresh()?->fecha_limite?->toDateString())->toBe('2026-09-16');
});

it('registra los activos afectados y la primera transición', function (): void {
    $activo = Activo::factory()->create();

    $this->actingAs($this->responsable)
        ->post('/vulnerabilidades', ($this->datos)(['activos' => [$activo->id], 'cve' => 'cve-2024-3094']))
        ->assertSessionHasNoErrors();

    $vulnerabilidad = Vulnerabilidad::query()->firstOrFail();

    expect($vulnerabilidad->activos()->pluck('activos.id')->all())->toBe([$activo->id])
        ->and($vulnerabilidad->cve)->toBe('CVE-2024-3094')
        ->and($vulnerabilidad->estado)->toBe(EstadoVulnerabilidad::Abierta)
        ->and($vulnerabilidad->transiciones()->count())->toBe(1);
});

it('un CVE mal escrito se rechaza', function (): void {
    $this->actingAs($this->responsable)
        ->post('/vulnerabilidades', ($this->datos)(['cve' => '2024-3094']))
        ->assertSessionHasErrors('cve');
});

it('las pantallas se pintan, también desde la ficha de un activo', function (): void {
    $activo = Activo::factory()->create();
    $vulnerabilidad = Vulnerabilidad::factory()->create();

    $this->actingAs($this->responsable)->get('/vulnerabilidades')->assertOk();
    $this->actingAs($this->responsable)->get("/vulnerabilidades/crear?activo={$activo->id}")
        ->assertInertia(fn ($pagina) => $pagina->where('sugerencia.activos', [(string) $activo->id]));
    $this->actingAs($this->responsable)->get("/vulnerabilidades/{$vulnerabilidad->id}")->assertOk();
    $this->actingAs($this->responsable)->get("/vulnerabilidades/{$vulnerabilidad->id}/editar")->assertOk();
    $this->actingAs($this->responsable)->get("/activos/{$activo->id}")->assertOk();
});

/*
| El desplegable ofrecía al auditor externo como responsable de remediar: alguien
| que no puede tocarla y que no debe, porque audita lo que se hace.
*/
it('de responsable sólo se ofrece a quien puede gestionarla y entra', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $auditor = usuarioCon(Rol::Auditor);
    $desactivado = usuarioCon(Rol::Tecnico);
    $desactivado->forceFill(['desactivada_en' => now()])->save();

    $this->actingAs($this->responsable)
        ->get('/vulnerabilidades/crear')
        ->assertInertia(function (AssertableInertia $pagina) use ($tecnico, $auditor, $desactivado): void {
            $ofrecidas = array_column($pagina->toArray()['props']['responsables'], 'valor');

            expect($ofrecidas)->toContain((string) $tecnico->id)
                ->and($ofrecidas)->toContain((string) $this->responsable->id)
                ->not->toContain((string) $auditor->id)
                ->not->toContain((string) $desactivado->id);
        });

    $this->actingAs($this->responsable)
        ->post('/vulnerabilidades', ($this->datos)(['responsable_id' => $auditor->id]))
        ->assertSessionHasErrors('responsable_id');
});

/** Sin él, editar la ficha vaciaría en silencio una asignación que ya existe. */
it('el responsable que ya estaba se conserva al editar aunque ya no se ofrezca', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $vulnerabilidad = Vulnerabilidad::factory()->create(['responsable_id' => $auditor->id]);

    $this->actingAs($this->responsable)
        ->get("/vulnerabilidades/{$vulnerabilidad->id}/editar")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('responsables', fn ($opciones): bool => collect($opciones)->contains('valor', (string) $auditor->id))
            ->etc());

    $this->actingAs($this->responsable)
        ->put("/vulnerabilidades/{$vulnerabilidad->id}", ($this->datos)(['codigo' => $vulnerabilidad->codigo, 'responsable_id' => $auditor->id]))
        ->assertSessionHasNoErrors();
});
