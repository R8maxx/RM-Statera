<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Models\Documento;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El BIA por la interfaz
|--------------------------------------------------------------------------
|
| **Tres verbos y el tercero de supervisión.** `continuidad.ver` lee,
| `continuidad.gestionar` registra y edita, y `continuidad.aprobar` es lo
| único que el técnico no tiene: aceptar un RTO es aceptar un riesgo.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('lista, crea y enseña un BIA', function (): void {
    $servicio = Activo::factory()->create(['tipo' => 'servicios']);

    $this->actingAs($this->usuario)
        ->post('/continuidad/bia', BiaServicio::factory()->raw(['activo_id' => $servicio->id]))
        ->assertRedirect();

    $this->actingAs($this->usuario)
        ->get('/continuidad/bia')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->component('continuidad/bia/Index'));

    $bia = BiaServicio::query()->sole();

    $this->actingAs($this->usuario)
        ->get("/continuidad/bia/{$bia->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('continuidad/bia/Ficha')
            ->has('umbral')
            ->has('dependencias'));
});

it('el técnico no aprueba un BIA', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $bia = BiaServicio::factory()->create();

    $this->actingAs($tecnico)
        ->post("/continuidad/bia/{$bia->id}/estado", ['estado' => 'aprobado'])
        ->assertForbidden();
});

it('el auditor ve pero no escribe', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $bia = BiaServicio::factory()->create();

    $this->actingAs($auditor)->get('/continuidad/bia')->assertOk();
    $this->actingAs($auditor)->get("/continuidad/bia/{$bia->id}")->assertOk();
    $this->actingAs($auditor)
        ->post("/continuidad/bia/{$bia->id}/estado", ['estado' => 'aprobado'])
        ->assertForbidden();
});

it('rechaza un activo que no es servicio en el formulario', function (): void {
    $servidor = Activo::factory()->create(['tipo' => 'hardware']);

    $this->actingAs($this->usuario)
        ->post('/continuidad/bia', BiaServicio::factory()->raw(['activo_id' => $servidor->id]))
        ->assertSessionHasErrors('activo_id');
});

it('ordena por revisión por defecto, con la clave de la columna y no la de la base', function (): void {
    BiaServicio::factory()->count(2)->create();

    // La regresión concreta: `ordenPorDefecto()` devolviendo `fecha_revision`
    // -el nombre de la columna en la base- en vez de `revision` -su clave-
    // hace que `ConsultaRecurso` no encuentre la columna, así que la flecha de
    // la cabecera nunca se enciende y, en cuanto se pagina o se filtra, spatie
    // descarta el `sort` que no reconoce y el ORDER BY desaparece en silencio.
    $this->actingAs($this->usuario)
        ->get('/continuidad/bia')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('continuidad/bia/Index')
            ->where('meta.orden', 'revision'));
});

it('edita un BIA sólo con los campos de contenido y lo deja en borrador', function (): void {
    $bia = BiaServicio::factory()->enEstado(EstadoBia::Aprobado)->create();

    $this->actingAs($this->usuario)
        ->put("/continuidad/bia/{$bia->id}", [
            'impacto_4h' => 'bajo',
            'impacto_1d' => 'bajo',
            'impacto_3d' => 'bajo',
            'impacto_1s' => 'bajo',
            'impacto_1m' => 'bajo',
            'rto_horas' => 48,
            'rpo_horas' => 8,
        ])
        ->assertRedirect();

    expect($bia->fresh()->estado)->toBe(EstadoBia::Borrador)
        ->and($bia->fresh()->rto_horas)->toBe(48);
});

/*
 * Dos entradas predecibles que antes subían como un 500 con el nombre de una
 * restricción: un impacto que baja con el tiempo (`bia_servicios_monotonia_check`)
 * y un segundo BIA para el mismo servicio (el único `(organizacion_id,
 * activo_id)`). Las dos tienen que volver al formulario con el error en su campo.
 */
