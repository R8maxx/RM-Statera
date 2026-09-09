<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\TipoCorrespondencia;
use App\Domain\Catalogo\Models\Mapeo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Enums\NivelMadurez;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Implantacion\Models\ImplantacionTransicion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La ficha de una implantación
|--------------------------------------------------------------------------
|
| Las tres preguntas de una auditoría en una pantalla: qué aplica, cómo se
| cumple y desde cuándo. La tercera —dónde está la prueba— entra con el módulo
| de evidencias.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->usuario->update(['name' => 'Responsable de seguridad']);

    $this->marco = Marco::factory()->create(['codigo' => 'ENS-SINTETICO', 'nombre' => 'ENS sintético']);
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)
        ->create(['codigo' => 'SIS-01']);

    $this->requisito = Requisito::factory()->conCodigo('op.acc.5')
        ->create(['marco_id' => $this->marco->id, 'titulo' => 'Mecanismo de autenticación']);

    $this->implantacion = Implantacion::factory()->create([
        'organizacion_id' => $this->organizacion->id,
        'sistema_id' => $this->sistema->id,
        'requisito_id' => $this->requisito->id,
    ]);

    /** @param array<string, mixed> $campos */
    $this->gestion = fn (array $campos = []): array => [
        'aplica' => true,
        'justificacion' => null,
        'responsable_id' => null,
        'fecha_objetivo' => null,
        'nivel_madurez' => null,
        'notas' => null,
        ...$campos,
    ];
});

it('sirve la ficha con el requisito, la exigencia y su origen', function (): void {
    $this->actingAs($this->usuario)
        ->get("/implantaciones/{$this->implantacion->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('implantaciones/Ficha')
            ->where('requisito.codigo', 'op.acc.5')
            ->where('requisito.titulo', 'Mecanismo de autenticación')
            ->where('requisito.marco', 'ENS sintético')
            ->where('sistema.codigo', 'SIS-01')
            ->where('exigencia.origen', 'Categoría del sistema')
            // Deriva del motor: no se excluye a mano.
            ->where('exigencia.excluibleAMano', false)
            ->has('transicionesPermitidas', 3)
        );
});

it('guarda responsable, fecha, madurez y notas', function (): void {
    $this->actingAs($this->usuario)
        ->put("/implantaciones/{$this->implantacion->id}", ($this->gestion)([
            'responsable_id' => $this->usuario->id,
            'fecha_objetivo' => '2026-12-31',
            'nivel_madurez' => NivelMadurez::L3->value,
            'notas' => 'MFA obligatorio en el IdP corporativo.',
        ]))
        ->assertSessionHasNoErrors();

    $implantacion = $this->implantacion->fresh();

    expect($implantacion->responsable_id)->toBe($this->usuario->id)
        ->and($implantacion->fecha_objetivo?->toDateString())->toBe('2026-12-31')
        ->and($implantacion->nivel_madurez)->toBe(NivelMadurez::L3)
        ->and($implantacion->notas)->toBe('MFA obligatorio en el IdP corporativo.');
});

it('no acepta como responsable a alguien de otra organización', function (): void {
    $otra = Organizacion::factory()->create();
    $ajeno = User::factory()->create(['organizacion_id' => $otra->id]);

    $this->actingAs($this->usuario)
        ->put("/implantaciones/{$this->implantacion->id}", ($this->gestion)(['responsable_id' => $ajeno->id]))
        ->assertSessionHasErrors('responsable_id');

    expect($this->implantacion->fresh()->responsable_id)->toBeNull();
});

it('cambia de estado y lo deja en el histórico con su autor', function (): void {
    $this->actingAs($this->usuario)
        ->post("/implantaciones/{$this->implantacion->id}/estado", [
            'estado' => EstadoImplantacion::EnProgreso->value,
            'nota' => 'Piloto en el entorno de preproducción.',
        ])
        ->assertSessionHasNoErrors();

    expect($this->implantacion->fresh()->estado)->toBe(EstadoImplantacion::EnProgreso);

    $transicion = ImplantacionTransicion::query()
        ->where('implantacion_id', $this->implantacion->id)
        ->latest('id')
        ->firstOrFail();

    expect($transicion->estado_anterior)->toBe(EstadoImplantacion::NoIniciado)
        ->and($transicion->estado_nuevo)->toBe(EstadoImplantacion::EnProgreso)
        ->and($transicion->usuario_id)->toBe($this->usuario->id)
        ->and($transicion->nota)->toBe('Piloto en el entorno de preproducción.');
});

