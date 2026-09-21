<?php

declare(strict_types=1);

use App\Domain\Persona\Enums\TipoPasoPersona;
use App\Domain\Persona\GuardarPasos;
use App\Domain\Persona\Models\AcuerdoConfidencialidad;
use App\Domain\Persona\Models\PasoPersona;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\RegistroPersonas;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Las checklists de alta y baja, y el acuerdo de mp.per.2
|--------------------------------------------------------------------------
|
| La checklist se guarda entera, como las subtareas de una tarea: añadir,
| renombrar, marcar, reordenar y borrar son el mismo gesto en una lista de
| comprobación, y el orden va implícito en la posición del array.
|
| **La de baja es la que el auditor mira**: un acceso que nadie revocó es el
| hallazgo clásico, y es el único rojo del módulo.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->persona = Persona::factory()->create();
    $this->guardar = app(GuardarPasos::class);
});

it('guarda la lista entera con su orden implícito', function (): void {
    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [
        ['titulo' => 'Firmar el acuerdo'],
        ['titulo' => 'Entregar el equipo'],
    ]);

    expect($this->persona->pasos()->orderBy('orden')->pluck('titulo')->all())
        ->toBe(['Firmar el acuerdo', 'Entregar el equipo']);
});

it('no vuelve a sellar la fecha de un paso que ya estaba marcado', function (): void {
    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [['titulo' => 'Uno', 'hecho' => true]]);

    $paso = PasoPersona::query()->sole();
    $sello = $paso->hecho_en;

    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [
        ['id' => $paso->id, 'titulo' => 'Uno, renombrado', 'hecho' => true],
    ]);

    expect($paso->fresh()?->hecho_en?->toDateTimeString())->toBe($sello?->toDateTimeString())
        ->and($paso->fresh()?->titulo)->toBe('Uno, renombrado');
});

it('desmarcar limpia la fecha', function (): void {
    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [['titulo' => 'Uno', 'hecho' => true]]);

    $paso = PasoPersona::query()->sole();

    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [
        ['id' => $paso->id, 'titulo' => 'Uno', 'hecho' => false],
    ]);

    expect($paso->fresh()?->hecho_en)->toBeNull();
});

it('lo que no viene en la lista se borra', function (): void {
    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [
        ['titulo' => 'Uno'],
        ['titulo' => 'Dos'],
    ]);

    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [['titulo' => 'Uno']]);

    expect($this->persona->pasos()->pluck('titulo')->all())->toBe(['Uno']);
});

/**
 * Las dos listas se guardan por separado: se rellenan con meses de diferencia y
 * por gente distinta, y una sola ruta haría que guardar la de salida borrara la
 * de entrada.
 */
it('guardar la lista de baja no toca la de alta', function (): void {
    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [['titulo' => 'Entregar el equipo']]);
    ($this->guardar)($this->persona, TipoPasoPersona::Baja, [['titulo' => 'Recuperar el equipo']]);

    expect($this->persona->pasos()->where('tipo', TipoPasoPersona::Alta->value)->count())->toBe(1)
        ->and($this->persona->pasos()->where('tipo', TipoPasoPersona::Baja->value)->count())->toBe(1);
});

/** Lo que llega del cliente no manda sobre a quién pertenece una fila. */
it('un id que no es de esta persona se trata como un paso nuevo', function (): void {
    $otra = Persona::factory()->create();

    ($this->guardar)($otra, TipoPasoPersona::Alta, [['titulo' => 'Suyo']]);

    $ajeno = PasoPersona::query()->where('persona_id', $otra->id)->sole();

    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [
        ['id' => $ajeno->id, 'titulo' => 'Robado'],
    ]);

    expect($ajeno->fresh()?->titulo)->toBe('Suyo')
        ->and($this->persona->pasos()->pluck('titulo')->all())->toBe(['Robado']);
});

it('descarta los pasos en blanco', function (): void {
    ($this->guardar)($this->persona, TipoPasoPersona::Alta, [
        ['titulo' => 'Uno'],
        ['titulo' => '   '],
    ]);

    expect(PasoPersona::query()->count())->toBe(1);
});

it('manda el tope de pasos a la ficha', function (): void {
    // La vista lo enseña en el campo de añadir; sin él, el 51.º paso se
    // rechazaba con un 422 que la pantalla no pintaba en ningún sitio.
    $this->actingAs($this->usuario)
        ->get("/personas/{$this->persona->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('personas/Ficha')
            ->where('maximoPasos', GuardarPasos::TOPE)
            ->etc());
});