it('rechaza en el formulario un impacto que baja con el tiempo, al registrar y al editar', function (): void {
    $servicio = Activo::factory()->create(['tipo' => 'servicios']);

    $this->actingAs($this->usuario)
        ->post('/continuidad/bia', BiaServicio::factory()->raw([
            'activo_id' => $servicio->id,
            'impacto_4h' => 'alto',
            'impacto_1d' => 'medio',
            'impacto_3d' => 'muy_alto',
            'impacto_1s' => 'muy_alto',
            'impacto_1m' => 'muy_alto',
        ]))
        ->assertSessionHasErrors('impacto_1d');

    expect(BiaServicio::query()->count())->toBe(0);

    $bia = BiaServicio::factory()->create([
        'impacto_4h' => 'alto', 'impacto_1d' => 'alto', 'impacto_3d' => 'muy_alto',
        'impacto_1s' => 'muy_alto', 'impacto_1m' => 'muy_alto',
    ]);

    $this->actingAs($this->usuario)
        ->put("/continuidad/bia/{$bia->id}", [
            'impacto_4h' => 'alto',
            'impacto_1d' => 'alto',
            'impacto_3d' => 'muy_alto',
            'impacto_1s' => 'medio',
            'impacto_1m' => 'muy_alto',
            'rto_horas' => 24,
            'rpo_horas' => 4,
        ])
        ->assertSessionHasErrors('impacto_1s');

    expect($bia->fresh()->impacto_1s->value)->toBe('muy_alto');
});

it('rechaza en el formulario un segundo BIA para el mismo servicio y no lo ofrece', function (): void {
    $bia = BiaServicio::factory()->create();
    $libre = Activo::factory()->create(['tipo' => 'servicios']);

    $this->actingAs($this->usuario)
        ->post('/continuidad/bia', BiaServicio::factory()->raw(['activo_id' => $bia->activo_id]))
        ->assertSessionHasErrors('activo_id');

    expect(BiaServicio::query()->count())->toBe(1);

    $this->actingAs($this->usuario)
        ->get('/continuidad/bia/crear')
        ->assertInertia(function (AssertableInertia $pagina) use ($bia, $libre): void {
            $valores = array_column($pagina->toArray()['props']['servicios'], 'valor');

            expect($valores)->toContain((string) $libre->id)
                ->and($valores)->not->toContain((string) $bia->activo_id);
        });
});

/*
|--------------------------------------------------------------------------
| Las pruebas de un plan de continuidad
|--------------------------------------------------------------------------
|
| **Dos verbos y ninguno de supervisión.** `continuidad.ver` lee y
| `continuidad.gestionar` planifica, edita, registra el resultado y cancela:
| aquí no hay nada que aceptar como riesgo, hay algo que comprobar.
|
*/

it('planifica una prueba y la enseña', function (): void {
    $plan = Documento::factory()->planContinuidad()->create();
    $servicio = Activo::factory()->create(['tipo' => 'servicios']);

    $this->actingAs($this->usuario)
        ->post('/continuidad/pruebas', [
            'codigo' => 'PC-2026-01',
            'titulo' => 'Simulacro de caída del CPD',
            'documento_id' => $plan->id,
            'tipo' => 'simulacro',
            'fecha_prevista' => now()->addDays(15)->toDateString(),
            'servicios' => [$servicio->id],
        ])
        ->assertRedirect();

    $prueba = PruebaContinuidad::query()->sole();

    expect($prueba->servicios()->pluck('activos.id'))->toEqual(collect([$servicio->id]));

    $this->actingAs($this->usuario)
        ->get('/continuidad/pruebas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->component('continuidad/pruebas/Index'));

    $this->actingAs($this->usuario)
        ->get("/continuidad/pruebas/{$prueba->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('continuidad/pruebas/Ficha')
            ->has('servicios')
            ->has('historial'));
});

it('edita una prueba planificada', function (): void {
    $servicio = Activo::factory()->create(['tipo' => 'servicios']);
    $prueba = PruebaContinuidad::factory()->planificada()->create();
    $prueba->servicios()->attach($servicio->id, ['organizacion_id' => $prueba->organizacion_id]);

    $this->actingAs($this->usuario)
        ->put("/continuidad/pruebas/{$prueba->id}", [
            'codigo' => $prueba->codigo,
            'titulo' => 'Título revisado',
            'tipo' => $prueba->tipo->value,
            'fecha_prevista' => $prueba->fecha_prevista->toDateString(),
            'servicios' => [$servicio->id],
        ])
        ->assertRedirect();

    expect($prueba->fresh()->titulo)->toBe('Título revisado');
});

it('no edita una prueba que ya no está planificada', function (): void {
    $servicio = Activo::factory()->create(['tipo' => 'servicios']);
    $prueba = PruebaContinuidad::factory()->realizada()->create();

    $this->actingAs($this->usuario)
        ->put("/continuidad/pruebas/{$prueba->id}", [
            'codigo' => $prueba->codigo,
            'titulo' => 'Otro título',
            'tipo' => $prueba->tipo->value,
            'fecha_prevista' => $prueba->fecha_prevista->toDateString(),
            'servicios' => [$servicio->id],
        ])
        ->assertRedirect("/continuidad/pruebas/{$prueba->id}");

    expect($prueba->fresh()->titulo)->not->toBe('Otro título');
});

it('registra el resultado de una prueba planificada', function (): void {
    $servicio = Activo::factory()->create(['tipo' => 'servicios']);
    $prueba = PruebaContinuidad::factory()->planificada()->create();
    $prueba->servicios()->attach($servicio->id, ['organizacion_id' => $prueba->organizacion_id]);

    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$prueba->id}/resultado", [
            'fecha_realizacion' => now()->toDateString(),
            'resultado' => 'superada',
            'conclusiones' => 'Todo salió según lo previsto.',
            'servicios' => [
                $servicio->id => ['rto_alcanzado_horas' => 4, 'rpo_alcanzado_horas' => 1],
            ],
        ])
        ->assertRedirect();

    $prueba->refresh();

    expect($prueba->estado)->toBe(EstadoPrueba::Realizada)
        ->and($prueba->resultado)->toBe(ResultadoPrueba::Superada)
        ->and((int) $prueba->servicios()->first()->pivot->rto_alcanzado_horas)->toBe(4);
});

