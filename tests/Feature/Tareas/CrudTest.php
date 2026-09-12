<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Models\TareaTransicion;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El plan de acción, de punta a punta
|--------------------------------------------------------------------------
|
| Lo que más se prueba aquí es lo que separa una tarea de una fila de una hoja
| de cálculo: que el estado lleve histórico, que descartar exija un motivo y que
| una tarea pueda servir a requisitos de dos marcos a la vez.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->implantacion = function (string $codigoMarco, string $codigo): Implantacion {
        $marco = Marco::query()->firstWhere('codigo', $codigoMarco)
            ?? Marco::factory()->create(['codigo' => $codigoMarco]);
        $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
        $requisito = Requisito::factory()->conCodigo($codigo)->create(['marco_id' => $marco->id]);

        return Implantacion::factory()->create([
            'organizacion_id' => $this->organizacion->id,
            'sistema_id' => $sistema->id,
            'requisito_id' => $requisito->id,
        ]);
    };

    /** @param array<string, mixed> $campos */
    $this->datos = fn (array $campos = []): array => [
        'titulo' => 'Redactar la política de contraseñas',
        'origen' => OrigenTarea::Propia->value,
        'prioridad' => PrioridadTarea::Media->value,
        ...$campos,
    ];
});

it('lista las tareas', function (): void {
    Tarea::factory()->count(3)->create();

    $this->actingAs($this->usuario)
        ->get('/tareas')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('tareas/Index')
            ->has('filas', 3));
});

it('crea una tarea y le abre el histórico', function (): void {
    $this->actingAs($this->usuario)
        ->post('/tareas', ($this->datos)())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $tarea = Tarea::query()->sole();

    expect($tarea->estado)->toBe(EstadoTarea::Pendiente)
        ->and(TareaTransicion::query()->where('tarea_id', $tarea->id)->count())->toBe(1);
});

it('una tarea sin título no se crea', function (): void {
    $this->actingAs($this->usuario)
        ->post('/tareas', ($this->datos)(['titulo' => '']))
        ->assertSessionHasErrors('titulo');

    expect(Tarea::query()->count())->toBe(0);
});

/**
 * Los cuatro orígenes cuyo módulo no existe están declarados pero no se pueden
 * elegir: una tarea marcada como «hallazgo de auditoría» sin auditoría detrás no
 * es trazable, es una etiqueta.
 */
it('no deja usar un origen cuyo módulo todavía no existe', function (): void {
    $this->actingAs($this->usuario)
        ->post('/tareas', ($this->datos)(['origen' => OrigenTarea::Hallazgo->value]))
        ->assertSessionHasErrors('origen');

    expect(Tarea::query()->count())->toBe(0);
});

