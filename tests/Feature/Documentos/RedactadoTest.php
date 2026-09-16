<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Render\ClienteGotenberg;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/*
|--------------------------------------------------------------------------
| Los documentos redactados (§ 4.5)
|--------------------------------------------------------------------------
|
| La otra familia de documentos: política, norma y procedimiento. No salen de
| ninguna consulta —los escribe la organización— y son los que dan sentido al
| acuse de lectura, porque nadie acusa recibo de una Declaración de Aplicabilidad.
|
| Lo que más importa aquí es lo que NO tienen: sin sistema, sin marco que casar,
| sin tabla larga y sin cifras. Un hueco calculado que se colara saldría vacío en
| el PDF y sin ningún error.
|
*/

beforeEach(function (): void {
    Storage::fake('documentos');
    $this->gotenberg = new GotenbergFalso;
    app()->instance(ClienteGotenberg::class, $this->gotenberg);

    $this->organizacion = comoOrganizacion();
});

it('se crea sin sistema, que es lo normal en una política', function (): void {
    $this->actingAs(usuarioCon(Rol::ResponsableSeguridad))
        ->post('/documentos', [
            'tipo' => TipoDocumento::Politica->value,
            'codigo' => 'POL-SEG-01',
            'titulo' => 'Política de Seguridad de la Información',
            'clasificacion' => 'uso_interno',
            'periodicidad_revision_meses' => 12,
            'exige_acuse' => true,
        ])
        ->assertRedirect();

    $documento = Documento::query()->where('codigo', 'POL-SEG-01')->firstOrFail();

    expect($documento->sistema_id)->toBeNull()
        ->and($documento->tipo)->toBe(TipoDocumento::Politica)
        ->and($documento->exigeAcuse())->toBeTrue()
        ->and($documento->periodicidad_revision_meses)->toBe(12);
});

/**
 * Una declaración sin sistema sigue siendo imposible: el alcance y la categoría
 * salen de él. Lo que cambió es que el requisito depende del tipo, no que se haya
 * relajado.
 */
it('una declaración de aplicabilidad sigue exigiendo sistema', function (): void {
    $this->actingAs(usuarioCon(Rol::ResponsableSeguridad))
        ->post('/documentos', [
            'tipo' => TipoDocumento::SoaIso->value,
            'codigo' => 'SOA-SIN-SISTEMA',
            'titulo' => 'Declaración de Aplicabilidad',
            'clasificacion' => 'uso_interno',
        ])
        ->assertSessionHasErrors('sistema_id');
});

it('el PDF sale sin tabla larga y sin cifras', function (): void {
    $documento = Documento::factory()->politica()->create();

    $version = app(GenerarDocumento::class)->encolar($documento);
    app(GenerarDocumento::class)->ejecutar($version);

    $html = $this->gotenberg->html();

    expect($html)->toContain('Política de Seguridad de la Información')
        // Ni tabla de requisitos ni gráfica de resumen: no hay nada que contar.
        ->and($html)->not->toContain('<table')
        ->and($html)->not->toContain('Controles del Anexo A');

    expect($version->fresh()->total_requisitos)->toBe(0);
});

it('trae el texto de fábrica que el ENS espera de una política', function (): void {
    $documento = Documento::factory()->politica()->create();

    app(GenerarDocumento::class)->ejecutar(app(GenerarDocumento::class)->encolar($documento));

    // `org.1` pide que la política declare los objetivos, el compromiso de la
    // dirección y a quién obliga. Es lo que la § 4.5 llama «plantilla base».
    expect($this->gotenberg->html())
        ->toContain('compromiso de la dirección')
        ->toContain('obligado cumplimiento');
});

/**
 * De los once huecos narrativos le quedan cinco. Ofrecerle «cómo leer la tabla» a
 * un documento que no tiene tabla es ofrecerle explicar algo que no existe.
 */
it('sólo ofrece los huecos narrativos que son prosa de verdad', function (TipoDocumento $tipo): void {
    $secciones = array_map(
        static fn (SeccionNarrativa $s): string => $s->value,
        SeccionNarrativa::paraTipo($tipo),
    );

    expect($secciones)
        ->toContain('introduccion', 'objeto_y_alcance', 'conclusiones', 'aprobacion', 'limitaciones_propias')
        ->not->toContain('nota_tabla')
        ->not->toContain('nota_derivacion')
        ->not->toContain('nota_madurez')
        ->not->toContain('nota_exclusiones')
        ->not->toContain('nota_resumen');
})->with(fn () => array_values(array_filter(
    TipoDocumento::cases(),
    static fn (TipoDocumento $tipo): bool => $tipo->esRedactado(),
)));

it('los tres tipos redactados comparten generador y ninguno espera marco', function (TipoDocumento $tipo): void {
    expect($tipo->marcoEsperado())->toBeNull()
        ->and($tipo->esRedactado())->toBeTrue();
})->with(fn () => array_values(array_filter(
    TipoDocumento::cases(),
    static fn (TipoDocumento $tipo): bool => $tipo->esRedactado(),
)));
