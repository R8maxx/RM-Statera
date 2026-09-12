<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El calendario
|--------------------------------------------------------------------------
|
| Enseña vencimientos, no tareas: una tarea que vence y una evidencia que caduca
| son la misma pregunta para quien mira el mes. § 4.16 —calendario de
| obligaciones— incluye literalmente la caducidad de evidencias, así que esto es
| su primera pieza.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

/** Los vencimientos que el calendario manda para un mes. */
function vencimientosDe(string $mes): array
{
    $vencimientos = [];

    test()->actingAs(test()->usuario)
        ->get("/tareas/calendario?mes={$mes}")
        ->assertOk()
        ->assertInertia(function (AssertableInertia $pagina) use (&$vencimientos): void {
            $vencimientos = $pagina->toArray()['props']['vencimientos'];
        });

    return $vencimientos;
}

it('junta plazos de tareas y caducidades de evidencias', function (): void {
    Tarea::factory()->paraElDia('2026-09-10')->create(['titulo' => 'Revisar la política']);
    Evidencia::factory()->create(['fecha_caducidad' => '2026-09-20', 'titulo' => 'Certificado TLS']);

    $vencimientos = vencimientosDe('2026-09');

    expect($vencimientos)->toHaveCount(2);

    $fuentes = array_column($vencimientos, 'fuente');

    expect($fuentes)->toContain('tarea')
        ->and($fuentes)->toContain('evidencia');
});

it('cada vencimiento sabe a dónde lleva', function (): void {
    $tarea = Tarea::factory()->paraElDia('2026-09-10')->create();
    $evidencia = Evidencia::factory()->create(['fecha_caducidad' => '2026-09-11']);

    $urls = array_column(vencimientosDe('2026-09'), 'url');

    expect($urls)->toContain("/tareas/{$tarea->id}")
        ->and($urls)->toContain("/evidencias/{$evidencia->id}");
});

/**
 * Los extremos son los de la REJILLA, no los del mes: las casillas de relleno
 * son días de verdad y lo que caiga en ellas también hay que atenderlo.
 */
it('trae lo que cae en los días de relleno de la rejilla', function (): void {
    // Septiembre de 2026 empieza en martes: la rejilla arranca el 31 de agosto.
    Tarea::factory()->paraElDia('2026-08-31')->create(['titulo' => 'Del lunes anterior']);

    expect(array_column(vencimientosDe('2026-09'), 'titulo'))->toContain('Del lunes anterior');
});

it('no trae lo que cae fuera de la rejilla', function (): void {
    Tarea::factory()->paraElDia('2026-07-15')->create(['titulo' => 'De julio']);

    expect(array_column(vencimientosDe('2026-09'), 'titulo'))->not->toContain('De julio');
});

/**
 * Una tarea cerrada no vence: está cerrada. Mismo criterio que en el aviso
 * diario y que en los indicadores.
 */
it('no enseña el plazo de una tarea ya cerrada', function (): void {
    Tarea::factory()
        ->enEstado(EstadoTarea::Hecha)
        ->paraElDia('2026-09-10')
        ->create(['titulo' => 'Ya hecha']);

    expect(vencimientosDe('2026-09'))->toBeEmpty();
});

it('un mes que no se entiende no rompe la pantalla', function (string $basura): void {
    $this->actingAs($this->usuario)
        ->get('/tareas/calendario?mes='.urlencode($basura))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('tareas/Calendario')
            ->where('rejilla.mes', Carbon::today()->format('Y-m')));
})->with(['2026-13', 'septiembre', "2026-09'; DROP TABLE tareas;--", '']);

it('la rejilla siempre trae seis semanas', function (): void {
    $this->actingAs($this->usuario)
        ->get('/tareas/calendario?mes=2026-02')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('rejilla.dias', 42));
});

it('no cruza la frontera de organización', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    Tarea::factory()->paraElDia('2026-09-10')->create(['titulo' => 'De la otra organización']);

    comoOrganizacion($this->organizacion);
    Tarea::factory()->paraElDia('2026-09-10')->create(['titulo' => 'Propia']);

    $titulos = array_column(vencimientosDe('2026-09'), 'titulo');

    expect($titulos)->toBe(['Propia']);
});

it('el auditor puede mirarlo', function (): void {
    $this->actingAs(usuarioCon(Rol::Auditor))
        ->get('/tareas/calendario')
        ->assertOk();
});