it('la tarea que nace de un requisito llega ya vinculada', function (): void {
    $implantacion = ($this->implantacion)('ISO-SINTETICO', 'A.5.1');

    $this->actingAs($this->usuario)
        ->post('/tareas', ($this->datos)([
            'origen' => OrigenTarea::BrechaImplantacion->value,
            'implantaciones' => [$implantacion->id],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Tarea::query()->sole()->implantaciones()->count())->toBe(1);
});

it('el formulario de alta puede venir de la ficha de un requisito', function (): void {
    $implantacion = ($this->implantacion)('ISO-SINTETICO', 'A.5.1');

    $this->actingAs($this->usuario)
        ->get("/tareas/crear?implantacion={$implantacion->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('tareas/Formulario')
            ->where('desdeImplantacion.codigo', 'A.5.1'));
});

it('la ficha enseña los vínculos y el histórico', function (): void {
    $tarea = Tarea::factory()->create();
    $implantacion = ($this->implantacion)('ENS-SINTETICO', 'op.acc.2');
    $tarea->implantaciones()->attach($implantacion->id, [
        'organizacion_id' => $this->organizacion->id,
    ]);

    $this->actingAs($this->usuario)
        ->get("/tareas/{$tarea->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('tareas/Ficha')
            ->has('vinculos', 1)
            ->where('vinculos.0.codigo', 'op.acc.2'));
});

it('mueve el estado desde la ficha y lo deja en el histórico', function (): void {
    $tarea = Tarea::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/tareas/{$tarea->id}/estado", ['estado' => EstadoTarea::EnCurso->value, 'nota' => 'La cojo yo.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tarea->fresh()->estado)->toBe(EstadoTarea::EnCurso)
        ->and(TareaTransicion::query()->where('tarea_id', $tarea->id)->sole()->nota)->toBe('La cojo yo.');
});

it('descartar sin motivo lo rechaza el formulario', function (): void {
    $tarea = Tarea::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/tareas/{$tarea->id}/estado", ['estado' => EstadoTarea::Descartada->value])
        ->assertSessionHasErrors('nota');

    expect($tarea->fresh()->estado)->toBe(EstadoTarea::Pendiente);
});

it('una transición imposible vuelve con el error y no rompe', function (): void {
    $tarea = Tarea::factory()->enEstado(EstadoTarea::Hecha)->create();

    $this->actingAs($this->usuario)
        ->post("/tareas/{$tarea->id}/estado", ['estado' => EstadoTarea::Descartada->value, 'nota' => 'ya no toca'])
        ->assertSessionHasErrors('estado');

    expect($tarea->fresh()->estado)->toBe(EstadoTarea::Hecha);
});

it('la acción masiva salta las que no admiten el cambio, sin parar la tanda', function (): void {
    $abiertas = Tarea::factory()->count(2)->create();
    $hecha = Tarea::factory()->enEstado(EstadoTarea::Hecha)->create();

    $this->actingAs($this->usuario)
        ->post('/tareas/estado', [
            'tareas' => [...$abiertas->pluck('id')->all(), $hecha->id],
            'estado' => EstadoTarea::EnCurso->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Tarea::query()->where('estado', EstadoTarea::EnCurso->value)->count())->toBe(3);
});

/**
 * Descartar a lo bruto no existe: un motivo escrito una vez para cincuenta
 * tareas no es un motivo.
 */
it('la acción masiva no descarta', function (): void {
    $tarea = Tarea::factory()->create();

    $this->actingAs($this->usuario)
        ->post('/tareas/estado', [
            'tareas' => [$tarea->id],
            'estado' => EstadoTarea::Descartada->value,
            'nota' => 'porque sí',
        ])
        ->assertSessionHasErrors('estado');

    expect($tarea->fresh()->estado)->toBe(EstadoTarea::Pendiente);
});

it('vincula y desvincula desde la ficha', function (): void {
    $tarea = Tarea::factory()->create();
    $implantacion = ($this->implantacion)('ISO-SINTETICO', 'A.5.15');

    $this->actingAs($this->usuario)
        ->post("/tareas/{$tarea->id}/implantaciones", ['implantacion_id' => $implantacion->id])
        ->assertRedirect();

    expect($tarea->implantaciones()->count())->toBe(1);

    $this->actingAs($this->usuario)
        ->delete("/tareas/{$tarea->id}/implantaciones/{$implantacion->id}")
        ->assertRedirect();

    expect($tarea->implantaciones()->count())->toBe(0);
});

it('el estado no se cambia por el formulario de edición', function (): void {
    $tarea = Tarea::factory()->create();

    $this->actingAs($this->usuario)
        ->put("/tareas/{$tarea->id}", ($this->datos)(['estado' => EstadoTarea::Hecha->value]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // Ni se aplica ni deja histórico: para eso está su ruta.
    expect($tarea->fresh()->estado)->toBe(EstadoTarea::Pendiente)
        ->and(TareaTransicion::query()->where('tarea_id', $tarea->id)->count())->toBe(0);
});

it('el auditor lee el plan y no lo toca', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $tarea = Tarea::factory()->create();

    $this->actingAs($auditor)->get('/tareas')->assertOk();
    $this->actingAs($auditor)->get("/tareas/{$tarea->id}")->assertOk();
    $this->actingAs($auditor)->get('/tareas/crear')->assertForbidden();
    $this->actingAs($auditor)->post('/tareas', ($this->datos)())->assertForbidden();
});

it('el técnico gestiona el plan entero', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnico)->get('/tareas')->assertOk();
    $this->actingAs($tecnico)
        ->post('/tareas', ($this->datos)())
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

/**
 * Prioridad 2 de cobertura: que ninguna consulta cruce la frontera de
 * organización. Responde 404 y no 403 — decir «existe pero no es tuyo» ya sería
 * filtrar.
 */
it('no se ve la tarea de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    $tareaAjena = Tarea::factory()->create();

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)->get("/tareas/{$tareaAjena->id}")->assertNotFound();
    $this->actingAs($this->usuario)->put("/tareas/{$tareaAjena->id}", ($this->datos)())->assertNotFound();
    $this->actingAs($this->usuario)->delete("/tareas/{$tareaAjena->id}")->assertNotFound();
    $this->actingAs($this->usuario)
        ->post("/tareas/{$tareaAjena->id}/estado", ['estado' => EstadoTarea::EnCurso->value])
        ->assertNotFound();
});

it('la tabla no cuenta las tareas de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    Tarea::factory()->count(4)->create();

    comoOrganizacion($this->organizacion);
    Tarea::factory()->count(2)->create();

    $this->actingAs($this->usuario)
        ->get('/tareas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));
});

it('el filtro de vencidas cuenta lo mismo que el scope', function (): void {
    Tarea::factory()->vencida()->count(2)->create();
    Tarea::factory()->paraElDia(Carbon::today()->addMonth())->create();
    Tarea::factory()->enEstado(EstadoTarea::Hecha)->paraElDia(Carbon::today()->subMonth())->create();

    $this->actingAs($this->usuario)
        ->get('/tareas?filter[vencidas]=1')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));

    expect(Tarea::query()->vencidas()->count())->toBe(2);
});

/**
 * Cada destino viaja con su tono y su icono para que el botón se pinte como el
 * badge al que lleva. Sin esto, los cuatro botones vuelven a ser cuatro
 * rectángulos grises que hay que leer uno a uno.
 */
it('las transiciones de la ficha llevan su tono y su icono', function (): void {
    $tarea = Tarea::factory()->create();

    $this->actingAs($this->usuario)
        ->get("/tareas/{$tarea->id}")
        ->assertInertia(function (AssertableInertia $pagina): void {
            $transiciones = $pagina->toArray()['props']['transiciones'];

            expect($transiciones)->not->toBeEmpty();

            foreach ($transiciones as $destino) {
                $estado = EstadoTarea::from($destino['valor']);

                expect($destino['tono'])->toBe($estado->tono())
                    ->and($destino['icono'])->toBe($estado->icono())
                    ->and($destino['etiqueta'])->toBe($estado->etiqueta());
            }
        });
});

/** El badge de la tabla también: color, icono y texto, los tres canales. */
it('el badge de estado de la tabla lleva icono', function (): void {
    Tarea::factory()->create();

    $this->actingAs($this->usuario)
        ->get('/tareas')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $fila = $pagina->toArray()['props']['filas'][0];

            expect($fila['estado']['icono'])->toBe(EstadoTarea::Pendiente->icono())
                ->and($fila['estado']['tono'])->toBe(EstadoTarea::Pendiente->tono());
        });
});
