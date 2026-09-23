<?php

declare(strict_types=1);

use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Continuidad\DerivarDePrueba;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Excepciones\TransicionDePruebaNoPermitida;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Models\Documento;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Las costuras de una prueba de continuidad: tareas, no conformidades y mejoras
|--------------------------------------------------------------------------
|
| Una prueba que sale parcial o fallida destapa un hueco entre lo que el plan
| prometía y lo que de verdad pasó —op.cont.3—, y ese hueco tiene que poder
| convertirse en trabajo sin volver a escribir el mismo hecho en tres sitios.
| Éste es el precedente exacto de `AbrirAccionCorrectiva` y
| `AbrirActuacionDeMejora`, aplicado a la tercera fuente reactiva del producto.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->plan = Documento::factory()->planContinuidad()->create();
    $this->derivar = app(DerivarDePrueba::class);

    $this->fallida = PruebaContinuidad::factory()
        ->deDocumento($this->plan)
        ->realizada(ResultadoPrueba::Fallida)
        ->create();
});

// --- El dominio: DerivarDePrueba ----------------------------------------

it('deriva una tarea de una prueba fallida, con el origen y la pivote puestos', function (): void {
    $tarea = $this->derivar->tarea($this->fallida, [
        'titulo' => 'Reescribir el guion de failover',
        'prioridad' => 'alta',
    ], $this->usuario);

    expect($tarea->origen)->toBe(OrigenTarea::Continuidad)
        ->and($this->fallida->tareas()->pluck('tareas.id'))->toContain($tarea->id)
        ->and(Tarea::query()->count())->toBe(1);
});

