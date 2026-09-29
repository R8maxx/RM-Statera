<?php

declare(strict_types=1);

use App\Domain\Aviso\Fuente;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Panel\VencimientosDelPanel;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Lo que vence, al lado del plan de acción
|--------------------------------------------------------------------------
|
| La lista de «El ciclo» tiene que enseñar los mismos rojos que cuenta el punto
| de su pestaña: ni una evidencia caducada, que se explica en «Cumplimiento», ni
| nada de un módulo que quien mira no puede ver.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('pone lo vencido delante de lo próximo, lo más antiguo primero', function (): void {
    Tarea::factory()->paraElDia(Carbon::today()->addDays(5))->create(['titulo' => 'Próxima']);
    Tarea::factory()->vencida(2)->create(['titulo' => 'Reciente']);
    Tarea::factory()->vencida(9)->create(['titulo' => 'Antigua']);

    $this->actingAs($this->usuario)
        ->get('/panel/ciclo')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('vencimientos.pasados', 2)
            ->where('vencimientos.proximos', 1)
            ->where('vencimientos.dias', 30)
            ->where('vencimientos.filas.0.titulo', 'Antigua')
            ->where('vencimientos.filas.1.titulo', 'Reciente')
            ->where('vencimientos.filas.2.titulo', 'Próxima'));
});

it('deja fuera lo que cae en otra pestaña', function (): void {
    Evidencia::factory()->caducada()->create();
    Tarea::factory()->vencida()->create(['titulo' => 'Del ciclo']);

    $this->actingAs($this->usuario)
        ->get('/panel/ciclo')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('vencimientos.pasados', 1)
            ->has('vencimientos.filas', 1)
            ->where('vencimientos.filas.0.fuente', 'tarea'));
});

it('recorta las filas y cuenta el total entero', function (): void {
    Tarea::factory()->vencida()->count(VencimientosDelPanel::FILAS + 2)->create();

    $this->actingAs($this->usuario)
        ->get('/panel/ciclo')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('vencimientos.pasados', VencimientosDelPanel::FILAS + 2)
            ->has('vencimientos.filas', VencimientosDelPanel::FILAS));
});

it('no enseña lo que vence en un módulo que quien mira no puede ver', function (): void {
    Vulnerabilidad::factory()->create([
        'severidad' => 'critica',
        'fecha_deteccion' => Carbon::today()->subDays(30),
        'fecha_limite' => Carbon::today()->subDays(23),
    ]);

    // Al rol y no a la persona: `revokePermissionTo` sobre ella no quita lo que
    // hereda, y el test pasaría por el motivo equivocado.
    $this->usuario->roles->first()?->revokePermissionTo('vulnerabilidades.ver');
    $usuario = $this->usuario->fresh();

    expect(VencimientosDelPanel::fuentesDe('ciclo', $usuario))->not->toContain(Fuente::Vulnerabilidad);

    $this->actingAs($usuario)
        ->get('/panel/ciclo')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('vencimientos.pasados', 0));
});
