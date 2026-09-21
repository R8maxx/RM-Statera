<?php

declare(strict_types=1);

use App\Domain\Persona\AsignarPuesto;
use App\Domain\Persona\AsignarSuperior;
use App\Domain\Persona\Excepciones\PersonaNoDesignable;
use App\Domain\Persona\Excepciones\PuestoCiclico;
use App\Domain\Persona\Models\AsignacionPuesto;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\Models\Puesto;
use App\Domain\Persona\Organigrama;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Puestos, asignaciones y organigrama
|--------------------------------------------------------------------------
|
| Dos reglas cargan con el módulo, y ninguna cabe en un `CHECK`:
|
| 1. **El ciclo.** `reporta_a_id` forma un grafo, y contra un grafo con un
|    bucle la CTE recursiva del organigrama NO devuelve un resultado raro: no
|    termina. El `CHECK` tapa el bucle de un salto; uno de tres se cuela, y lo
|    rechaza `AsignarSuperior` — precedente exacto de `RegistrarDependencia`.
| 2. **La exclusividad.** Una persona ocupa un puesto a la vez, así que
|    asignarle uno nuevo CIERRA el anterior en vez de chocar con el índice
|    único parcial.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->asignar = app(AsignarPuesto::class);
    $this->superior = app(AsignarSuperior::class);
});

/* --- El ciclo, que es el fallo que cuelga el proceso --------------------- */

it('rechaza que un puesto dependa de sí mismo', function (): void {
    $puesto = Puesto::factory()->create();

    expect(fn () => ($this->superior)($puesto, $puesto))
        ->toThrow(PuestoCiclico::class);
});

it('rechaza un ciclo de dos saltos', function (): void {
    $jefe = Puesto::factory()->create();
    $medio = Puesto::factory()->bajo($jefe)->create();

    expect(fn () => ($this->superior)($jefe, $medio))
        ->toThrow(PuestoCiclico::class);
});

/**
 * El de tres saltos es el que se cuela por el `CHECK`, y el que de verdad
 * cuelga la CTE. Si esta prueba se pone roja, el organigrama deja de terminar.
 */
it('rechaza un ciclo de tres saltos', function (): void {
    $uno = Puesto::factory()->create();
    $dos = Puesto::factory()->bajo($uno)->create();
    $tres = Puesto::factory()->bajo($dos)->create();

    expect(fn () => ($this->superior)($uno, $tres))
        ->toThrow(PuestoCiclico::class);
});

it('deja colgar un puesto de otro que no está por debajo', function (): void {
    $jefe = Puesto::factory()->create();
    $otro = Puesto::factory()->create();

    ($this->superior)($otro, $jefe);

    expect($otro->refresh()->reporta_a_id)->toBe($jefe->id);
});

it('deja soltar un puesto de su superior', function (): void {
    $jefe = Puesto::factory()->create();
    $hijo = Puesto::factory()->bajo($jefe)->create();

    ($this->superior)($hijo, null);

    expect($hijo->refresh()->reporta_a_id)->toBeNull();
});

/* --- El organigrama ------------------------------------------------------ */

it('recorre un árbol de tres niveles con su profundidad', function (): void {
    $direccion = Puesto::factory()->create(['titulo' => 'Dirección']);
    $area = Puesto::factory()->bajo($direccion)->create(['titulo' => 'Sistemas']);
    Puesto::factory()->bajo($area)->create(['titulo' => 'Soporte']);

    $arbol = app(Organigrama::class)->arbol();

    expect($arbol->pluck('titulo')->all())->toBe(['Dirección', 'Sistemas', 'Soporte'])
        ->and($arbol->map(fn (Puesto $uno): int => (int) $uno->getAttribute('profundidad'))->all())
        ->toBe([0, 1, 2]);
});

it('saca las ramas sueltas también, para que se puedan arreglar', function (): void {
    Puesto::factory()->create(['titulo' => 'Dirección']);
    Puesto::factory()->create(['titulo' => 'Consejo']);

    expect(app(Organigrama::class)->arbol())->toHaveCount(2);
});

/**
 * El organigrama es SQL crudo, así que no pasa por el global scope de Eloquent:
 * si `organizacion_id` faltara en una de las dos ramas de la CTE, el árbol
 * cruzaría la frontera sin que ningún `where` de PHP lo tapara.
 */
