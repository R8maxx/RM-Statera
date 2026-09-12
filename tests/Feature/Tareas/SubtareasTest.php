<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Aviso\ResumenVencimientos;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Models\Subtarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\ResumenPlanDeAccion;
use App\Http\Requests\GuardarSubtareasRequest;
use App\Http\Resources\Panel\Indicador;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Las listas de comprobación
|--------------------------------------------------------------------------
|
| Una subtarea es un paso, no una tarea. Lo que más importa aquí no es que se
| guarde bien: es que NO cuente en ningún sitio donde se cuentan tareas. Trece
| sitios cuentan tareas, y el día que uno sume pasos el panel dirá doce donde hay
| cuatro cosas que hacer.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->tarea = Tarea::factory()->create();

    /** @param list<array{id?: int|null, titulo: string, hecha: bool}> $pasos */
    $this->guardar = fn (array $pasos, ?Tarea $tarea = null) => $this->actingAs($this->usuario)
        ->put('/tareas/'.($tarea ?? $this->tarea)->id.'/subtareas', ['pasos' => $pasos]);
});

it('crea la lista en una pasada', function (): void {
    ($this->guardar)([
        ['titulo' => 'Redactar el borrador', 'hecha' => false],
        ['titulo' => 'Pasarlo a revisión', 'hecha' => false],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($this->tarea->subtareas()->pluck('titulo')->all())
        ->toBe(['Redactar el borrador', 'Pasarlo a revisión']);
});

it('el orden es el de la lista, no el de creación', function (): void {
    ($this->guardar)([
        ['titulo' => 'Primero', 'hecha' => false],
        ['titulo' => 'Segundo', 'hecha' => false],
    ]);

    $pasos = $this->tarea->subtareas()->get();

    // Y ahora al revés, conservando los ids.
    ($this->guardar)([
        ['id' => $pasos[1]->id, 'titulo' => 'Segundo', 'hecha' => false],
        ['id' => $pasos[0]->id, 'titulo' => 'Primero', 'hecha' => false],
    ]);

    expect($this->tarea->subtareas()->pluck('titulo')->all())->toBe(['Segundo', 'Primero']);
});

it('marcar sella la fecha y desmarcar la limpia', function (): void {
    ($this->guardar)([['titulo' => 'Un paso', 'hecha' => false]]);

    $paso = $this->tarea->subtareas()->sole();
    expect($paso->hecha_en)->toBeNull();

    ($this->guardar)([['id' => $paso->id, 'titulo' => 'Un paso', 'hecha' => true]]);
    expect($paso->fresh()->hecha_en)->not->toBeNull();

    ($this->guardar)([['id' => $paso->id, 'titulo' => 'Un paso', 'hecha' => false]]);
    expect($paso->fresh()->hecha_en)->toBeNull();
});

/**
 * La fecha es cuándo se hizo el paso, no cuándo se guardó la lista por última
 * vez. Si se resellara, renombrar el paso de al lado cambiaría la fecha.
 */
it('no vuelve a sellar la fecha de lo que ya estaba hecho', function (): void {
    ($this->guardar)([['titulo' => 'Un paso', 'hecha' => true]]);

    $paso = $this->tarea->subtareas()->sole();
    $sellada = $paso->hecha_en;

    Carbon::setTestNow(Carbon::now()->addHour());

    ($this->guardar)([['id' => $paso->id, 'titulo' => 'Un paso renombrado', 'hecha' => true]]);

    expect($paso->fresh()->hecha_en?->toIso8601String())->toBe($sellada?->toIso8601String());

    Carbon::setTestNow();
});

it('lo que no viene en la lista se borra', function (): void {
    ($this->guardar)([
        ['titulo' => 'Se queda', 'hecha' => false],
        ['titulo' => 'Se va', 'hecha' => false],
    ]);

    $paso = $this->tarea->subtareas()->firstWhere('titulo', 'Se queda');

    ($this->guardar)([['id' => $paso->id, 'titulo' => 'Se queda', 'hecha' => false]]);

    expect($this->tarea->subtareas()->pluck('titulo')->all())->toBe(['Se queda']);
});

it('una lista vacía vacía la lista', function (): void {
    Subtarea::factory()->count(3)->create(['tarea_id' => $this->tarea->id]);

    ($this->guardar)([])->assertRedirect()->assertSessionHasNoErrors();

    expect($this->tarea->subtareas()->count())->toBe(0);
});

it('un paso en blanco no se guarda', function (): void {
    ($this->guardar)([['titulo' => '   ', 'hecha' => false]])->assertSessionHasErrors();

    expect($this->tarea->subtareas()->count())->toBe(0);
});

it('el tope son cincuenta pasos', function (): void {
    $pasos = array_map(
        static fn (int $i): array => ['titulo' => "Paso {$i}", 'hecha' => false],
        range(1, GuardarSubtareasRequest::MAXIMO + 1),
    );

    ($this->guardar)($pasos)->assertSessionHasErrors('pasos');

    expect($this->tarea->subtareas()->count())->toBe(0);
});

/**
 * Lo que llega del cliente no manda sobre a quién pertenece una fila: un id de
 * otra tarea se trata como un paso nuevo, no como una puerta para editarla.
 */
it('un id de otra tarea no se roba, se crea uno nuevo', function (): void {
    $otra = Tarea::factory()->create();
    $ajeno = Subtarea::factory()->create(['tarea_id' => $otra->id, 'titulo' => 'De la otra tarea']);

    ($this->guardar)([['id' => $ajeno->id, 'titulo' => 'Intento de secuestro', 'hecha' => false]]);

    expect($ajeno->fresh()->titulo)->toBe('De la otra tarea')
        ->and($ajeno->fresh()->tarea_id)->toBe($otra->id)
        ->and($this->tarea->subtareas()->pluck('titulo')->all())->toBe(['Intento de secuestro']);
});

it('la ficha enseña la lista y el tope', function (): void {
    Subtarea::factory()->count(2)->create(['tarea_id' => $this->tarea->id]);
    Subtarea::factory()->hecha()->create(['tarea_id' => $this->tarea->id]);

    $this->actingAs($this->usuario)
        ->get("/tareas/{$this->tarea->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('subtareas', 3)
            ->where('maximoSubtareas', GuardarSubtareasRequest::MAXIMO)
            ->where('puedeGestionar', true));
});

it('el auditor ve la lista y no la toca', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)
        ->get("/tareas/{$this->tarea->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('puedeGestionar', false));

    $this->actingAs($auditor)
        ->put("/tareas/{$this->tarea->id}/subtareas", ['pasos' => [['titulo' => 'No', 'hecha' => false]]])
        ->assertForbidden();
});

it('no se toca la lista de una tarea de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    $tareaAjena = Tarea::factory()->create();

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->put("/tareas/{$tareaAjena->id}/subtareas", ['pasos' => [['titulo' => 'No', 'hecha' => false]]])
        ->assertNotFound();
});

