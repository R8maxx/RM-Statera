<?php

declare(strict_types=1);

use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Render\AssetsDocumento;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Organizacion\Marca\GuardarPiezaDeMarca;
use App\Domain\Organizacion\Marca\PiezaDeMarca;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/*
|--------------------------------------------------------------------------
| El logo del cliente dentro del PDF
|--------------------------------------------------------------------------
|
| La decisión que da forma a todo esto: **el logo es marca, no contenido**, lo
| mismo que el filete y la palabra «Statera» de la portada (`CuerpoDeFabrica`).
| Por eso entra por CSS y no por el árbol del cuerpo, y por eso `EsquemaCuerpo`,
| `RenderizadorCuerpo` y el `.docx` no se enteran de que existe — ese esquema
| declara por escrito que NO hay nodo de imagen ni de SVG, y sigue sin haberlo.
|
| Va como **data URI** y no como fichero del multipart porque la cabecera se
| renderiza en un contexto aparte que no recibe los assets. Con una sola vía hay
| un solo mecanismo y un solo sitio donde se rompe.
|
| Lo más importante que se prueba aquí es lo de abajo del todo: **sin logo, el
| documento sale exactamente como salía**.
|
*/

beforeEach(function (): void {
    Storage::fake('documentos');
    Storage::fake('adjuntos');

    $this->gotenberg = new GotenbergFalso;
    app()->instance(ClienteGotenberg::class, $this->gotenberg);

    $this->organizacion = comoOrganizacion();
    $this->documento = Documento::factory()->politica()->create(['codigo' => 'POL-SEG-01']);

    $this->generar = function (): void {
        app(GenerarDocumento::class)->encolar($this->documento->fresh(), null);
    };

    $this->subir = function (PiezaDeMarca $pieza, ?UploadedFile $fichero = null): void {
        app(GuardarPiezaDeMarca::class)(
            $this->organizacion,
            $pieza,
            $fichero ?? UploadedFile::fake()->image('marca.png', 400, 120),
        );
    };
});

/** La hoja de estilos que viajó en el multipart de la última generación. */
function hojaGenerada(GotenbergFalso $gotenberg): string
{
    foreach ($gotenberg->ultima()->assets as $asset) {
        if ($asset->nombre === AssetsDocumento::HOJA) {
            return $asset->contenido;
        }
    }

    throw new RuntimeException('La hoja no viajó en el multipart.');
}

it('mete el logo de fondo en la portada, incrustado en la hoja', function (): void {
    ($this->subir)(PiezaDeMarca::Logo);
    ($this->generar)();

    $hoja = hojaGenerada($this->gotenberg);

    expect($hoja)->toContain('.portada {')
        ->and($hoja)->toContain("background-image: url('data:image/png;base64,")
        ->and($hoja)->toContain('background-position: top right');
});

it('mete el símbolo en la cabecera, que no recibe los assets', function (): void {
    ($this->subir)(PiezaDeMarca::Simbolo, UploadedFile::fake()->image('s.png', 120, 120));
    ($this->generar)();

    $cabecera = $this->gotenberg->ultima()->cabecera;

    expect($cabecera)->toContain('class="cab__simbolo"')
        ->and($cabecera)->toContain('src="data:image/png;base64,')
        // Y sigue siendo autocontenida: la hoja común no llega aquí.
        ->and($cabecera)->not->toContain(AssetsDocumento::HOJA);
});

it('convive con Statera en vez de sustituirlo', function (): void {
    // Co-branding y no marca blanca: la herramienta sigue diciendo cuál es.
    ($this->subir)(PiezaDeMarca::Logo);
    ($this->subir)(PiezaDeMarca::Simbolo, UploadedFile::fake()->image('s.png', 120, 120));
    ($this->generar)();

    expect($this->gotenberg->ultima()->cabecera)->toContain('Statera')
        ->and($this->gotenberg->html())->toContain('portada__marca');
});

it('no mete ninguna URL remota en el documento', function (): void {
    // La allow-list de Gotenberg deniega todo lo que no sea `file:///tmp/` o
    // `data:`, y `failOnResourceLoadingFailed()` está encendido: una remota no
    // degradaría, tumbaría la generación entera.
    ($this->subir)(PiezaDeMarca::Logo);
    ($this->subir)(PiezaDeMarca::Simbolo, UploadedFile::fake()->image('s.png', 120, 120));
    ($this->generar)();

    $solicitud = $this->gotenberg->ultima();

    foreach ([$solicitud->html, $solicitud->cabecera, hojaGenerada($this->gotenberg)] as $parte) {
        expect($parte)->not->toContain('http://')
            ->and($parte)->not->toContain('https://');
    }
});

it('el logo no entra en el cuerpo ni en la instantánea', function (): void {
    /*
     * Es la mitad que justifica el diseño. `EsquemaCuerpo` declara que no hay
     * nodo de imagen porque «una incrustada hincharía la instantánea sin
     * límite»; como el logo va por CSS, esa frase sigue siendo cierta y la
     * instantánea no engorda ni un byte.
     */
    ($this->subir)(PiezaDeMarca::Logo);
    ($this->generar)();

    $instantanea = $this->documento->fresh()->versiones()->latest('id')->firstOrFail()->instantanea;

    expect(json_encode($instantanea))->not->toContain('data:image')
        ->and($this->gotenberg->html())->not->toContain('data:image');
});

it('sin logo el documento sale exactamente como salía', function (): void {
    ($this->generar)();

    $solicitud = $this->gotenberg->ultima();

    expect(hojaGenerada($this->gotenberg))->not->toContain('background-image')
        ->and($solicitud->cabecera)->not->toContain('cab__simbolo')
        ->and($solicitud->cabecera)->not->toContain('<img')
        // Y la cabecera de siempre sigue en su sitio.
        ->and($solicitud->cabecera)->toContain('Statera');
});

it('con sólo una de las dos piezas, la otra no aparece', function (): void {
    // Las dos son opcionales por separado: quien sólo tenga logo horizontal no
    // se queda sin portada, y su cabecera sigue siendo la de texto.
    ($this->subir)(PiezaDeMarca::Logo);
    ($this->generar)();

    expect(hojaGenerada($this->gotenberg))->toContain('background-image')
        ->and($this->gotenberg->ultima()->cabecera)->not->toContain('<img');
});
