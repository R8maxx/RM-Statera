<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\RevisionDireccion\AprobarRevision;
use App\Domain\RevisionDireccion\CambiarEstadoRevision;
use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use App\Domain\RevisionDireccion\Excepciones\RevisionNoAprobable;
use App\Domain\RevisionDireccion\Excepciones\TransicionDeRevisionNoPermitida;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| El ciclo de una revisión por la dirección (cláusula 9.3)
|--------------------------------------------------------------------------
|
| **Aprobar es lo único que importa de este módulo**: congela las siete entradas
| de la 9.3.2 y vuelve la fila inmutable. Todo lo que se prueba aquí gira
| alrededor de eso — que no se pueda firmar el acta de una reunión que no se ha
| celebrado, que lo congelado no se mueva después, y que la única puerta del
| quinto trigger de inmutabilidad sea la que se talló.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('no se firma el acta de una reunión que no se ha celebrado', function (): void {
    $revision = RevisionDireccion::factory()->create();

    expect($revision->estado)->toBe(EstadoRevision::Planificada);

    expect(fn () => app(AprobarRevision::class)($revision, $this->usuario))
        ->toThrow(RevisionNoAprobable::class, 'todavía no se ha celebrado');

    expect($revision->refresh()->instantanea)->toBeNull();
});

it('aprobar congela las siete entradas, estampa la firma y deja el acta lista', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();

    app(AprobarRevision::class)($revision, $this->usuario);

    $revision->refresh();

    expect($revision->estado)->toBe(EstadoRevision::Aprobada)
        ->and($revision->aprobada_por_id)->toBe($this->usuario->id)
        ->and($revision->aprobada_en)->not->toBeNull();

    // Las siete entradas de la 9.3.2, en la instantánea y no en una consulta.
    expect(array_keys($revision->instantanea ?? []))->toContain(
        'accionesPrevias',
        'contexto',
        'partesInteresadas',
        'desempeno',
        'riesgos',
        'mejoras',
    );
});

/*
 * El fallo más caro que este módulo podía tener, escrito como aserción: si el
 * acta consultara las tablas, el documento de la revisión de marzo enseñaría las
 * cifras de octubre bajo la fecha y la firma de marzo.
 */
it('lo que se congeló no cambia aunque el registro siga vivo', function (): void {
    NoConformidad::factory()->count(2)->create();

    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    $antes = $revision->refresh()->instantanea['desempeno']['noConformidades']['abiertas'] ?? null;

    expect($antes)->toBe(2);

    // Pasa la vida: se abren dos no conformidades más.
    NoConformidad::factory()->count(2)->create();

    expect(NoConformidad::query()->abiertas()->count())->toBe(4)
        ->and($revision->refresh()->instantanea['desempeno']['noConformidades']['abiertas'])->toBe(2);
});

it('un acta aprobada no se modifica: lo impide el trigger de PostgreSQL', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    /*
     * Por Query Builder, saltándose el dominio entero: la última capa es la base.
     *
     * **Y no se comprueba la fila después**: el `RAISE EXCEPTION` aborta la
     * transacción de `RefreshDatabase`, así que cualquier consulta posterior
     * revienta con «current transaction is aborted» y el test fallaría por el
     * motivo equivocado. Que el trigger salte ya significa que no se escribió
     * nada; es el mismo patrón de `Contexto/AnalisisTest`.
     */
    expect(fn () => DB::table('revisiones_direccion')
        ->where('id', $revision->id)
        ->update(['conclusiones' => 'Reescrito a mano.']))
        ->toThrow(QueryException::class);
});

it('de aprobada sólo se vuelve a en curso, nunca a planificada', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    expect(fn () => app(CambiarEstadoRevision::class)($revision, EstadoRevision::Planificada))
        ->toThrow(TransicionDeRevisionNoPermitida::class);

    // Reabrir sí: un acta firmada con un error tiene que poder corregirse.
    app(CambiarEstadoRevision::class)($revision, EstadoRevision::EnCurso);

    expect($revision->refresh()->estado)->toBe(EstadoRevision::EnCurso)
        // La firma se conserva hasta que la siguiente aprobación la sobreescribe.
        ->and($revision->aprobada_por_id)->toBe($this->usuario->id);
});

it('reabrir y volver a aprobar recoge las entradas otra vez', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    app(CambiarEstadoRevision::class)($revision, EstadoRevision::EnCurso);

    NoConformidad::factory()->create();

    app(AprobarRevision::class)($revision->refresh(), $this->usuario);

    expect($revision->refresh()->instantanea['desempeno']['noConformidades']['abiertas'])->toBe(1);
});

it('aprobar no pasa por la ruta de transición', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();

    $this->actingAs($this->usuario)
        ->post("/revision-direccion/{$revision->id}/estado", ['estado' => EstadoRevision::Aprobada->value])
        ->assertSessionHasErrors('estado');

    expect($revision->refresh()->estado)->toBe(EstadoRevision::EnCurso);
});

it('el acta aprobada no se edita desde el formulario', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    $this->actingAs($this->usuario)
        ->put("/revision-direccion/{$revision->id}", [
            'codigo' => $revision->codigo,
            'fecha' => $revision->fecha->toDateString(),
            'periodo_desde' => $revision->periodo_desde->toDateString(),
            'periodo_hasta' => $revision->periodo_hasta->toDateString(),
            'conclusiones' => 'Reescrito desde el formulario.',
        ])
        ->assertSessionHasErrors('codigo');

    expect($revision->refresh()->conclusiones)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| El octavo verbo de supervisión
|--------------------------------------------------------------------------
*/

it('un técnico prepara la revisión y no firma el acta', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $revision = RevisionDireccion::factory()->enCurso()->create();

    $this->actingAs($tecnico)
        ->post("/revision-direccion/{$revision->id}/aprobacion")
        ->assertForbidden();

    expect($revision->refresh()->estado)->toBe(EstadoRevision::EnCurso);

    // Lo que sí puede: moverla de estado y registrar decisiones.
    $this->actingAs($tecnico)
        ->post("/revision-direccion/{$revision->id}/estado", ['estado' => EstadoRevision::Planificada->value])
        ->assertRedirect();
});

it('el responsable de seguridad firma y la ficha pasa a enseñar lo congelado', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();

    $this->actingAs($this->usuario)
        ->post("/revision-direccion/{$revision->id}/aprobacion")
        ->assertRedirect();

    $this->actingAs($this->usuario)
        ->get("/revision-direccion/{$revision->id}")
        ->assertInertia(fn ($pagina) => $pagina
            ->component('revision-direccion/Ficha')
            ->where('congeladas', true)
            ->where('revision.estado', EstadoRevision::Aprobada->value));
});

it('un auditor lee las actas y no escribe en ellas', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $revision = RevisionDireccion::factory()->create();

    $this->actingAs($auditor)->get('/revision-direccion')->assertOk();
    $this->actingAs($auditor)->get("/revision-direccion/{$revision->id}")->assertOk();
    $this->actingAs($auditor)->get('/revision-direccion/crear')->assertForbidden();
    $this->actingAs($auditor)->post("/revision-direccion/{$revision->id}/aprobacion")->assertForbidden();
});

it('el periodo no puede terminar antes de empezar', function (): void {
    $this->actingAs($this->usuario)
        ->post('/revision-direccion', [
            'codigo' => 'RD-2026-01',
            'fecha' => Carbon::today()->toDateString(),
            'periodo_desde' => Carbon::today()->toDateString(),
            'periodo_hasta' => Carbon::today()->subMonth()->toDateString(),
        ])
        ->assertSessionHasErrors('periodo_hasta');
});