it('no cruza la frontera de organización', function (): void {
    $otra = comoOrganizacion();
    Puesto::factory()->create(['titulo' => 'De la otra organización']);

    comoOrganizacion($this->organizacion);
    Puesto::factory()->create(['titulo' => 'De la mía']);

    $arbol = app(Organigrama::class)->arbol();

    expect($arbol)->toHaveCount(1)
        ->and($arbol->first()?->titulo)->toBe('De la mía')
        ->and($otra->id)->not->toBe($this->organizacion->id);
});

/* --- Las asignaciones ---------------------------------------------------- */

it('asigna un puesto a una persona', function (): void {
    $persona = Persona::factory()->create();
    $puesto = Puesto::factory()->create();

    ($this->asignar)($persona, $puesto);

    expect($persona->refresh()->puestoVigente()?->id)->toBe($puesto->id);
});

/**
 * Cambiar de puesto cierra el anterior **el día antes**: dos asignaciones que
 * se solapan un día harían que «qué puesto ocupaba el 3 de marzo» tuviera dos
 * respuestas.
 */
it('cambiar de puesto cierra el anterior sin borrarlo', function (): void {
    $persona = Persona::factory()->create();
    $viejo = Puesto::factory()->create();
    $nuevo = Puesto::factory()->create();

    ($this->asignar)($persona, $viejo, Carbon::today()->subYear());
    ($this->asignar)($persona, $nuevo, Carbon::today());

    $asignaciones = AsignacionPuesto::query()->where('persona_id', $persona->id)->get();

    expect($asignaciones)->toHaveCount(2)
        ->and($persona->refresh()->puestoVigente()?->id)->toBe($nuevo->id)
        ->and($asignaciones->firstWhere('puesto_id', $viejo->id)?->hasta?->toDateString())
        ->toBe(Carbon::today()->subDay()->toDateString());
});

it('cerrar deja a la persona sin puesto y conserva la fila', function (): void {
    $persona = Persona::factory()->create();
    $puesto = Puesto::factory()->create();

    $asignacion = ($this->asignar)($persona, $puesto);
    $this->asignar->cerrar($asignacion);

    expect($persona->refresh()->puestoVigente())->toBeNull()
        ->and(AsignacionPuesto::query()->where('persona_id', $persona->id)->count())->toBe(1);
});

it('no asigna un puesto a quien ya no está en plantilla', function (): void {
    $baja = Persona::factory()->deBaja()->create();
    $puesto = Puesto::factory()->create();

    expect(fn () => ($this->asignar)($baja, $puesto))
        ->toThrow(PersonaNoDesignable::class);
});

/**
 * La exclusividad la impone la base, no sólo el dominio: un importador que
 * escribiera dos filas vigentes a mano tiene que chocar.
 */
it('la base impide dos puestos vigentes para la misma persona', function (): void {
    $persona = Persona::factory()->create();

    AsignacionPuesto::query()->create([
        'persona_id' => $persona->id,
        'puesto_id' => Puesto::factory()->create()->id,
        'desde' => Carbon::today()->subYear(),
    ]);

    expect(function () use ($persona): void {
        AsignacionPuesto::query()->create([
            'persona_id' => $persona->id,
            'puesto_id' => Puesto::factory()->create()->id,
            'desde' => Carbon::today(),
        ]);
    })->toThrow(QueryException::class);
});

it('deja que varias personas ocupen el mismo puesto a la vez', function (): void {
    $puesto = Puesto::factory()->create();

    ($this->asignar)(Persona::factory()->create(), $puesto);
    ($this->asignar)(Persona::factory()->create(), $puesto);

    expect(AsignacionPuesto::query()->where('puesto_id', $puesto->id)->whereNull('hasta')->count())
        ->toBe(2);
});

/* --- Las pantallas ------------------------------------------------------- */

it('lista los puestos con sus cifras', function (): void {
    Puesto::factory()->count(2)->create();
    Puesto::factory()->caracterizado()->create();

    $this->actingAs($this->usuario)
        ->get('/puestos')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('puestos/Index')
            ->where('total', 3)
            ->where('sinCaracterizar', 2)
            ->where('vacantes', 3)
            ->has('filas', 3));
});

