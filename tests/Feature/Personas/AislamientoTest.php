<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Persona\DesignarRol;
use App\Domain\Persona\Enums\RolEns;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\AcuerdoConfidencialidad;
use App\Domain\Persona\Models\DesignacionRol;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\RegistrarAsistencia;
use App\Domain\Sistema\Models\Sistema;

/*
|--------------------------------------------------------------------------
| El aislamiento del registro de personas
|--------------------------------------------------------------------------
|
| Es el registro con datos personales de todo el producto: nombres, puestos,
| correos y quién se fue y cuándo. Filtrarlo no es sólo un fallo de negocio.
|
| Se responde **404 y no 403**: decir «existe pero no es tuyo» ya sería
| filtrar.
|
| **Y hay un segundo eje**, el que de verdad cuesta ver: entre dos personas de
| la MISMA organización no hay ninguna de las tres capas. Lo que impide revocar
| desde ésta el nombramiento de aquélla es `scopeBindings()` — y aquí
| `scopeBindings()` **no basta**, porque deduce la relación pluralizando en
| inglés y `designacion` se le convierte en `designacions`. Es la cuarta vez en
| el producto y las tres anteriores las cazó un test como éste.
|
*/

beforeEach(function (): void {
    $this->propia = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->ajena = Organizacion::factory()->create();

    $this->enLaAjena = fn (callable $callback) => app(ContextoOrganizacion::class)
        ->paraOrganizacion($this->ajena, $callback);
});

it('no lista las personas de otra organización', function (): void {
    Persona::factory()->create(['codigo' => 'PER-PROPIA']);

    ($this->enLaAjena)(fn () => Persona::factory()->create(['codigo' => 'PER-AJENA']));

    app(ContextoOrganizacion::class)->establecer($this->propia);

    expect(Persona::query()->pluck('codigo')->all())->toBe(['PER-PROPIA']);
});

it('responde 404 al pedir la persona de otra organización', function (): void {
    $ajena = ($this->enLaAjena)(fn (): Persona => Persona::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)->get("/personas/{$ajena->id}")->assertNotFound();
    $this->actingAs($this->usuario)->get("/personas/{$ajena->id}/editar")->assertNotFound();
    $this->actingAs($this->usuario)->delete("/personas/{$ajena->id}")->assertNotFound();
});

it('responde 404 al pedir la sesión de formación de otra organización', function (): void {
    $ajena = ($this->enLaAjena)(fn (): AccionFormativa => AccionFormativa::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)->get("/formacion/{$ajena->id}")->assertNotFound();
    $this->actingAs($this->usuario)
        ->put("/formacion/{$ajena->id}/asistencia", ['convocadas' => []])
        ->assertNotFound();
});

/**
 * El segundo eje, y el que cazó el `resolveChildRouteBinding()`: sin acotar, la
 * ruta revocaría desde una persona el nombramiento de otra.
 */
it('no revoca desde una persona el nombramiento de otra', function (): void {
    $sistema = Sistema::factory()->create();
    $una = Persona::factory()->create();
    $otra = Persona::factory()->create();

    $suya = app(DesignarRol::class)($otra, $sistema, RolEns::ResponsableSeguridad);

    $this->actingAs($this->usuario)
        ->delete("/personas/{$una->id}/designaciones/{$suya->id}")
        ->assertNotFound();

    expect($suya->fresh()?->estaVigente())->toBeTrue();
});

it('no borra desde una persona el acuerdo de otra', function (): void {
    $una = Persona::factory()->create();
    $otra = Persona::factory()->create();

    $suyo = AcuerdoConfidencialidad::factory()->create([
        'persona_id' => $otra->id,
        'organizacion_id' => $otra->organizacion_id,
    ]);

    $this->actingAs($this->usuario)
        ->delete("/personas/{$una->id}/acuerdos/{$suyo->id}")
        ->assertNotFound();

    expect(AcuerdoConfidencialidad::query()->count())->toBe(1);
});

/**
 * La convocatoria se resuelve **por el modelo** y no por los ids que llegan, que
 * es lo que impide apuntar a una sesión propia la asistencia de la plantilla de
 * otro cliente pasando ids a mano.
 *
 * **Y la petición entera se rechaza antes de llegar ahí**, porque el `exists` de
 * la regla es una consulta cruda y ésa la corta RLS —la tercera capa, que es
 * justamente la que cubre lo que no pasa por Eloquent—. Las dos cosas hacen
 * falta: la regla protege la petición y `RegistrarAsistencia` protege al
 * importador y al seeder.
 */
it('rechaza la convocatoria que nombra a una persona de otra organización', function (): void {
    $ajena = ($this->enLaAjena)(fn (): Persona => Persona::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $accion = AccionFormativa::factory()->create();
    $propia = Persona::factory()->create();

    $this->actingAs($this->usuario)
        ->put("/formacion/{$accion->id}/asistencia", [
            'convocadas' => [
                ['persona_id' => $propia->id, 'asistio' => true],
                ['persona_id' => $ajena->id, 'asistio' => true],
            ],
        ])
        ->assertSessionHasErrors('convocadas.1.persona_id');

    expect($accion->asistencias()->count())->toBe(0);
});

it('ignora en el dominio a las personas de otra organización', function (): void {
    $ajena = ($this->enLaAjena)(fn (): Persona => Persona::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $accion = AccionFormativa::factory()->create();
    $propia = Persona::factory()->create();

    app(RegistrarAsistencia::class)($accion, [$propia->id => true, $ajena->id => true]);

    expect($accion->asistencias()->pluck('persona_id')->all())->toBe([$propia->id]);
});

/** Un nombramiento no cruza la frontera ni por el scope ni por RLS. */
it('no lista los nombramientos de otra organización', function (): void {
    ($this->enLaAjena)(function (): void {
        $sistema = Sistema::factory()->create();
        app(DesignarRol::class)(
            Persona::factory()->create(),
            $sistema,
            RolEns::ResponsableSeguridad,
        );
    });

    app(ContextoOrganizacion::class)->establecer($this->propia);

    expect(DesignacionRol::query()->count())->toBe(0);
});