it('el tablero enseña el progreso de cada tarjeta', function (): void {
    Subtarea::factory()->hecha()->count(2)->create(['tarea_id' => $this->tarea->id]);
    Subtarea::factory()->count(3)->create(['tarea_id' => $this->tarea->id]);

    $this->actingAs($this->usuario)
        ->get('/tareas/tablero')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $tarjeta = collect($pagina->toArray()['props']['columnas'])
                ->firstWhere('estado', 'pendiente')['tarjetas'][0];

            expect($tarjeta['pasos'])->toBe(5)
                ->and($tarjeta['pasosHechos'])->toBe(2);
        });
});

/*
|--------------------------------------------------------------------------
| El test que de verdad importa
|--------------------------------------------------------------------------
*/

/**
 * Una subtarea no es una tarea. Si algún recuento las sumara, el panel diría
 * doce tareas abiertas donde hay cuatro cosas que hacer, y contar de más es el
 * fallo caro.
 */
it('las subtareas no cambian ni una sola cifra', function (): void {
    Tarea::factory()->vencida()->create();
    Tarea::factory()->count(2)->create();
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    $antes = [
        'panel' => app(ResumenPlanDeAccion::class)->paraElPanel(),
        'alertas' => array_map(
            static fn (Indicador $uno): int => $uno->valor,
            app(ResumenPlanDeAccion::class)->alertas(),
        ),
        'aviso' => app(ResumenVencimientos::class)()->total(),
    ];

    // Ahora se trocean todas las tareas en cinco pasos cada una.
    foreach (Tarea::query()->get() as $tarea) {
        Subtarea::factory()->count(5)->create(['tarea_id' => $tarea->id]);
    }

    $despues = [
        'panel' => app(ResumenPlanDeAccion::class)->paraElPanel(),
        'alertas' => array_map(
            static fn (Indicador $uno): int => $uno->valor,
            app(ResumenPlanDeAccion::class)->alertas(),
        ),
        'aviso' => app(ResumenVencimientos::class)()->total(),
    ];

    expect($despues['panel']->total)->toBe($antes['panel']->total)
        ->and($despues['panel']->abiertas)->toBe($antes['panel']->abiertas)
        ->and($despues['panel']->vencidas)->toBe($antes['panel']->vencidas)
        ->and($despues['alertas'])->toBe($antes['alertas'])
        ->and($despues['aviso'])->toBe($antes['aviso']);
});

it('las subtareas no salen en la tabla, ni en el tablero, ni en el calendario', function (): void {
    $tarea = Tarea::factory()->paraElDia('2026-09-10')->create();
    Subtarea::factory()->count(4)->create(['tarea_id' => $tarea->id]);

    $this->actingAs($this->usuario)
        ->get('/tareas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));

    $this->actingAs($this->usuario)
        ->get('/tareas/tablero')
        ->assertInertia(function (AssertableInertia $pagina): void {
            expect(collect($pagina->toArray()['props']['columnas'])->sum('total'))->toBe(2);
        });

    $this->actingAs($this->usuario)
        ->get('/tareas/calendario?mes=2026-09')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('vencimientos', 1));
});