it('pinta el organigrama con quien ocupa cada puesto', function (): void {
    $direccion = Puesto::factory()->create(['titulo' => 'Dirección']);
    $area = Puesto::factory()->bajo($direccion)->create();
    ($this->asignar)(Persona::factory()->create(), $area);

    $this->actingAs($this->usuario)
        ->get('/puestos/organigrama')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('puestos/Organigrama')
            ->has('nodos', 2)
            ->where('nodos.0.profundidad', 0)
            ->where('nodos.1.profundidad', 1)
            ->has('nodos.1.ocupantes', 1));
});

it('propone el código del siguiente puesto, sin año', function (): void {
    Puesto::factory()->create(['codigo' => 'PUE-007']);
    Puesto::factory()->create(['codigo' => 'PUE-008']);

    $this->actingAs($this->usuario)
        ->get('/puestos/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('sugerencia.codigo', 'PUE-009'));
});

it('crea un puesto', function (): void {
    $this->actingAs($this->usuario)
        ->post('/puestos', [
            'codigo' => 'PUE-100',
            'titulo' => 'Técnico de sistemas',
            'competencias' => 'Tres años de experiencia.',
        ])
        ->assertRedirect();

    expect(Puesto::query()->where('codigo', 'PUE-100')->sole()->estaCaracterizado())->toBeTrue();
});

/**
 * El ciclo se rechaza también por la interfaz, y con un mensaje que habla de
 * organigramas: `reporta_a_id` no entra por asignación masiva, pasa por
 * `AsignarSuperior`.
 */
it('la pantalla rechaza el ciclo con un error legible', function (): void {
    $uno = Puesto::factory()->create();
    $dos = Puesto::factory()->bajo($uno)->create();
    $tres = Puesto::factory()->bajo($dos)->create();

    $this->actingAs($this->usuario)
        ->put("/puestos/{$uno->id}", [
            'codigo' => $uno->codigo,
            'titulo' => $uno->titulo,
            'reporta_a_id' => $tres->id,
        ])
        ->assertSessionHasErrors('reporta_a_id');

    expect($uno->refresh()->reporta_a_id)->toBeNull();
});

it('no borra un puesto que alguien ha ocupado', function (): void {
    $puesto = Puesto::factory()->create();
    ($this->asignar)(Persona::factory()->create(), $puesto);

    $this->actingAs($this->usuario)
        ->delete("/puestos/{$puesto->id}")
        ->assertSessionHasErrors('puesto');

    expect(Puesto::query()->whereKey($puesto->id)->exists())->toBeTrue();
});

it('no enseña el puesto de otra organización', function (): void {
    comoOrganizacion();
    $ajeno = Puesto::factory()->create();

    comoOrganizacion($this->organizacion);

    // 404 y no 403: decir «existe pero no es tuyo» ya sería filtrar.
    $this->actingAs($this->usuario)
        ->get("/puestos/{$ajeno->id}")
        ->assertNotFound();
});

it('asigna el puesto desde la ficha de la persona', function (): void {
    $persona = Persona::factory()->create();
    $puesto = Puesto::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/personas/{$persona->id}/puesto", [
            'puesto_id' => $puesto->id,
            'desde' => Carbon::today()->toDateString(),
        ])
        ->assertRedirect();

    expect($persona->refresh()->puestoVigente()?->id)->toBe($puesto->id);
});

/**
 * `scopeBindings()` con el plural español escrito a mano: sin
 * `resolveChildRouteBinding`, la asignación de otra persona se cerraría desde
 * ésta. Es la quinta vez en el producto.
 */
it('no cierra la asignación de otra persona', function (): void {
    $suya = Persona::factory()->create();
    $ajena = Persona::factory()->create();
    $asignacion = ($this->asignar)($ajena, Puesto::factory()->create());

    $this->actingAs($this->usuario)
        ->delete("/personas/{$suya->id}/asignaciones/{$asignacion->id}")
        ->assertNotFound();

    expect($asignacion->refresh()->estaVigente())->toBeTrue();
});

it('la tabla de personas enseña el puesto vigente', function (): void {
    $persona = Persona::factory()->create();
    ($this->asignar)($persona, Puesto::factory()->create(['titulo' => 'Comercial']));

    $this->actingAs($this->usuario)
        ->get('/personas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.puesto', 'Comercial'));
});
