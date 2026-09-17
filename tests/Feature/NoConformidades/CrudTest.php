<?php

declare(strict_types=1);

use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\PrecargarChecklist;
use App\Domain\Auditoria\RegistrarHallazgo;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El registro de no conformidades por la interfaz
|--------------------------------------------------------------------------
|
| Lo que más se prueba aquí es la costura con el módulo de auditorías —abrir el
| tratamiento desde un hallazgo, con lo que ya se sabe puesto— y el reparto de
| permisos, que tiene un verbo más que los demás módulos: quien ejecuta el
| tratamiento no firma que funcionó.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    /*
     * La organización se pasa a mano porque uno de los tests monta el hallazgo
     * **dentro de otra**: `Sistema::factory()->de()` fija la columna, y con la
     * del contexto equivocado la política de RLS rechaza la inserción con un
     * error de privilegios que no menciona la palabra «organización».
     */
    $this->hallazgo = function (TipoHallazgo $tipo = TipoHallazgo::NcMayor, ?Organizacion $organizacion = null) {
        $organizacion ??= $this->organizacion;

        $marco = Marco::factory()->create();
        $sistema = Sistema::factory()->de($organizacion)->conMarco($marco)->create();

        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => 'op.acc.1',
            'tipo' => TipoRequisito::Medida->value,
            'orden' => 1,
        ]);
        Implantacion::factory()->for($sistema)->create(['requisito_id' => $requisito->id]);

        $auditoria = Auditoria::factory()->paraSistema($sistema->id)->create();
        app(PrecargarChecklist::class)($auditoria);

        return app(RegistrarHallazgo::class)->registrar(
            $auditoria,
            $tipo,
            'No consta la autorización de las altas de marzo.',
            $auditoria->puntos()->firstOrFail(),
        );
    };

    /** @param array<string, mixed> $campos */
    $this->datos = fn (array $campos = []): array => [
        'codigo' => 'NC-2026-01',
        'origen' => OrigenNoConformidad::Propia->value,
        'descripcion' => 'El procedimiento de altas no deja constancia de la autorización.',
        'fecha_deteccion' => Carbon::today()->toDateString(),
        ...$campos,
    ];
});

it('lista las no conformidades con sus indicadores', function (): void {
    NoConformidad::factory()->count(3)->create();

    $this->actingAs($this->usuario)
        ->get('/no-conformidades')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('no-conformidades/Index')
            ->has('filas', 3)
            ->where('total', 3)
            ->has('alertas')
            ->has('pendientes')
        );
});

it('registra una no conformidad suelta', function (): void {
    $this->actingAs($this->usuario)
        ->post('/no-conformidades', ($this->datos)())
        ->assertRedirect();

    $nc = NoConformidad::query()->firstOrFail();

    expect($nc->codigo)->toBe('NC-2026-01')
        ->and($nc->estado)->toBe(EstadoNoConformidad::Abierta)
        ->and($nc->hallazgo_id)->toBeNull()
        ->and($nc->transiciones()->count())->toBe(1);
});

/*
 * El camino que deja la no conformidad trazable: desde el hallazgo, con la
 * descripción, el origen y la fecha de detección ya puestos. Pedir que se
 * reescriban a mano es cómo se acaba con dos versiones del mismo hecho.
 */
it('abre el formulario desde un hallazgo con lo que ya se sabe puesto', function (): void {
    $hallazgo = ($this->hallazgo)();

    $this->actingAs($this->usuario)
        ->get("/no-conformidades/crear?hallazgo={$hallazgo->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('no-conformidades/Formulario')
            ->where('hallazgo.id', $hallazgo->id)
            ->where('sugerencia.origen', OrigenNoConformidad::Auditoria->value)
            ->where('sugerencia.descripcion', $hallazgo->descripcion)
            ->where('sugerencia.codigo', 'NC-'.Carbon::today()->year.'-01')
        );
});

/*
 * Un hallazgo se trata una vez, y lo impone un índice único. Sin esta puerta el
 * formulario se abriría y el alta reventaría con un error de clave duplicada al
 * final del trabajo.
 */
it('si el hallazgo ya tiene tratamiento, lleva al que existe en vez de abrir otro', function (): void {
    $hallazgo = ($this->hallazgo)();

    $this->actingAs($this->usuario)
        ->post('/no-conformidades', ($this->datos)([
            'origen' => OrigenNoConformidad::Auditoria->value,
            'hallazgo_id' => $hallazgo->id,
        ]))
        ->assertRedirect();

    $nc = NoConformidad::query()->firstOrFail();

    $this->actingAs($this->usuario)
        ->get("/no-conformidades/crear?hallazgo={$hallazgo->id}")
        ->assertRedirect("/no-conformidades/{$nc->id}");
});

it('un origen cuyo módulo no existe se rechaza con un mensaje que lo dice', function (): void {
    $this->actingAs($this->usuario)
        ->post('/no-conformidades', ($this->datos)(['origen' => OrigenNoConformidad::Incidente->value]))
        ->assertSessionHasErrors('origen');

    expect(NoConformidad::query()->count())->toBe(0);
});

