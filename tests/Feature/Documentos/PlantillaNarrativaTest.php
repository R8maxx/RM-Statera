<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Enums\OrigenTexto;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoSeccion;
use App\Domain\Documento\Models\PlantillaSeccion;
use App\Domain\Documento\Narrativa\GuardarNarrativa;
use App\Domain\Documento\Narrativa\GuardarPlantilla;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Domain\Documento\Narrativa\TextosDeFabrica;
use App\Domain\Sistema\Models\Sistema;

/**
 * La cadena documento → plantilla → fábrica.
 *
 * Lo que fija: que un documento sea un HECHO y no el resultado de un join que
 * cambia bajo los pies, y que «vacío a conciencia» y «no lo he tocado» sean dos
 * cosas distintas.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();

    $this->documento = Documento::factory()->soa()->paraSistema($this->sistema->id)->create();

    $this->resolver = app(ResolverNarrativa::class);
});

it('un documento sin filas resuelve al texto de fábrica', function (): void {
    // Los documentos creados antes de que esto existiera no tienen filas, y su
    // PDF tiene que salir idéntico al de siempre. Sin migración de datos.
    expect($this->documento->secciones()->count())->toBe(0);

    expect($this->resolver->paraDocumento($this->documento)['introduccion'])
        ->toBe(TextosDeFabrica::para(TipoDocumento::SoaIso, SeccionNarrativa::Introduccion));
});

it('materializar es idempotente', function (): void {
    $materializar = app(MaterializarSecciones::class);

    $primera = $materializar($this->documento);

    expect($primera)->toBe(count(SeccionNarrativa::paraTipo(TipoDocumento::SoaIso)));
    expect($materializar($this->documento))->toBe(0);
    expect($this->documento->secciones()->count())->toBe($primera);
});

it('un documento nuevo arranca con la plantilla de la organización', function (): void {
    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['introduccion' => 'Nuestra introducción.']);

    app(MaterializarSecciones::class)($this->documento);

    expect($this->resolver->paraDocumento($this->documento->fresh())['introduccion'])
        ->toBe('Nuestra introducción.');
});

it('cambiar la plantilla DESPUÉS no cambia un documento ya creado', function (): void {
    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['introduccion' => 'La de entonces.']);
    app(MaterializarSecciones::class)($this->documento);

    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['introduccion' => 'La de ahora.']);

    // Lo que dice un documento es un hecho del documento. Es el mismo
    // razonamiento que hay detrás de la instantánea de una versión.
    expect($this->resolver->paraDocumento($this->documento->fresh())['introduccion'])
        ->toBe('La de entonces.');
});

it('una fila vacía NO es lo mismo que una fila ausente', function (): void {
    app(MaterializarSecciones::class)($this->documento);

    app(GuardarNarrativa::class)($this->documento, ['introduccion' => '']);

    // Sin esta distinción, borrar un texto lo resucitaría en la siguiente
    // generación: el fallo silencioso clásico.
    expect($this->resolver->paraDocumento($this->documento->fresh())['introduccion'])->toBe('');
});

it('guardar la plantilla sin tocarla no la congela', function (): void {
    $textos = $this->resolver->paraPlantilla(TipoDocumento::SoaIso);

    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, $textos);

    /*
     * Si guardar sin cambiar nada dejara fila en cada sección, esa organización
     * dejaría de recibir cualquier mejora futura del texto de Statera sin
     * haberlo decidido y sin enterarse. «No lo he tocado» y «no hay fila» tienen
     * que ser lo mismo.
     */
    expect(PlantillaSeccion::query()->count())->toBe(0);
});

it('calcula el origen de cada texto en vez de fiarse de lo que le digan', function (): void {
    app(MaterializarSecciones::class)($this->documento);

    app(GuardarNarrativa::class)($this->documento, ['conclusiones' => 'Algo nuestro.']);

    $filas = $this->resolver->filasDe($this->documento->fresh());

    expect($filas['conclusiones']->origen)->toBe(OrigenTexto::Propio)
        ->and($filas['introduccion']->origen)->toBe(OrigenTexto::Plantilla);
});

it('restablecer devuelve un hueco a la plantilla', function (): void {
    app(GuardarPlantilla::class)(TipoDocumento::SoaIso, ['conclusiones' => 'Lo de la organización.']);
    app(MaterializarSecciones::class)($this->documento);
    app(GuardarNarrativa::class)($this->documento, ['conclusiones' => 'Lo mío.']);

    app(GuardarNarrativa::class)->restablecer($this->documento, SeccionNarrativa::Conclusiones);

    $filas = $this->resolver->filasDe($this->documento->fresh());

    expect($filas['conclusiones']->contenido_md)->toBe('Lo de la organización.')
        ->and($filas['conclusiones']->origen)->toBe(OrigenTexto::Plantilla);
});

it('cambiar el tipo del documento no deja secciones huérfanas', function (): void {
    app(MaterializarSecciones::class)($this->documento);

    expect($this->documento->secciones()->where('seccion', 'nota_exclusiones')->exists())->toBeTrue();

    $ens = Sistema::factory()->de($this->organizacion)
        ->conMarco(Marco::factory()->create(['codigo' => 'ENS-SINTETICO']))
        ->create();

    $this->documento->update(['tipo' => TipoDocumento::DdaEns->value, 'sistema_id' => $ens->id]);
    app(MaterializarSecciones::class)->sincronizarConElTipo($this->documento->fresh());

    // `nota_exclusiones` es de ISO: arrastrarla invisible haría que reapareciera
    // —con un texto de hace meses— si alguien devolviera el documento a SoA.
    expect(DocumentoSeccion::query()->where('seccion', 'nota_exclusiones')->exists())->toBeFalse();
    expect(DocumentoSeccion::query()->where('seccion', 'nota_derivacion')->exists())->toBeTrue();
});
