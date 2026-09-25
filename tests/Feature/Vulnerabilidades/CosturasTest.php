<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Panel\AlertasDelPanel;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Las costuras del registro de vulnerabilidades
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->vulnerabilidad = Vulnerabilidad::factory()->create(['fecha_limite' => today()->addDays(10)]);
});

it('la tarea hereda el plazo de remediación si no se dice otro', function (): void {
    $this->actingAs($this->responsable)
        ->post("/vulnerabilidades/{$this->vulnerabilidad->id}/tareas", ['titulo' => 'Aplicar el parche', 'prioridad' => 'alta'])
        ->assertSessionHasNoErrors();

    $tarea = Tarea::query()->firstOrFail();

    expect($tarea->origen)->toBe(OrigenTarea::Vulnerabilidad)
        ->and($tarea->fecha_limite?->toDateString())->toBe(today()->addDays(10)->toDateString())
        ->and($this->vulnerabilidad->tareas()->pluck('tareas.id')->all())->toBe([$tarea->id]);
});

it('el plazo vencido sube al panel en rojo, y lo mitigado no', function (): void {
    $this->vulnerabilidad->forceFill(['fecha_limite' => today()->subDay()])->save();

    $claves = fn (): array => collect(app(AlertasDelPanel::class)($this->responsable))->pluck('clave')->all();

    expect($claves())->toContain('fuera_de_plazo');

    $this->vulnerabilidad->forceFill(['estado' => 'mitigada'])->save();

    expect($claves())->not->toContain('fuera_de_plazo');
});

it('el filtro de la tabla cuenta lo mismo que el indicador del panel', function (): void {
    $this->vulnerabilidad->forceFill(['fecha_limite' => today()->subDay()])->save();
    Vulnerabilidad::factory()->create();

    $this->actingAs($this->responsable)->get('/vulnerabilidades?filter[fuera_de_plazo]=1')
        ->assertInertia(fn ($pagina) => $pagina->where('meta.total', 1));
});

/**
 * Una aceptada no se corrige, pero sigue en el activo: es riesgo asumido. La
 * ficha decía «ninguna» de un activo con una crítica aceptada encima.
 */
it('la ficha del activo lista también las aceptadas, y no las cerradas', function (): void {
    $activo = Activo::factory()->create();

    $aceptada = Vulnerabilidad::factory()->create([
        'estado' => 'aceptada',
        'motivo_aceptacion' => 'Se retira en octubre.',
        'aceptada_en' => now(),
    ]);
    $cerrada = Vulnerabilidad::factory()->create([
        'estado' => 'cerrada',
        'verificacion' => 'Ya no aparece en el escaneo.',
        'cerrada_en' => now(),
    ]);

    foreach ([$aceptada, $cerrada] as $vulnerabilidad) {
        $vulnerabilidad->activos()->attach($activo->id, ['organizacion_id' => $this->organizacion->id]);
    }

    $this->actingAs($this->responsable)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('vulnerabilidades', 1)
            ->where('vulnerabilidades.0.id', $aceptada->id)
            ->where('vulnerabilidades.0.aceptada.etiqueta', 'Aceptada')
            ->etc());
});

/** Mismo hallazgo que tareas e implantaciones en el punto 28: botones que responden 403. */
it('al auditor la ficha del activo no le ofrece editar ni tocar dependencias', function (): void {
    $activo = Activo::factory()->create();

    $this->actingAs(usuarioCon(Rol::Auditor))
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('puedeGestionar', false)
            ->where('puedeRegistrarVulnerabilidad', false)
            ->etc());

    $this->actingAs($this->responsable)
        ->get("/activos/{$activo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('puedeGestionar', true)->etc());
});
