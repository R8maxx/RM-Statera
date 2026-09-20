<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Enums\OrigenTarea;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Las costuras del § 4.10 con lo que ya existía
|--------------------------------------------------------------------------
|
| Tres enganches llevaban puestos desde hacía meses, esperando este módulo:
| `OrigenTarea::Incidente`, `OrigenNoConformidad::Incidente` y
| —envejecida desde el § 4.15— `OrigenNoConformidad::RevisionDireccion`.
|
| Y uno nuevo: `no_conformidades.incidente_id`, espejo exacto de `hallazgo_id`.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->incidente = Incidente::factory()->create();
});

it('ofrece incidente como origen de una tarea', function (): void {
    expect(OrigenTarea::Incidente->disponible())->toBeTrue()
        ->and(OrigenTarea::disponibles())->toContain(OrigenTarea::Incidente);
});

/**
 * `RevisionDireccion` llevaba en `false` desde que el § 4.15 se construyó: una
 * frase que envejeció en el tramo anterior.
 */
it('ofrece incidente y revisión por la dirección como origen de una no conformidad', function (): void {
    expect(OrigenNoConformidad::Incidente->disponible())->toBeTrue()
        ->and(OrigenNoConformidad::RevisionDireccion->disponible())->toBeTrue()
        ->and(OrigenNoConformidad::disponibles())->toHaveCount(4);
});

it('ofrece incidente como origen de una mejora', function (): void {
    expect(OrigenMejora::disponibles())->toContain(OrigenMejora::Incidente);
});

/*
|--------------------------------------------------------------------------
| El vínculo con la no conformidad
|--------------------------------------------------------------------------
*/

it('abre la no conformidad desde el incidente con el origen puesto', function (): void {
    $this->actingAs($this->usuario)
        ->get("/no-conformidades/crear?incidente={$this->incidente->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('no-conformidades/Formulario')
            ->where('incidente.codigo', $this->incidente->codigo)
            ->where('sugerencia.origen', OrigenNoConformidad::Incidente->value));
});

/** Un incidente se trata una vez, y lo impone el índice único. */
it('lleva a la no conformidad que ya existe en vez de dejar abrir otra', function (): void {
    $nc = NoConformidad::factory()->deIncidente($this->incidente->id)->create();

    $this->actingAs($this->usuario)
        ->get("/no-conformidades/crear?incidente={$this->incidente->id}")
        ->assertRedirect("/no-conformidades/{$nc->id}");
});

it('rechaza el segundo tratamiento del mismo incidente en la base', function (): void {
    NoConformidad::factory()->deIncidente($this->incidente->id)->create();

    expect(fn () => NoConformidad::factory()->deIncidente($this->incidente->id)->create())
        ->toThrow(QueryException::class);
});

/**
 * **No puede venir de un hallazgo y de un incidente a la vez.** Con las dos
 * columnas puestas, `origen` tendría que valer dos cosas y los dos `CHECK`
 * anteriores se contradirían con un mensaje que no explica nada.
 */
it('no admite una no conformidad que venga de un hallazgo y de un incidente', function (): void {
    $this->actingAs($this->usuario)
        ->post('/no-conformidades', [
            'codigo' => 'NC-2026-99',
            'origen' => OrigenNoConformidad::Incidente->value,
            'incidente_id' => $this->incidente->id,
            'hallazgo_id' => 1,
            'descripcion' => 'Algo.',
            'fecha_deteccion' => now()->toDateString(),
        ])
        ->assertSessionHasErrors();
});

it('exige que el origen case con el incidente', function (): void {
    $this->actingAs($this->usuario)
        ->post('/no-conformidades', [
            'codigo' => 'NC-2026-98',
            'origen' => OrigenNoConformidad::Propia->value,
            'incidente_id' => $this->incidente->id,
            'descripcion' => 'Algo.',
            'fecha_deteccion' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('origen');
});

/*
|--------------------------------------------------------------------------
| El vínculo con la mejora, que NO lleva clave foránea
|--------------------------------------------------------------------------
|
| Mismo reparto que `OrigenMejora::RevisionDireccion`: la mejora que sale de una
| lección aprendida no «trata» el incidente —ése ya está cerrado—, así que
| atarla sería fingir una trazabilidad que no hay. Lo que sí se hereda es el
| título.
|
*/

it('apunta la mejora desde el incidente con el origen y el título puestos', function (): void {
    $this->actingAs($this->usuario)
        ->get("/mejoras/crear?incidente={$this->incidente->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('mejoras/Formulario')
            ->where('sugerencia.origen', OrigenMejora::Incidente->value)
            ->where('sugerencia.titulo', "Lección aprendida de {$this->incidente->codigo}: {$this->incidente->titulo}"));
});

/*
|--------------------------------------------------------------------------
| Los activos afectados
|--------------------------------------------------------------------------
*/

it('vincula varios activos a un solo incidente', function (): void {
    $uno = Activo::factory()->de($this->organizacion)->create();
    $dos = Activo::factory()->de($this->organizacion)->create();

    $this->actingAs($this->usuario)
        ->put("/incidentes/{$this->incidente->id}", [
            'codigo' => $this->incidente->codigo,
            'titulo' => $this->incidente->titulo,
            'descripcion' => $this->incidente->descripcion,
            'clasificacion' => $this->incidente->clasificacion->value,
            'peligrosidad' => $this->incidente->peligrosidad->value,
            'fecha_deteccion' => $this->incidente->fecha_deteccion->toDateTimeString(),
            'activos' => [$uno->id, $dos->id],
        ])
        ->assertRedirect();

    expect($this->incidente->activos()->count())->toBe(2);
});

/**
 * Los activos se resuelven **por el modelo**, que es lo que impide colgar del
 * incidente propio el activo de otro cliente pasando su id a mano.
 *
 * **Y la petición entera se rechaza antes de llegar ahí**, porque el `exists` de
 * la regla es una consulta cruda y ésa la corta RLS —la tercera capa, que es
 * justamente la que cubre lo que no pasa por Eloquent—. Las dos hacen falta: la
 * regla protege la petición y el controlador protege al importador.
 */
it('rechaza la petición que nombra un activo de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();

    $ajeno = app(ContextoOrganizacion::class)
        ->paraOrganizacion($ajena, fn (): Activo => Activo::factory()->de($ajena)->create());

    app(ContextoOrganizacion::class)->establecer($this->organizacion);

    $propio = Activo::factory()->de($this->organizacion)->create();

    $this->actingAs($this->usuario)
        ->put("/incidentes/{$this->incidente->id}", [
            'codigo' => $this->incidente->codigo,
            'titulo' => $this->incidente->titulo,
            'descripcion' => $this->incidente->descripcion,
            'clasificacion' => $this->incidente->clasificacion->value,
            'peligrosidad' => $this->incidente->peligrosidad->value,
            'fecha_deteccion' => $this->incidente->fecha_deteccion->toDateTimeString(),
            'activos' => [$propio->id, $ajeno->id],
        ])
        ->assertSessionHasErrors('activos.1');

    expect($this->incidente->activos()->count())->toBe(0);
});
