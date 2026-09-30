<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\RevisionDireccion\AprobarRevision;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia;

/**
 * El acta de la revisión por la dirección (§ 4.18, 9.3): cómo nace y qué lo
 * impide.
 *
 * Hermano de `Auditorias/InformeTest` y por lo mismo: lo que se clava es el
 * vínculo con su revisión. Una por revisión, sólo con ella aprobada, nunca desde
 * el formulario de documentos, y con la base diciendo lo mismo que el dominio.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->aprobada = function (string $codigo = 'RD-2026-01'): RevisionDireccion {
        $revision = RevisionDireccion::factory()->enCurso()->create(['codigo' => $codigo]);
        app(AprobarRevision::class)($revision, $this->usuario);

        return $revision->refresh();
    };
});

it('prepara el acta de una revisión aprobada, una sola vez', function (): void {
    $revision = ($this->aprobada)();

    $this->actingAs($this->usuario)->post("/revision-direccion/{$revision->id}/acta")->assertRedirect();
    $this->actingAs($this->usuario)->post("/revision-direccion/{$revision->id}/acta")->assertRedirect();

    $documento = Documento::query()->sole();

    expect($documento->tipo)->toBe(TipoDocumento::ActaRevision)
        ->and($documento->revision_direccion_id)->toBe($revision->id)
        ->and($documento->sistema_id)->toBeNull()
        ->and($documento->codigo)->toBe('ACT-RD-2026-01')
        ->and($documento->periodicidad_revision_meses)->toBe(12);
});

it('cada revisión tiene su acta', function (): void {
    $vieja = ($this->aprobada)('RD-2025-01');
    $nueva = ($this->aprobada)('RD-2026-01');

    $this->actingAs($this->usuario)->post("/revision-direccion/{$vieja->id}/acta");
    $this->actingAs($this->usuario)->post("/revision-direccion/{$nueva->id}/acta");

    expect(Documento::query()->orderBy('codigo')->pluck('revision_direccion_id')->all())
        ->toBe([$vieja->id, $nueva->id]);
});

it('la ficha ofrece el acta y, una vez preparada, la enlaza', function (): void {
    $revision = ($this->aprobada)();

    $this->actingAs($this->usuario)
        ->get("/revision-direccion/{$revision->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('acta', null)
            ->where('puedePrepararActa', true));

    $this->actingAs($this->usuario)->post("/revision-direccion/{$revision->id}/acta");
    $documento = Documento::query()->sole();

    $this->actingAs($this->usuario)
        ->get("/revision-direccion/{$revision->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('acta.id', $documento->id)
            ->where('acta.codigo', $documento->codigo));
});

it('no prepara el acta de una revisión sin aprobar', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();

    $this->actingAs($this->usuario)
        ->from("/revision-direccion/{$revision->id}")
        ->post("/revision-direccion/{$revision->id}/acta")
        ->assertRedirect("/revision-direccion/{$revision->id}")
        ->assertSessionHasErrors('acta');

    expect(Documento::query()->count())->toBe(0);
});

it('el Auditor no prepara actas', function (): void {
    $revision = ($this->aprobada)();

    $this->actingAs(usuarioCon(Rol::Auditor))->post("/revision-direccion/{$revision->id}/acta")->assertForbidden();

    expect(Documento::query()->count())->toBe(0);
});

it('no prepara el acta de la revisión de otra organización', function (): void {
    comoOrganizacion();
    $ajena = RevisionDireccion::factory()->enCurso()->create();

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)->post("/revision-direccion/{$ajena->id}/acta")->assertNotFound();

    expect(Documento::query()->count())->toBe(0);
});

it('la base rechaza una segunda acta de la misma revisión', function (): void {
    $revision = ($this->aprobada)();
    Documento::factory()->actaRevision($revision)->create();

    expect(fn () => Documento::factory()->actaRevision($revision)->create(['codigo' => 'ACT-OTRA']))
        ->toThrow(QueryException::class, 'documentos_revision_direccion_unica');
});

it('la base rechaza un acta sin revisión', function (): void {
    expect(fn () => Documento::factory()->actaRevision()->create(['revision_direccion_id' => null]))
        ->toThrow(QueryException::class, 'documentos_revision_direccion_check');
});

// Aparte del anterior: la primera violación aborta la transacción del test, y la
// segunda sentencia ya no llegaría a comprobar el `CHECK`.
it('la base rechaza una revisión colgada de otro tipo de documento', function (): void {
    $revision = ($this->aprobada)();

    expect(fn () => Documento::factory()->informeEstado()->create(['revision_direccion_id' => $revision->id]))
        ->toThrow(QueryException::class, 'documentos_revision_direccion_check');
});

it('el formulario de documentos no crea actas', function (): void {
    $this->actingAs($this->usuario)
        ->post('/documentos', [
            'tipo' => TipoDocumento::ActaRevision->value,
            'codigo' => 'ACT-A-MANO',
            'titulo' => 'Acta a mano',
            'clasificacion' => ClasificacionDocumental::UsoInterno->value,
        ])
        ->assertSessionHasErrors('tipo');

    $this->actingAs($this->usuario)
        ->get('/documentos/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('tipos', fn ($tipos) => collect($tipos)->doesntContain('valor', TipoDocumento::ActaRevision->value)));

    expect(Documento::query()->count())->toBe(0);
});

it('un acta no cambia de tipo pero sí se le corrige el título', function (): void {
    $documento = Documento::factory()->actaRevision(($this->aprobada)())->create();

    $datos = [
        'tipo' => TipoDocumento::ActaRevision->value,
        'codigo' => $documento->codigo,
        'titulo' => 'Título corregido',
        'clasificacion' => ClasificacionDocumental::UsoInterno->value,
    ];

    $this->actingAs($this->usuario)
        ->put("/documentos/{$documento->id}", [...$datos, 'tipo' => TipoDocumento::InformeEstado->value])
        ->assertSessionHasErrors('tipo');

    $this->actingAs($this->usuario)
        ->put("/documentos/{$documento->id}", $datos)
        ->assertSessionHasNoErrors();

    expect($documento->fresh()->titulo)->toBe('Título corregido');
});

it('no se elimina una revisión con acta', function (): void {
    $revision = ($this->aprobada)();
    Documento::factory()->actaRevision($revision)->create();

    $this->actingAs($this->usuario)
        ->from("/revision-direccion/{$revision->id}")
        ->delete("/revision-direccion/{$revision->id}")
        ->assertSessionHasErrors('revision');

    expect($revision->fresh())->not->toBeNull();
});