it('deriva una no conformidad de una prueba fallida, con el origen y la clave foránea puestos', function (): void {
    $noConformidad = $this->derivar->noConformidad($this->fallida, [
        'codigo' => 'NC-2026-40',
        'descripcion' => 'El centro alternativo no levantó dentro del RTO.',
        'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    expect($noConformidad->origen)->toBe(OrigenNoConformidad::PruebaContinuidad)
        ->and($noConformidad->prueba_continuidad_id)->toBe($this->fallida->id)
        ->and($this->fallida->fresh()?->noConformidad?->id)->toBe($noConformidad->id);
});

it('deriva una mejora de una prueba fallida, con el origen puesto y sin clave foránea', function (): void {
    $mejora = $this->derivar->mejora($this->fallida, [
        'codigo' => 'OM-2026-40',
        'titulo' => 'Automatizar el failover del correo',
        'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    expect($mejora->origen)->toBe(OrigenMejora::PruebaContinuidad)
        // Espejo de `OrigenMejora::Incidente`: no lleva clave foránea, así que
        // no hay columna que comprobar en el modelo — sólo la etiqueta.
        ->and(Schema::hasColumn('mejoras', 'prueba_continuidad_id'))->toBeFalse();
});

it('rechaza derivar de una prueba superada', function (): void {
    $superada = PruebaContinuidad::factory()->deDocumento($this->plan)->realizada(ResultadoPrueba::Superada)->create();

    expect(fn () => $this->derivar->tarea($superada, ['titulo' => 'No debería crearse', 'prioridad' => 'media'], $this->usuario))
        ->toThrow(TransicionDePruebaNoPermitida::class);

    expect(fn () => $this->derivar->noConformidad($superada, [
        'codigo' => 'NC-2026-41', 'descripcion' => 'No debería crearse.', 'fecha_deteccion' => Carbon::today(),
    ], $this->usuario))->toThrow(TransicionDePruebaNoPermitida::class);

    expect(fn () => $this->derivar->mejora($superada, [
        'codigo' => 'OM-2026-41', 'titulo' => 'No debería crearse', 'fecha_deteccion' => Carbon::today(),
    ], $this->usuario))->toThrow(TransicionDePruebaNoPermitida::class);

    expect(Tarea::query()->count())->toBe(0)
        ->and(NoConformidad::query()->count())->toBe(0)
        ->and(Mejora::query()->count())->toBe(0);
});

it('rechaza derivar de una prueba todavía planificada', function (): void {
    $planificada = PruebaContinuidad::factory()->deDocumento($this->plan)->planificada()->create();

    expect(fn () => $this->derivar->tarea($planificada, ['titulo' => 'No debería crearse', 'prioridad' => 'media'], $this->usuario))
        ->toThrow(TransicionDePruebaNoPermitida::class);
});

it('una no conformidad no puede tener hallazgo y prueba de continuidad a la vez', function (): void {
    $hallazgo = Hallazgo::factory()->create();

    /*
     * Con las dos columnas puestas no hay `origen` que satisfaga a la vez
     * `no_conformidades_hallazgo_origen_check` (exige `auditoria`) y
     * `no_conformidades_prueba_continuidad_origen_check` (exige
     * `prueba_continuidad`): Postgres para en la primera que evalúa, y
     * `num_nonnulls(...) <= 1` es la que cierra el hueco si algún día una de
     * las dos anteriores se relajase. Lo que importa aquí es que la fila no
     * entra, sea cual sea el mensaje.
     *
     * Sin consulta después: el `CHECK` deja la transacción de Postgres
     * abortada y cualquier consulta posterior en el mismo test fallaría por
     * eso y no por lo que se quiere comprobar — mismo patrón que
     * `Incidentes/CosturasTest::rechaza el segundo tratamiento…`.
     */
    expect(fn () => NoConformidad::query()->create([
        'codigo' => 'NC-2026-77',
        'origen' => OrigenNoConformidad::PruebaContinuidad->value,
        'hallazgo_id' => $hallazgo->id,
        'prueba_continuidad_id' => $this->fallida->id,
        'descripcion' => 'No debería poder crearse con las dos procedencias.',
        'fecha_deteccion' => Carbon::today(),
    ]))->toThrow(QueryException::class, 'check constraint');
});

it('borrar la prueba deja la no conformidad con prueba_continuidad_id a null', function (): void {
    $noConformidad = $this->derivar->noConformidad($this->fallida, [
        'codigo' => 'NC-2026-42',
        'descripcion' => 'Fallo destapado por la prueba.',
        'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    $this->fallida->delete();

    expect($noConformidad->fresh()?->prueba_continuidad_id)->toBeNull()
        // Y sigue viva: `nullOnDelete`, no cascada. Borrar la prueba no se lleva
        // por delante la prueba —documental— de que se trató.
        ->and(NoConformidad::query()->count())->toBe(1);
});

it('rechaza una segunda no conformidad de la misma prueba con una excepción de dominio', function (): void {
    $this->derivar->noConformidad($this->fallida, [
        'codigo' => 'NC-2026-46', 'descripcion' => 'Primera.', 'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    expect(fn () => $this->derivar->noConformidad($this->fallida->fresh(), [
        'codigo' => 'NC-2026-47', 'descripcion' => 'Segunda.', 'fecha_deteccion' => Carbon::today(),
    ], $this->usuario))->toThrow(TransicionDePruebaNoPermitida::class);

    expect(NoConformidad::query()->count())->toBe(1);
});

// --- Por HTTP: las tres rutas y sus permisos -----------------------------

it('abre una tarea desde la ficha de la prueba', function (): void {
    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$this->fallida->id}/tareas", [
            'titulo' => 'Reescribir el guion de failover',
            'prioridad' => 'alta',
        ])
        ->assertRedirect();

    $tarea = Tarea::query()->sole();

    expect($tarea->origen)->toBe(OrigenTarea::Continuidad)
        ->and($this->fallida->tareas()->pluck('tareas.id'))->toContain($tarea->id);
});

it('abre una no conformidad desde la ficha de la prueba y lleva a su ficha', function (): void {
    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$this->fallida->id}/no-conformidades", [
            'codigo' => 'NC-2026-43',
            'descripcion' => 'El RTO no se cumplió.',
            'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertRedirect('/no-conformidades/'.NoConformidad::query()->sole()->id);

    expect(NoConformidad::query()->sole()->prueba_continuidad_id)->toBe($this->fallida->id);
});

it('abre una mejora desde la ficha de la prueba y lleva a su ficha', function (): void {
    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$this->fallida->id}/mejoras", [
            'codigo' => 'OM-2026-43',
            'titulo' => 'Automatizar el failover',
            'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertRedirect('/mejoras/'.Mejora::query()->sole()->id);

    expect(Mejora::query()->sole()->origen)->toBe(OrigenMejora::PruebaContinuidad);
});

it('rechaza derivar por HTTP de una prueba planificada, con el error en el formulario', function (): void {
    $planificada = PruebaContinuidad::factory()->deDocumento($this->plan)->planificada()->create();

    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$planificada->id}/tareas", [
            'titulo' => 'No debería crearse',
            'prioridad' => 'media',
        ])
        ->assertSessionHasErrors('titulo');

    expect(Tarea::query()->count())->toBe(0);
});

it('el permiso de cada ruta es el del módulo destino, no continuidad.gestionar', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)
        ->post("/continuidad/pruebas/{$this->fallida->id}/tareas", ['titulo' => 'x', 'prioridad' => 'media'])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post("/continuidad/pruebas/{$this->fallida->id}/no-conformidades", [
            'codigo' => 'NC-2026-44', 'descripcion' => 'x', 'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post("/continuidad/pruebas/{$this->fallida->id}/mejoras", [
            'codigo' => 'OM-2026-44', 'titulo' => 'x', 'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertForbidden();

    expect(Tarea::query()->count())->toBe(0)
        ->and(NoConformidad::query()->count())->toBe(0)
        ->and(Mejora::query()->count())->toBe(0);
});

/*
 * Dos pestañas o un doble envío: la segunda no conformidad vuelve al
 * formulario con el error en el campo, no como un `QueryException` crudo.
 */