it('rechaza una transición que la máquina de estados no permite', function (): void {
    // De `no_iniciado` no se pasa a `no_aplica`: eso lo deriva el motor.
    $this->actingAs($this->usuario)
        ->post("/implantaciones/{$this->implantacion->id}/estado", [
            'estado' => EstadoImplantacion::NoAplica->value,
        ])
        ->assertSessionHasErrors('estado');

    expect($this->implantacion->fresh()->estado)->toBe(EstadoImplantacion::NoIniciado);
});

it('no deja excluir a mano una medida cuya exigencia deriva el motor', function (): void {
    $this->actingAs($this->usuario)
        ->put("/implantaciones/{$this->implantacion->id}", ($this->gestion)([
            'aplica' => false,
            'justificacion' => 'No nos aplica.',
        ]))
        ->assertSessionHasErrors('aplica');

    expect($this->implantacion->fresh()->aplica)->toBeTrue();
});

it('excluye con justificación un requisito del propio marco, que es la SoA de ISO', function (): void {
    $this->implantacion->update([
        'origen_exigencia' => OrigenExigencia::Catalogo->value,
        'estado' => EstadoImplantacion::Implantado->value,
    ]);

    $this->actingAs($this->usuario)
        ->put("/implantaciones/{$this->implantacion->id}", ($this->gestion)([
            'aplica' => false,
            'justificacion' => 'La organización no desarrolla software a medida.',
        ]))
        ->assertSessionHasNoErrors();

    $implantacion = $this->implantacion->fresh();

    // `aplica` y `estado` se mueven juntos: lo exige una restricción de la base.
    expect($implantacion->aplica)->toBeFalse()
        ->and($implantacion->estado)->toBe(EstadoImplantacion::NoAplica)
        ->and($implantacion->justificacion)->toBe('La organización no desarrolla software a medida.');

    // Y la exclusión queda en el histórico, con su motivo.
    $transicion = ImplantacionTransicion::query()
        ->where('implantacion_id', $implantacion->id)
        ->latest('id')
        ->firstOrFail();

    expect($transicion->estado_nuevo)->toBe(EstadoImplantacion::NoAplica)
        ->and($transicion->nota)->toBe('La organización no desarrolla software a medida.');
});

it('rechaza una exclusión sin justificación', function (): void {
    $this->implantacion->update(['origen_exigencia' => OrigenExigencia::Catalogo->value]);

    $this->actingAs($this->usuario)
        ->put("/implantaciones/{$this->implantacion->id}", ($this->gestion)(['aplica' => false]))
        ->assertSessionHasErrors('justificacion');

    expect($this->implantacion->fresh()->aplica)->toBeTrue();
});

it('reincorporar una excluida recupera el estado que tenía', function (): void {
    $this->implantacion->update([
        'origen_exigencia' => OrigenExigencia::Catalogo->value,
        'estado' => EstadoImplantacion::Implantado->value,
    ]);

    $this->actingAs($this->usuario)
        ->put("/implantaciones/{$this->implantacion->id}", ($this->gestion)([
            'aplica' => false,
            'justificacion' => 'Fuera del alcance declarado.',
        ]));

    $this->actingAs($this->usuario)
        ->put("/implantaciones/{$this->implantacion->id}", ($this->gestion)(['aplica' => true]))
        ->assertSessionHasNoErrors();

    $implantacion = $this->implantacion->fresh();

    expect($implantacion->aplica)->toBeTrue()
        ->and($implantacion->justificacion)->toBeNull()
        // Para esto se guarda el histórico: no se vuelve a empezar de cero.
        ->and($implantacion->estado)->toBe(EstadoImplantacion::Implantado);
});

