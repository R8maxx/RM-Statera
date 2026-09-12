<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El tablero
|--------------------------------------------------------------------------
|
| Lo que el tablero tiene que garantizar no es que pinte bonito: es que lo que
| ofrece se pueda hacer. Una columna que acepta una tarjeta y luego falla es
| peor que una que no la acepta.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

/** Las columnas del tablero, indexadas por estado. */
function columnasDelTablero(): array
{
    $columnas = [];

    test()->actingAs(test()->usuario)
        ->get('/tareas/tablero')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $pagina) use (&$columnas): void {
            $columnas = collect($pagina->toArray()['props']['columnas'])->keyBy('estado')->all();
        });

    return $columnas;
}

it('reparte las tareas por estado, en el orden en que avanza el trabajo', function (): void {
    Tarea::factory()->create(['titulo' => 'Pendiente']);
    Tarea::factory()->enEstado(EstadoTarea::EnCurso)->create(['titulo' => 'En curso']);
    Tarea::factory()->enEstado(EstadoTarea::Bloqueada)->create(['titulo' => 'Bloqueada']);

    $this->actingAs($this->usuario)
        ->get('/tareas/tablero')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('tareas/Tablero')
            ->has('columnas', 4)
            ->where('columnas.0.estado', 'pendiente')
            ->where('columnas.1.estado', 'en_curso')
            ->where('columnas.2.estado', 'bloqueada')
            ->where('columnas.3.estado', 'hecha'));
});

/**
 * Descartar exige un motivo y eso no cabe en un gesto de arrastre. Además, una
 * columna de descartadas crece para siempre y no la mira nadie.
 */
it('no tiene columna para lo descartado', function (): void {
    Tarea::factory()->enEstado(EstadoTarea::Descartada)->create();

    $estados = array_keys(columnasDelTablero());

    expect($estados)->not->toContain('descartada');
});

/**
 * El tablero enseña el trabajo en curso. Una columna con las trescientas tareas
 * cerradas desde enero deja de decir nada.
 */
it('en «hecha» sólo entra lo cerrado hace poco', function (): void {
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->create(['fecha_cierre' => Carbon::today()->subDays(3)]);
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->create(['fecha_cierre' => Carbon::today()->subMonths(3)]);

    expect(columnasDelTablero()['hecha']['total'])->toBe(1);
});

/**
 * Es lo que permite que una columna prohibida se marque como tal DURANTE el
 * arrastre, en vez de aceptar el soltado y fallar después.
 */
it('cada tarjeta dice a qué columnas puede ir', function (): void {
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->create();

    $tarjeta = columnasDelTablero()['hecha']['tarjetas'][0];

    // Desde «hecha» se puede reabrir, pero no saltar a descartada.
    expect($tarjeta['transiciones'])->toContain('en_curso')
        ->and($tarjeta['transiciones'])->toContain('pendiente')
        ->and($tarjeta['transiciones'])->not->toContain('descartada')
        ->and($tarjeta['descartable'])->toBeFalse();
});

it('las transiciones de una tarjeta no ofrecen columnas que no existen', function (): void {
    Tarea::factory()->create();

    $tarjeta = columnasDelTablero()['pendiente']['tarjetas'][0];

    // `descartada` es una transición legal del dominio, pero no es una columna:
    // se ofrece aparte, con su diálogo.
    expect($tarjeta['transiciones'])->not->toContain('descartada')
        ->and($tarjeta['descartable'])->toBeTrue();
});

it('mover desde el tablero deja su huella en el histórico', function (): void {
    $tarea = Tarea::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/tareas/{$tarea->id}/estado", ['estado' => EstadoTarea::EnCurso->value])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tarea->fresh()->estado)->toBe(EstadoTarea::EnCurso)
        ->and($tarea->transiciones()->count())->toBe(1);
});

it('la columna dice cuántas se quedan fuera del tope', function (): void {
    Tarea::factory()->count(3)->create();

    $columna = columnasDelTablero()['pendiente'];

    expect($columna['total'])->toBe(3)
        ->and($columna['ocultas'])->toBe(0)
        ->and($columna['tarjetas'])->toHaveCount(3);
});

it('el auditor ve el tablero y no lo mueve', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $tarea = Tarea::factory()->create();

    $this->actingAs($auditor)->get('/tareas/tablero')->assertOk();
    $this->actingAs($auditor)
        ->post("/tareas/{$tarea->id}/estado", ['estado' => EstadoTarea::EnCurso->value])
        ->assertForbidden();
});

it('no enseña las tareas de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    Tarea::factory()->count(4)->create();

    comoOrganizacion($this->organizacion);
    Tarea::factory()->create();

    expect(columnasDelTablero()['pendiente']['total'])->toBe(1);
});
