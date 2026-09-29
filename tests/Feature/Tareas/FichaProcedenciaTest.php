<?php

declare(strict_types=1);

use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La ficha de una tarea dice por qué existe
|--------------------------------------------------------------------------
|
| Una acción correctiva enseña su no conformidad, y cuando ésta ya se cerró y
| la acción sigue abierta, la ficha lo avisa: es lo que un auditor encuentra al
| cruzar las dos listas.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->vincular = function (NoConformidad $noConformidad, Tarea $tarea): void {
        $noConformidad->tareas()->attach($tarea->id, ['organizacion_id' => $this->organizacion->id]);
    };
});

it('enseña la no conformidad de la que sale, con enlace', function (): void {
    $tarea = Tarea::factory()->create();
    $noConformidad = NoConformidad::factory()->create([
        'descripcion' => 'No hay constancia de la revisión periódica.',
        'analisis_causa_raiz' => 'Nadie la convocaba.',
    ]);
    ($this->vincular)($noConformidad, $tarea);

    $this->actingAs($this->usuario)
        ->get("/tareas/{$tarea->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('tareas/Ficha')
            ->has('procedencias', 1)
            ->where('procedencias.0.codigo', $noConformidad->codigo)
            ->where('procedencias.0.titulo', 'No hay constancia de la revisión periódica.')
            ->where('procedencias.0.detalles.0.etiqueta', 'Causa raíz')
            ->where('procedencias.0.href', route('no-conformidades.show', $noConformidad->id))
            ->missing('procedencias.0.permiso')
            ->where('origenCerrado', []));
});

it('avisa cuando la no conformidad está cerrada y la tarea sigue abierta', function (): void {
    $tarea = Tarea::factory()->create();
    $noConformidad = NoConformidad::factory()
        ->enEstado(EstadoNoConformidad::Verificada, Carbon::parse('2026-09-23'))
        ->create();
    ($this->vincular)($noConformidad, $tarea);

    $this->actingAs($this->usuario)
        ->get("/tareas/{$tarea->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('origenCerrado', 1)
            ->where('origenCerrado.0.codigo', $noConformidad->codigo)
            ->where('origenCerrado.0.fecha', '2026-09-23'));
});

it('no avisa si alguna de sus no conformidades sigue abierta', function (): void {
    $tarea = Tarea::factory()->create();
    ($this->vincular)(NoConformidad::factory()->enEstado(EstadoNoConformidad::Verificada)->create(), $tarea);
    ($this->vincular)(NoConformidad::factory()->create(), $tarea);

    $this->actingAs($this->usuario)
        ->get("/tareas/{$tarea->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('procedencias', 2)
            ->where('origenCerrado', []));
});

it('no avisa si la tarea ya está cerrada', function (): void {
    $tarea = Tarea::factory()->enEstado(EstadoTarea::Hecha)->create();
    ($this->vincular)(NoConformidad::factory()->enEstado(EstadoNoConformidad::Verificada)->create(), $tarea);

    $this->actingAs($this->usuario)
        ->get("/tareas/{$tarea->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('origenCerrado', []));
});

it('cuenta los días hasta el plazo, negativos si ya pasó', function (): void {
    $vencida = Tarea::factory()->create(['fecha_limite' => Carbon::today()->subDays(190)]);
    $cerrada = Tarea::factory()->enEstado(EstadoTarea::Hecha)->create(['fecha_limite' => Carbon::today()->subDays(3)]);

    expect($vencida->diasHastaElPlazo())->toBe(-190)
        ->and($cerrada->diasHastaElPlazo())->toBeNull()
        ->and(Tarea::factory()->create(['fecha_limite' => null])->diasHastaElPlazo())->toBeNull();
});

it('cada destino de transición lleva su pista', function (): void {
    $tarea = Tarea::factory()->create();

    $this->actingAs($this->usuario)
        ->get("/tareas/{$tarea->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('transiciones.0.pista', EstadoTarea::EnCurso->pista()));
});