it('enseña el mapeo cruzado con lo que la organización tiene hecho en el otro marco', function (): void {
    $iso = Marco::factory()->create(['codigo' => 'ISO-27001-2022', 'nombre' => 'ISO/IEC 27001:2022']);
    $control = Requisito::factory()->conCodigo('A.8.5')
        ->create(['marco_id' => $iso->id, 'titulo' => 'Autenticación segura']);

    Mapeo::query()->create([
        'requisito_origen_id' => $control->id,
        'requisito_destino_id' => $this->requisito->id,
        'tipo_correspondencia' => TipoCorrespondencia::Equivalente->value,
        'nota' => 'Misma exigencia de autenticación.',
    ]);

    $sgsi = Sistema::factory()->de($this->organizacion)->conMarco($iso)->create(['codigo' => 'SGSI-01']);

    Implantacion::factory()->enEstado(EstadoImplantacion::Implantado)->create([
        'organizacion_id' => $this->organizacion->id,
        'sistema_id' => $sgsi->id,
        'requisito_id' => $control->id,
    ]);

    // El mapeo se declara de ISO hacia el ENS, y desde el ENS tiene que verse
    // igual: la correspondencia no es dirigida aunque la fila lo sea.
    $this->actingAs($this->usuario)
        ->get("/implantaciones/{$this->implantacion->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('correspondencias', 1)
            ->where('correspondencias.0.codigo', 'A.8.5')
            ->where('correspondencias.0.marco', 'ISO-27001-2022')
            ->where('correspondencias.0.cubreDelTodo', true)
            ->where('correspondencias.0.implantaciones.0.sistema', 'SGSI-01')
            ->where('correspondencias.0.implantaciones.0.estado', 'implantado')
        );
});

it('el mapeo cruzado no enseña implantaciones de otra organización', function (): void {
    $iso = Marco::factory()->create(['codigo' => 'ISO-27001-2022']);
    $control = Requisito::factory()->conCodigo('A.8.5')->create(['marco_id' => $iso->id]);

    Mapeo::query()->create([
        'requisito_origen_id' => $control->id,
        'requisito_destino_id' => $this->requisito->id,
        'tipo_correspondencia' => TipoCorrespondencia::Equivalente->value,
    ]);

    $otra = Organizacion::factory()->create();
    comoOrganizacion($otra);

    $ajeno = Sistema::factory()->de($otra)->conMarco($iso)->create();
    Implantacion::factory()->create([
        'organizacion_id' => $otra->id,
        'sistema_id' => $ajeno->id,
        'requisito_id' => $control->id,
    ]);

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->get("/implantaciones/{$this->implantacion->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('correspondencias', 1)
            // El requisito del catálogo sí se ve —es global—; lo que la otra
            // organización tenga hecho en él, no.
            ->has('correspondencias.0.implantaciones', 0)
        );
});

it('no sirve la ficha de otra organización', function (): void {
    $otra = Organizacion::factory()->create();
    comoOrganizacion($otra);

    $ajeno = Sistema::factory()->de($otra)->conMarco($this->marco)->create();
    $implantacionAjena = Implantacion::factory()->create([
        'organizacion_id' => $otra->id,
        'sistema_id' => $ajeno->id,
        'requisito_id' => $this->requisito->id,
    ]);

    comoOrganizacion($this->organizacion);

    // 404 y no 403: «existe pero no es tuyo» ya sería filtrar información.
    $this->actingAs($this->usuario)
        ->get("/implantaciones/{$implantacionAjena->id}")
        ->assertNotFound();

    $this->actingAs($this->usuario)
        ->put("/implantaciones/{$implantacionAjena->id}", ($this->gestion)(['notas' => 'Intrusión']))
        ->assertNotFound();

    $this->actingAs($this->usuario)
        ->post("/implantaciones/{$implantacionAjena->id}/estado", [
            'estado' => EstadoImplantacion::Implantado->value,
        ])
        ->assertNotFound();
});

it('exige sesión iniciada', function (): void {
    $this->get("/implantaciones/{$this->implantacion->id}")->assertRedirect('/login');
    $this->put("/implantaciones/{$this->implantacion->id}", [])->assertRedirect('/login');
});