it('cancela una prueba planificada', function (): void {
    $prueba = PruebaContinuidad::factory()->planificada()->create();

    $this->actingAs($this->usuario)
        ->post("/continuidad/pruebas/{$prueba->id}/cancelar", [
            'motivo' => 'Se pospone por indisponibilidad del proveedor del centro alternativo.',
        ])
        ->assertRedirect();

    expect($prueba->fresh()->estado)->toBe(EstadoPrueba::Cancelada);
});

it('el técnico planifica y gestiona una prueba', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $plan = Documento::factory()->planContinuidad()->create();
    $servicio = Activo::factory()->create(['tipo' => 'servicios']);

    $this->actingAs($tecnico)
        ->post('/continuidad/pruebas', [
            'codigo' => 'PC-2026-02',
            'titulo' => 'Prueba técnica de restauración',
            'documento_id' => $plan->id,
            'tipo' => 'tecnica',
            'fecha_prevista' => now()->addDays(10)->toDateString(),
            'servicios' => [$servicio->id],
        ])
        ->assertRedirect();

    $prueba = PruebaContinuidad::query()->sole();

    $this->actingAs($tecnico)
        ->post("/continuidad/pruebas/{$prueba->id}/cancelar", ['motivo' => 'Se reprograma.'])
        ->assertRedirect();

    expect($prueba->fresh()->estado)->toBe(EstadoPrueba::Cancelada);
});

it('ordena las pruebas por fecha prevista por defecto y pinta la fecha en la tabla', function (): void {
    $prueba = PruebaContinuidad::factory()->create();

    // La regresión concreta: la columna `prevista` sin `->formato()` intenta
    // leer `$fila->prevista`, que no es ninguna columna de la base, y la celda
    // sale en blanco aunque `ordenPorDefecto()` ya apunte a la clave correcta.
    $this->actingAs($this->usuario)
        ->get('/continuidad/pruebas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('continuidad/pruebas/Index')
            ->where('meta.orden', 'prevista')
            ->where('filas.0.prevista', $prueba->fecha_prevista->toDateString()));
});

it('el auditor ve pero no gestiona una prueba', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $prueba = PruebaContinuidad::factory()->create();

    $this->actingAs($auditor)->get('/continuidad/pruebas')->assertOk();
    $this->actingAs($auditor)->get("/continuidad/pruebas/{$prueba->id}")->assertOk();
    $this->actingAs($auditor)->get('/continuidad/pruebas/crear')->assertForbidden();
    $this->actingAs($auditor)
        ->post("/continuidad/pruebas/{$prueba->id}/cancelar", ['motivo' => 'x'])
        ->assertForbidden();
});

/*
 * RTO y RPO son columnas numéricas, y la celda numérica hace `Number(valor)`:
 * con el formato «72 h» la tabla pintaba «NaN». La unidad va en la cabecera.
 */
it('manda el RTO y el RPO como números para la celda numérica', function (): void {
    BiaServicio::factory()->create(['rto_horas' => 72, 'rpo_horas' => 24]);

    $this->actingAs($this->usuario)
        ->get('/continuidad/bia')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.rto', 72)
            ->where('filas.0.rpo', 24));
});