it('una segunda no conformidad por HTTP vuelve con el error en el formulario', function (): void {
    $datos = fn (string $codigo): array => [
        'codigo' => $codigo, 'descripcion' => 'El RTO no se cumplió.', 'fecha_deteccion' => Carbon::today()->toDateString(),
    ];

    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$this->fallida->id}/no-conformidades", $datos('NC-2026-48'))
        ->assertRedirect();

    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$this->fallida->id}/no-conformidades", $datos('NC-2026-49'))
        ->assertSessionHasErrors('codigo');

    expect(NoConformidad::query()->count())->toBe(1);
});

/*
 * Derivar parte de la ficha de la prueba: sin `continuidad.ver` no se deriva,
 * aunque se tenga el permiso del módulo destino. Se le quita al rol y no al
 * usuario, porque el permiso viene del rol.
 */
it('derivar exige también continuidad.ver', function (): void {
    $rol = Role::query()
        ->where('name', Rol::ResponsableSeguridad->value)
        ->where('organizacion_id', $this->organizacion->id)
        ->firstOrFail();

    // El rol conserva los tres permisos destino: lo único que falta es leer.
    expect($rol->hasPermissionTo('tareas.gestionar'))->toBeTrue()
        ->and($rol->hasPermissionTo('no_conformidades.gestionar'))->toBeTrue()
        ->and($rol->hasPermissionTo('mejoras.gestionar'))->toBeTrue();

    $rol->revokePermissionTo(Permiso::ContinuidadVer->value);

    app()->forgetInstance(PermissionRegistrar::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $usuario = $this->usuario->fresh();

    $this->actingAs($usuario)
        ->post("/continuidad/pruebas/{$this->fallida->id}/tareas", ['titulo' => 'x', 'prioridad' => 'media'])
        ->assertForbidden();

    $this->actingAs($usuario)
        ->post("/continuidad/pruebas/{$this->fallida->id}/no-conformidades", [
            'codigo' => 'NC-2026-50', 'descripcion' => 'x', 'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertForbidden();

    $this->actingAs($usuario)
        ->post("/continuidad/pruebas/{$this->fallida->id}/mejoras", [
            'codigo' => 'OM-2026-50', 'titulo' => 'x', 'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertForbidden();

    expect(Tarea::query()->count())->toBe(0)
        ->and(NoConformidad::query()->count())->toBe(0)
        ->and(Mejora::query()->count())->toBe(0);
});

/*
 * Editar una no conformidad nacida de una prueba y cambiarle el origen subía
 * como un 500 por `no_conformidades_prueba_continuidad_origen_check`. Ahora
 * vuelve al formulario, que además ya no ofrece el desplegable.
 */
it('no deja cambiar el origen de una no conformidad nacida de una prueba', function (): void {
    $nc = $this->derivar->noConformidad($this->fallida, [
        'codigo' => 'NC-2026-51', 'descripcion' => 'El RTO no se cumplió.', 'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    $this->actingAs($this->usuario)
        ->get("/no-conformidades/{$nc->id}/editar")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('origenFijo', true));

    $this->actingAs($this->usuario)
        ->put("/no-conformidades/{$nc->id}", [
            'codigo' => $nc->codigo,
            'origen' => OrigenNoConformidad::Propia->value,
            'descripcion' => $nc->descripcion,
            'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertSessionHasErrors('origen');

    expect($nc->fresh()?->origen)->toBe(OrigenNoConformidad::PruebaContinuidad);

    // Y con el mismo origen, la edición sigue funcionando.
    $this->actingAs($this->usuario)
        ->put("/no-conformidades/{$nc->id}", [
            'codigo' => $nc->codigo,
            'origen' => OrigenNoConformidad::PruebaContinuidad->value,
            'descripcion' => 'Reescrita.',
            'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect($nc->fresh()?->descripcion)->toBe('Reescrita.');
});

it('la ficha de la prueba lista lo derivado y sólo ofrece los botones cuando toca', function (): void {
    $tarea = $this->derivar->tarea($this->fallida, ['titulo' => 'Reescribir el guion', 'prioridad' => 'alta'], $this->usuario);
    $noConformidad = $this->derivar->noConformidad($this->fallida, [
        'codigo' => 'NC-2026-45', 'descripcion' => 'x', 'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    $this->actingAs($this->usuario)
        ->get("/continuidad/pruebas/{$this->fallida->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('continuidad/pruebas/Ficha')
            ->where('puedeDerivar', true)
            ->where('puedeAbrirTarea', true)
            ->where('puedeTratar', true)
            ->where('puedeMejorar', true)
            ->where('tareasDerivadas.0.id', $tarea->id)
            ->where('noConformidadDerivada.id', $noConformidad->id));

    // Una prueba superada no ofrece los botones, aunque el usuario pueda.
    $superada = PruebaContinuidad::factory()->deDocumento($this->plan)->realizada(ResultadoPrueba::Superada)->create();

    $this->actingAs($this->usuario)
        ->get("/continuidad/pruebas/{$superada->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('puedeDerivar', false)
            ->where('puedeAbrirTarea', false)
            ->where('puedeTratar', false)
            ->where('puedeMejorar', false));
});
