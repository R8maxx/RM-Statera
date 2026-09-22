<?php

declare(strict_types=1);

use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El mismo filtro en las tres vistas
|--------------------------------------------------------------------------
|
| Las tres filtran con la misma declaración y los mismos scopes. Es lo único que
| garantiza que enseñen lo mismo: con la condición escrita tres veces, la tabla
| dice doce y el tablero nueve, y a partir de ahí nadie se fía de ninguna.
|
| **Siguen siendo tres, pero ya no son tres vistas del plan.** Con el § 4.16 el
| calendario se mudó a `/calendario` y dejó de colgar de `/tareas`; el fichero
| conserva el nombre porque lo que comprueba —que las tres superficies apliquen la
| misma declaración de filtros— no ha cambiado, y el calendario sigue siendo la
| que más lo necesita: su URL con el mes puesto es de las que se guardan.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('un filtro por responsable acota igual la tabla y el tablero', function (): void {
    $suyas = Tarea::factory()->de($this->usuario)->count(2)->create();
    Tarea::factory()->count(3)->create();

    $filtro = "filter[responsable_id]={$this->usuario->id}";

    $this->actingAs($this->usuario)
        ->get("/tareas?{$filtro}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));

    $this->actingAs($this->usuario)
        ->get("/tareas/tablero?{$filtro}")
        ->assertOk()
        ->assertInertia(function (AssertableInertia $pagina) use ($suyas): void {
            $total = collect($pagina->toArray()['props']['columnas'])->sum('total');

            expect($total)->toBe($suyas->count());
        });
});

it('la búsqueda acota igual en las dos', function (): void {
    Tarea::factory()->create(['titulo' => 'Revisar la política de contraseñas']);
    Tarea::factory()->create(['titulo' => 'Contratar el antivirus']);

    $filtro = 'filter[q]=pol%C3%ADtica';

    $this->actingAs($this->usuario)
        ->get("/tareas?{$filtro}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 1));

    $this->actingAs($this->usuario)
        ->get("/tareas/tablero?{$filtro}")
        ->assertInertia(function (AssertableInertia $pagina): void {
            expect(collect($pagina->toArray()['props']['columnas'])->sum('total'))->toBe(1);
        });
});

it('el filtro de prioridad recorta las columnas del tablero', function (): void {
    Tarea::factory()->conPrioridad(PrioridadTarea::Critica)->create();
    Tarea::factory()->conPrioridad(PrioridadTarea::Critica)->enEstado(EstadoTarea::EnCurso)->create();
    Tarea::factory()->conPrioridad(PrioridadTarea::Baja)->count(4)->create();

    $this->actingAs($this->usuario)
        ->get('/tareas/tablero?filter[prioridad]=critica')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $columnas = collect($pagina->toArray()['props']['columnas'])->keyBy('estado');

            expect($columnas['pendiente']['total'])->toBe(1)
                ->and($columnas['en_curso']['total'])->toBe(1)
                ->and($columnas['bloqueada']['total'])->toBe(0);
        });
});

/**
 * Las columnas **son** el estado: filtrar por él vacía tres de las cuatro y deja
 * un tablero que parece roto. `bloqueadas` es lo mismo con otro nombre.
 */
it('el tablero no ofrece el filtro de estado ni el de bloqueadas', function (): void {
    $this->actingAs($this->usuario)
        ->get('/tareas/tablero')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $claves = array_column($pagina->toArray()['props']['filtros'], 'clave');

            expect($claves)->not->toContain('estado')
                ->and($claves)->not->toContain('bloqueadas')
                // Y sí ofrece los que acotan de verdad.
                ->and($claves)->toContain('responsable_id')
                ->and($claves)->toContain('prioridad')
                ->and($claves)->toContain('q');
        });
});

it('la tabla sigue ofreciéndolos todos', function (): void {
    $this->actingAs($this->usuario)
        ->get('/tareas')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $claves = array_column($pagina->toArray()['props']['recurso']['filtros'], 'clave');

            expect($claves)->toContain('estado')
                ->and($claves)->toContain('bloqueadas');
        });
});

it('los chips dicen lo que se aplicó de verdad', function (): void {
    Tarea::factory()->de($this->usuario)->create();

    $this->actingAs($this->usuario)
        ->get("/tareas/tablero?filter[responsable_id]={$this->usuario->id}&filter[inventado]=1")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filtrosAplicados.responsable_id', (string) $this->usuario->id)
            // Lo que no se declara no filtra, y tampoco aparece como aplicado.
            ->missing('filtrosAplicados.inventado'));
});

/**
 * El 400 por defecto de spatie convierte cualquier URL guardada en un error en
 * cuanto se renombra un filtro.
 *
 * Vale para las dos vistas del plan **y para el calendario**, que desde el § 4.16
 * ya no es una de ellas: se quedó en este test porque la regla es la misma y la
 * URL del mes es justamente de las que se guardan.
 */
it('un filtro no declarado se ignora en vez de romper la petición', function (): void {
    Tarea::factory()->count(2)->create();

    $this->actingAs($this->usuario)->get('/tareas?filter[platano]=1')->assertOk();
    $this->actingAs($this->usuario)->get('/tareas/tablero?filter[platano]=1')->assertOk();
    $this->actingAs($this->usuario)->get('/calendario?filter[platano]=1')->assertOk();
});

it('el tablero sigue respetando su ventana de cerradas al filtrar', function (): void {
    Tarea::factory()
        ->de($this->usuario)
        ->enEstado(EstadoTarea::Hecha)
        ->create(['fecha_cierre' => Carbon::today()->subMonths(3)]);

    $this->actingAs($this->usuario)
        ->get("/tareas/tablero?filter[responsable_id]={$this->usuario->id}")
        ->assertInertia(function (AssertableInertia $pagina): void {
            $columnas = collect($pagina->toArray()['props']['columnas'])->keyBy('estado');

            expect($columnas['hecha']['total'])->toBe(0);
        });
});
