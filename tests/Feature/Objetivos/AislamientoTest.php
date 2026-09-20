<?php

declare(strict_types=1);

use App\Domain\Metrica\Models\Indicador;
use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Objetivo\VincularActuacion;
use App\Domain\Objetivo\VincularIndicador;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Models\Tarea;

/*
|--------------------------------------------------------------------------
| El aislamiento de los objetivos de seguridad
|--------------------------------------------------------------------------
|
| A qué se ha comprometido la dirección de una organización este año, y cuáles
| no ha alcanzado, es información de negocio. Filtrarla es exactamente igual de
| grave que filtrar su informe de auditoría.
|
| Se responde **404 y no 403**: decir «existe pero no es tuyo» ya sería filtrar,
| y es la regla de todo el producto.
|
| **Y hay un segundo eje**, el que de verdad cuesta ver: entre dos objetivos de
| la MISMA organización no hay ninguna de las tres capas. Lo que impide
| desvincular desde éste el indicador de aquél es `scopeBindings()`, y sin un
| test eso se rompe sin que nadie se entere. El precedente es la checklist de una
| auditoría.
|
*/

beforeEach(function (): void {
    $this->propia = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->ajena = Organizacion::factory()->create();

    $this->enLaAjena = fn (callable $callback) => app(ContextoOrganizacion::class)
        ->paraOrganizacion($this->ajena, $callback);
});

it('no lista los objetivos de otra organización', function (): void {
    Objetivo::factory()->create(['codigo' => 'OBJ-PROPIO']);

    ($this->enLaAjena)(fn () => Objetivo::factory()->create(['codigo' => 'OBJ-AJENO']));

    app(ContextoOrganizacion::class)->establecer($this->propia);

    expect(Objetivo::query()->pluck('codigo')->all())->toBe(['OBJ-PROPIO']);
});

it('responde 404 al pedir el objetivo de otra organización', function (): void {
    $ajeno = ($this->enLaAjena)(fn (): Objetivo => Objetivo::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)->get("/objetivos/{$ajeno->id}")->assertNotFound();
    $this->actingAs($this->usuario)->get("/objetivos/{$ajeno->id}/editar")->assertNotFound();
    $this->actingAs($this->usuario)->delete("/objetivos/{$ajeno->id}")->assertNotFound();
    $this->actingAs($this->usuario)
        ->post("/objetivos/{$ajeno->id}/estado", ['estado' => 'aprobado'])
        ->assertNotFound();
});

it('no se vincula el indicador de otra organización', function (): void {
    $ajeno = ($this->enLaAjena)(fn (): Indicador => Indicador::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $objetivo = Objetivo::factory()->create();

    /*
     * Se queda en el `exists` del `FormRequest` y ni llega al controlador: esa
     * regla corre bajo RLS, así que para esta sesión el indicador de otro cliente
     * **no existe**, y el mensaje que sale es el de un valor inválido. Es el mismo
     * resultado que el 404 del `findOrFail` de más adentro —no se filtra que
     * exista—, pero llega antes y con el error puesto en su campo.
     */
    $this->actingAs($this->usuario)
        ->post("/objetivos/{$objetivo->id}/indicadores", ['indicador_id' => $ajeno->id])
        ->assertSessionHasErrors('indicador_id');

    expect($objetivo->indicadores()->count())->toBe(0);
});

it('no se vincula la tarea de otra organización', function (): void {
    $ajena = ($this->enLaAjena)(fn (): Tarea => Tarea::factory()->create());

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $objetivo = Objetivo::factory()->create();

    // Igual que arriba: el `exists` corre bajo RLS y la tarea ajena no existe.
    $this->actingAs($this->usuario)
        ->post("/objetivos/{$objetivo->id}/actuaciones/vincular", ['tarea_id' => $ajena->id])
        ->assertSessionHasErrors('tarea_id');

    expect($objetivo->tareas()->count())->toBe(0);
});

/*
 * El segundo eje, el que las tres capas no tapan: dos objetivos de la misma
 * organización. Sin `scopeBindings()`, la ruta anidada resolvería el indicador
 * por su id suelto y lo desvincularía del objetivo equivocado.
 */
it('no desvincula desde un objetivo lo que cuelga de otro', function (): void {
    $indicador = Indicador::factory()->create();

    $mio = Objetivo::factory()->create();
    $ajeno = Objetivo::factory()->create();

    app(VincularIndicador::class)->vincular($ajeno, $indicador, $this->usuario);

    $this->actingAs($this->usuario)
        ->delete("/objetivos/{$mio->id}/indicadores/{$indicador->id}")
        ->assertNotFound();

    expect($ajeno->indicadores()->count())->toBe(1);
});

it('no desvincula desde un objetivo la actuación de otro', function (): void {
    $tarea = Tarea::factory()->create();

    $mio = Objetivo::factory()->create();
    $ajeno = Objetivo::factory()->create();

    app(VincularActuacion::class)->vincular($ajeno, $tarea, $this->usuario);

    $this->actingAs($this->usuario)
        ->delete("/objetivos/{$mio->id}/actuaciones/{$tarea->id}")
        ->assertNotFound();

    expect($ajeno->tareas()->count())->toBe(1);
});
