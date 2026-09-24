<?php

declare(strict_types=1);

use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia;

/**
 * El informe de auditoría interna (§ 4.18, 9.2.2): cómo nace y qué lo impide.
 *
 * Lo que se clava es el vínculo con su auditoría, que es lo único que este tipo
 * trae y ningún otro tenía: uno por auditoría, sólo con ella cerrada, nunca desde
 * el formulario de documentos, y con la base diciendo lo mismo que el dominio.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->sistema = sistemaEns($this->organizacion);
});

it('prepara el informe de una auditoría cerrada, una sola vez', function (): void {
    $auditoria = autoevaluacion($this->sistema);

    $this->actingAs($this->usuario)->post("/auditorias/{$auditoria->id}/informe")->assertRedirect();
    $this->actingAs($this->usuario)->post("/auditorias/{$auditoria->id}/informe")->assertRedirect();

    $documento = Documento::query()->sole();

    expect($documento->tipo)->toBe(TipoDocumento::InformeAuditoria)
        ->and($documento->auditoria_id)->toBe($auditoria->id)
        ->and($documento->sistema_id)->toBe($auditoria->sistema_id)
        ->and($documento->codigo)->toBe('INF-'.$auditoria->codigo)
        ->and($documento->clasificacion)->toBe(ClasificacionDocumental::UsoInterno);
});

it('la ficha ofrece el informe y, una vez preparado, lo enlaza', function (): void {
    $auditoria = autoevaluacion($this->sistema);

    $this->actingAs($this->usuario)
        ->get("/auditorias/{$auditoria->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('informe', null)
            ->where('admiteInforme', true)
            ->where('puedeGenerar', true));

    $this->actingAs($this->usuario)->post("/auditorias/{$auditoria->id}/informe");
    $documento = Documento::query()->sole();

    $this->actingAs($this->usuario)
        ->get("/auditorias/{$auditoria->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('informe.id', $documento->id)
            ->where('informe.codigo', $documento->codigo));
});

it('no prepara el informe de una auditoría abierta', function (): void {
    $auditoria = autoevaluacion($this->sistema, cerrar: false);

    $this->actingAs($this->usuario)
        ->from("/auditorias/{$auditoria->id}")
        ->post("/auditorias/{$auditoria->id}/informe")
        ->assertRedirect("/auditorias/{$auditoria->id}")
        ->assertSessionHasErrors('informe');

    expect(Documento::query()->count())->toBe(0);
});

it('no prepara el informe de una auditoría externa: lo emite la entidad', function (): void {
    $auditoria = Auditoria::factory()
        ->deTipo(TipoAuditoria::Externa)
        ->paraSistema($this->sistema->id)
        ->cerrada()
        ->create();

    $this->actingAs($this->usuario)
        ->post("/auditorias/{$auditoria->id}/informe")
        ->assertSessionHasErrors('informe');

    expect(Documento::query()->count())->toBe(0);
});

it('el Auditor no prepara informes', function (): void {
    $auditoria = autoevaluacion($this->sistema);
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)->post("/auditorias/{$auditoria->id}/informe")->assertForbidden();

    expect(Documento::query()->count())->toBe(0);
});

it('no prepara el informe de la auditoría de otra organización', function (): void {
    $otra = comoOrganizacion();
    $ajena = autoevaluacion(sistemaEns($otra));

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)->post("/auditorias/{$ajena->id}/informe")->assertNotFound();

    expect(Documento::query()->count())->toBe(0);
});

it('la base rechaza un segundo informe de la misma auditoría', function (): void {
    $auditoria = autoevaluacion($this->sistema);
    Documento::factory()->informeAuditoria($auditoria)->create();

    expect(fn () => Documento::factory()->informeAuditoria($auditoria)->create(['codigo' => 'INF-OTRO']))
        ->toThrow(QueryException::class, 'documentos_auditoria_unica');
});

it('la base rechaza un informe sin auditoría', function (): void {
    $auditoria = autoevaluacion($this->sistema);

    expect(fn () => Documento::factory()->informeAuditoria($auditoria)->create(['auditoria_id' => null]))
        ->toThrow(QueryException::class, 'documentos_auditoria_check');
});

// Aparte del anterior: la primera violación aborta la transacción del test, y la
// segunda sentencia ya no llegaría a comprobar el `CHECK`.
it('la base rechaza una auditoría colgada de otro tipo de documento', function (): void {
    $auditoria = autoevaluacion($this->sistema);

    expect(fn () => Documento::factory()->dda()->paraSistema($this->sistema->id)->create(['auditoria_id' => $auditoria->id]))
        ->toThrow(QueryException::class, 'documentos_auditoria_check');
});

it('los criterios, el método y el equipo quedan blindados al cerrar', function (): void {
    $auditoria = autoevaluacion($this->sistema);

    expect(fn () => $auditoria->forceFill(['criterios' => 'Otros criterios.'])->saveQuietly())
        ->toThrow(QueryException::class, 'Una auditoria cerrada no se modifica');
});

it('el formulario de documentos no crea informes de auditoría', function (): void {
    $this->actingAs($this->usuario)
        ->post('/documentos', [
            'tipo' => TipoDocumento::InformeAuditoria->value,
            'sistema_id' => $this->sistema->id,
            'codigo' => 'INF-A-MANO',
            'titulo' => 'Informe a mano',
            'clasificacion' => ClasificacionDocumental::UsoInterno->value,
        ])
        ->assertSessionHasErrors('tipo');

    $this->actingAs($this->usuario)
        ->get('/documentos/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('tipos', fn ($tipos) => collect($tipos)->doesntContain('valor', TipoDocumento::InformeAuditoria->value)));

    expect(Documento::query()->count())->toBe(0);
});

it('un informe no cambia de tipo ni de sistema desde el formulario', function (): void {
    $auditoria = autoevaluacion($this->sistema);
    $documento = Documento::factory()->informeAuditoria($auditoria)->create();
    $otroSistema = sistemaEns($this->organizacion);

    $datos = [
        'tipo' => TipoDocumento::InformeAuditoria->value,
        'sistema_id' => $this->sistema->id,
        'codigo' => $documento->codigo,
        'titulo' => 'Título corregido',
        'clasificacion' => ClasificacionDocumental::UsoInterno->value,
    ];

    $this->actingAs($this->usuario)
        ->put("/documentos/{$documento->id}", [...$datos, 'tipo' => TipoDocumento::DdaEns->value])
        ->assertSessionHasErrors('tipo');

    $this->actingAs($this->usuario)
        ->put("/documentos/{$documento->id}", [...$datos, 'sistema_id' => $otroSistema->id])
        ->assertSessionHasErrors('sistema_id');

    $this->actingAs($this->usuario)
        ->put("/documentos/{$documento->id}", $datos)
        ->assertSessionHasNoErrors();

    expect($documento->fresh()->titulo)->toBe('Título corregido');
});

it('no se elimina una auditoría con informe', function (): void {
    $auditoria = autoevaluacion($this->sistema);
    Documento::factory()->informeAuditoria($auditoria)->create();

    $this->actingAs($this->usuario)
        ->from("/auditorias/{$auditoria->id}")
        ->delete("/auditorias/{$auditoria->id}")
        ->assertSessionHasErrors('auditoria');

    expect($auditoria->fresh())->not->toBeNull();
});

it('borrar el sistema arrastra la auditoría y su informe sin fallar', function (): void {
    $auditoria = autoevaluacion($this->sistema);
    Documento::factory()->informeAuditoria($auditoria)->create();

    $this->sistema->delete();

    expect(Auditoria::query()->count())->toBe(0)
        ->and(Documento::query()->count())->toBe(0);
});