it('rechaza una checklist por encima del tope, con su mensaje', function (): void {
    $pasos = array_map(
        static fn (int $i): array => ['titulo' => "Paso {$i}", 'hecho' => false],
        range(1, GuardarPasos::TOPE + 1),
    );

    $this->actingAs($this->usuario)
        ->put("/personas/{$this->persona->id}/pasos", [
            'tipo' => TipoPasoPersona::Alta->value,
            'pasos' => $pasos,
        ])
        ->assertSessionHasErrors('pasos');

    expect(PasoPersona::query()->count())->toBe(0);
});

it('guarda la checklist por la interfaz', function (): void {
    $this->actingAs($this->usuario)
        ->put("/personas/{$this->persona->id}/pasos", [
            'tipo' => TipoPasoPersona::Baja->value,
            'pasos' => [['titulo' => 'Revocar los accesos', 'hecho' => false]],
        ])
        ->assertRedirect();

    expect(PasoPersona::query()->sole()->titulo)->toBe('Revocar los accesos');
});

/*
|--------------------------------------------------------------------------
| El único rojo del módulo
|--------------------------------------------------------------------------
*/

/**
 * La checklist de salida sin terminar sólo se mira **en quien ya se fue**: sin
 * empezar en alguien que sigue trabajando no es una laguna, es que todavía no
 * toca.
 */
it('no reclama la checklist de salida a quien sigue en plantilla', function (): void {
    ($this->guardar)($this->persona, TipoPasoPersona::Baja, [['titulo' => 'Revocar accesos']]);

    expect($this->persona->fresh()?->esperaCierreDeBaja())->toBeFalse()
        ->and(Persona::query()->conBajaSinCerrar()->count())->toBe(0);
});

it('señala a quien se fue con la checklist de salida a medias', function (): void {
    ($this->guardar)($this->persona, TipoPasoPersona::Baja, [
        ['titulo' => 'Recuperar el equipo', 'hecho' => true],
        ['titulo' => 'Revocar los accesos', 'hecho' => false],
    ]);

    $this->persona->update(['fecha_baja' => Carbon::today()]);

    expect($this->persona->fresh()?->esperaCierreDeBaja())->toBeTrue()
        ->and(Persona::query()->conBajaSinCerrar()->count())->toBe(1);
});

it('deja de señalarla al cerrar todos los pasos', function (): void {
    ($this->guardar)($this->persona, TipoPasoPersona::Baja, [['titulo' => 'Revocar', 'hecho' => true]]);

    $this->persona->update(['fecha_baja' => Carbon::today()]);

    expect(Persona::query()->conBajaSinCerrar()->count())->toBe(0);
});

/** Es la única alerta del registro: no estar formado es la distancia que queda. */
it('sólo tiene una alerta, y es la salida sin cerrar', function (): void {
    $claves = array_map(
        static fn (object $indicador): string => $indicador->clave,
        app(RegistroPersonas::class)->alertas(),
    );

    expect($claves)->toBe(['baja_sin_cerrar']);
});

/*
|--------------------------------------------------------------------------
| El acuerdo de confidencialidad: mp.per.2
|--------------------------------------------------------------------------
*/

/** Sin fecha de caducidad es lo normal: el deber sobrevive a la relación laboral. */
it('un acuerdo sin fecha de caducidad está vigente', function (): void {
    AcuerdoConfidencialidad::factory()->create([
        'persona_id' => $this->persona->id,
        'organizacion_id' => $this->persona->organizacion_id,
    ]);

    expect(Persona::query()->sinAcuerdoVigente()->count())->toBe(0);
});

it('un acuerdo caducado deja a la persona sin acuerdo vigente', function (): void {
    AcuerdoConfidencialidad::factory()->caducado()->create([
        'persona_id' => $this->persona->id,
        'organizacion_id' => $this->persona->organizacion_id,
    ]);

    expect(Persona::query()->sinAcuerdoVigente()->count())->toBe(1);
});

it('registra un acuerdo por la interfaz y rechaza el que caduca antes de firmarse', function (): void {
    $this->actingAs($this->usuario)
        ->post("/personas/{$this->persona->id}/acuerdos", [
            'fecha_firma' => Carbon::today()->toDateString(),
            'vigente_hasta' => null,
        ])
        ->assertRedirect();

    expect(AcuerdoConfidencialidad::query()->count())->toBe(1);

    $this->actingAs($this->usuario)
        ->post("/personas/{$this->persona->id}/acuerdos", [
            'fecha_firma' => Carbon::today()->toDateString(),
            'vigente_hasta' => Carbon::today()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('vigente_hasta');
});