it('un hallazgo con un origen que no es auditoría se rechaza antes de llegar al CHECK', function (): void {
    $hallazgo = ($this->hallazgo)();

    $this->actingAs($this->usuario)
        ->post('/no-conformidades', ($this->datos)([
            'origen' => OrigenNoConformidad::Propia->value,
            'hallazgo_id' => $hallazgo->id,
        ]))
        ->assertSessionHasErrors('origen');
});

it('la edición no mueve el hallazgo de sitio', function (): void {
    $hallazgo = ($this->hallazgo)();
    $otro = ($this->hallazgo)();

    $this->actingAs($this->usuario)->post('/no-conformidades', ($this->datos)([
        'origen' => OrigenNoConformidad::Auditoria->value,
        'hallazgo_id' => $hallazgo->id,
    ]));

    $nc = NoConformidad::query()->firstOrFail();

    $this->actingAs($this->usuario)
        ->put("/no-conformidades/{$nc->id}", ($this->datos)([
            'origen' => OrigenNoConformidad::Auditoria->value,
            'hallazgo_id' => $otro->id,
            'descripcion' => 'Corregida la redacción.',
        ]))
        ->assertRedirect();

    expect($nc->fresh()?->hallazgo_id)->toBe($hallazgo->id);
});

it('anular desde la ficha sin motivo no pasa de la validación', function (): void {
    $nc = NoConformidad::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/no-conformidades/{$nc->id}/estado", ['estado' => EstadoNoConformidad::Anulada->value])
        ->assertSessionHasErrors('nota');

    expect($nc->fresh()?->estado)->toBe(EstadoNoConformidad::Abierta);
});

/*
 * El quinto verbo de la familia de supervisión, junto a `sistemas.valorar`,
 * `riesgos.aceptar` y `documentos.aprobar`. El técnico trata la no conformidad
 * entera y **no firma que funcionó**: es la cláusula 10.2 e).
 */
it('el técnico trata la no conformidad y no verifica su eficacia', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $nc = NoConformidad::factory()->enEstado(EstadoNoConformidad::Cerrada)->create();

    $this->actingAs($tecnico)
        ->post("/no-conformidades/{$nc->id}/estado", [
            'estado' => EstadoNoConformidad::EnTratamiento->value,
            'nota' => 'Faltaba media cosa.',
        ])
        ->assertRedirect();

    $nc->refresh();
    expect($nc->estado)->toBe(EstadoNoConformidad::EnTratamiento);

    // Y de vuelta a cerrada para poder intentar verificar.
    $this->actingAs($tecnico)
        ->post("/no-conformidades/{$nc->id}/estado", ['estado' => EstadoNoConformidad::Cerrada->value]);

    $this->actingAs($tecnico)
        ->post("/no-conformidades/{$nc->id}/estado", [
            'estado' => EstadoNoConformidad::Verificada->value,
            'nota' => 'Yo mismo lo he mirado.',
        ])
        ->assertForbidden();

    expect($nc->fresh()?->estado)->toBe(EstadoNoConformidad::Cerrada);
});

it('el auditor mira el registro y no lo toca', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $nc = NoConformidad::factory()->create();

    $this->actingAs($auditor)->get('/no-conformidades')->assertOk();
    $this->actingAs($auditor)->get("/no-conformidades/{$nc->id}")->assertOk();

    $this->actingAs($auditor)
        ->post('/no-conformidades', ($this->datos)())
        ->assertForbidden();
});

/*
 * El aislamiento: 404 y nunca 403, porque decir «existe pero no es tuyo» ya sería
 * filtrar información.
 */
it('la no conformidad de otra organización no existe', function (): void {
    $otra = Organizacion::factory()->create();
    $ajena = comoOrganizacion($otra);
    $suya = NoConformidad::factory()->create();

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->get("/no-conformidades/{$suya->id}")
        ->assertNotFound();

    expect($ajena->id)->not->toBe($this->organizacion->id);
});

it('abrir el tratamiento de un hallazgo de otra organización no se puede', function (): void {
    $otra = Organizacion::factory()->create();
    comoOrganizacion($otra);
    $ajeno = ($this->hallazgo)(TipoHallazgo::NcMayor, $otra);

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->get("/no-conformidades/crear?hallazgo={$ajeno->id}")
        ->assertNotFound();

    /*
     * Y por POST ni siquiera llega al controlador: lo para el `exists` de la
     * regla. Conviene decir por qué, porque el docblock de `VincularTareaRequest`
     * dice que `exists` mira la tabla entera y aquí no lo hace — esa consulta va
     * por la conexión de la aplicación, que es `NOBYPASSRLS`, así que **la
     * tercera capa la filtra** antes que ninguna otra. En una tabla sin RLS
     * —`users`— seguiría mirando la tabla entera.
     */
    $this->actingAs($this->usuario)
        ->post('/no-conformidades', ($this->datos)([
            'origen' => OrigenNoConformidad::Auditoria->value,
            'hallazgo_id' => $ajeno->id,
        ]))
        ->assertSessionHasErrors('hallazgo_id');

    expect(NoConformidad::query()->count())->toBe(0);
});

it('la ficha enseña las transiciones que caben desde el estado actual', function (): void {
    $nc = NoConformidad::factory()->enEstado(EstadoNoConformidad::Cerrada)->create();

    $this->actingAs($this->usuario)
        ->get("/no-conformidades/{$nc->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('no-conformidades/Ficha')
            ->has('transiciones', 2)
            ->where('puedeVerificar', true)
        );
});
