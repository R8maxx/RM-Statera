<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Contenido\RegistroGeneradores;
use App\Domain\Documento\Cuerpo\HtmlDocumento;
use App\Domain\Documento\Excepciones\GeneracionFallida;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Documento\Render\IndiceDocumento;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * El índice del PDF: dos pasadas, y sólo cuando el documento lo merece.
 *
 * Gotenberg es el doble, que inventa una paginación coherente —el índice en la
 * página 1 y cada sección `paginasPorSeccion` páginas después de la anterior—.
 * Que los números sean los del PDF real lo comprueba `GotenbergIntegracionTest`;
 * aquí, que lo medido llega al documento y que la medida no se entrega.
 */
beforeEach(function (): void {
    Storage::fake('documentos');

    $this->gotenberg = new GotenbergFalso;
    app()->instance(ClienteGotenberg::class, $this->gotenberg);

    $this->organizacion = comoOrganizacion();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($this->sistema);

    $this->soa = Documento::factory()->soa()->paraSistema($this->sistema->id)->create();
});

it('mide primero sin portada y entrega después con los números medidos', function (): void {
    app(GenerarDocumento::class)->encolar($this->soa);

    expect($this->gotenberg->mediciones)->toHaveCount(1)
        ->and($this->gotenberg->solicitudes)->toHaveCount(1);

    $medida = $this->gotenberg->mediciones[0];
    $entrega = $this->gotenberg->ultima();

    // La medida lleva marcadores y relleno, y no lleva portada: el índice
    // cuenta como el pie, desde la primera página del cuerpo.
    expect($medida->portada)->toBeNull()
        ->and($medida->html)->toContain('@@s-1@@')
        ->and($medida->html)->toContain('<span class="indice__pagina cifra">000</span>');

    // La entrega, ni marcadores ni relleno: la sección 1 en la 2, la 2 en la 5…
    expect($entrega->portada)->toContain('<section class="portada">')
        ->and($entrega->html)->not->toContain('@@s-')
        ->and($entrega->html)->not->toContain('<span class="indice__pagina cifra">000</span>')
        ->and($entrega->html)->toContain('<a class="indice__titulo" href="#s-1">')
        ->and($entrega->html)->toMatch('#href="\#s-1">[^<]+</a><span class="indice__guia" aria-hidden="true"></span><span class="indice__pagina cifra">2</span>#')
        ->and($entrega->html)->toMatch('#href="\#s-2">[^<]+</a><span class="indice__guia" aria-hidden="true"></span><span class="indice__pagina cifra">5</span>#');
});

it('imprime sin índice el documento que al medirlo resulta corto', function (): void {
    $this->gotenberg->paginasPorSeccion = 1;

    $politica = Documento::factory()->politica()->create();

    app(GenerarDocumento::class)->encolar($politica);

    expect($this->gotenberg->mediciones)->toHaveCount(1)
        ->and($this->gotenberg->html())->not->toContain('class="indice"')
        ->and($this->gotenberg->html())->not->toContain('@@s-');
});

it('ni siquiera mide un documento con menos de tres secciones', function (): void {
    $politica = Documento::factory()->politica()->create();

    // Se le quitan secciones al cuerpo de fábrica hasta dejar dos.
    $version = DocumentoVersion::factory()->delDocumento($politica->id)->create();
    $contenido = app(RegistroGeneradores::class)->para($politica->tipo)->construir($politica, $version, []);
    $cuerpo = app(HtmlDocumento::class)->cuerpo($politica, $contenido);

    $secciones = array_values(array_filter($cuerpo['content'], fn (array $nodo): bool => $nodo['type'] === 'seccion'));
    $cuerpo['content'] = [$cuerpo['content'][0], ...array_slice($secciones, 0, 2)];

    $indice = app(IndiceDocumento::class);

    expect($indice->mereceMedirse($indice->entradas($cuerpo)))->toBeFalse();
});

it('no entrega nada si la pasada de medida falla', function (): void {
    $this->gotenberg = new GotenbergFalso('Gotenberg caído');
    app()->instance(ClienteGotenberg::class, $this->gotenberg);

    // La cola es síncrona en la suite: el fallo sale de `encolar()`.
    expect(fn () => app(GenerarDocumento::class)->encolar($this->soa))
        ->toThrow(GeneracionFallida::class, 'Gotenberg caído');

    expect($this->gotenberg->mediciones)->toHaveCount(1)
        ->and($this->gotenberg->solicitudes)->toBe([])
        ->and(Storage::disk('documentos')->allFiles())->toBe([]);
});
