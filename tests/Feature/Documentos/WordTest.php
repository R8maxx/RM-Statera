<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Documento\Contenido\RegistroGeneradores;
use App\Domain\Documento\Cuerpo\GuardarCuerpo;
use App\Domain\Documento\Cuerpo\Nodo;
use App\Domain\Documento\Cuerpo\ResolverCuerpo;
use App\Domain\Documento\EmitirVersion;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\GuardarNarrativa;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Facades\Storage;
use Tests\Dobles\GotenbergFalso;

/**
 * La copia de trabajo en Word.
 *
 * Lo que más importa de aquí: **sale de la instantánea, nunca de una consulta
 * nueva**. Si saliera de una consulta, el Word de una versión emitida en marzo
 * enseñaría los datos de octubre y contradiría al PDF que lo acompaña, con la
 * huella de ese PDF impresa dentro.
 */
beforeEach(function (): void {
    Storage::fake('documentos');
    app()->instance(ClienteGotenberg::class, new GotenbergFalso);

    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $marco = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);

    // Con controles de verdad: un marco vacío genera un documento sin tabla, y
    // entonces el test no prueba lo que dice probar.
    foreach (['A.5.1', 'A.5.2', 'A.5.3'] as $codigo) {
        Requisito::factory()->conCodigo($codigo)->create([
            'marco_id' => $marco->id,
            'tipo' => 'control',
            'titulo' => "Control {$codigo}",
        ]);
    }

    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    app(GeneradorImplantaciones::class)->generar($this->sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($this->sistema->id)->create();
    app(MaterializarSecciones::class)($this->documento);

    $this->emitir = function (): DocumentoVersion {
        $servicio = app(GenerarDocumento::class);

        return app(EmitirVersion::class)($servicio->encolar($this->documento)->fresh());
    };
});

/** El `.docx` descargado, como cadena. */
function descargarWord(int $documentoId, int $versionId): string
{
    $respuesta = test()->actingAs(test()->usuario)
        ->get("/documentos/{$documentoId}/versiones/{$versionId}/word")
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    return $respuesta->streamedContent();
}

/** Lee una entrada del `.docx`, que es un ZIP. */
function entradaDelDocx(string $docx, string $ruta): string
{
    $temporal = tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($temporal, $docx);

    $zip = new ZipArchive;
    $zip->open($temporal);
    $contenido = (string) $zip->getFromName($ruta);
    $zip->close();
    unlink($temporal);

    return $contenido;
}

it('devuelve un .docx de verdad', function (): void {
    $version = ($this->emitir)();

    $docx = descargarWord($this->documento->id, $version->id);

    // Un OOXML es un ZIP: empieza por la firma `PK`.
    expect($docx)->toStartWith('PK');
    expect(strlen($docx))->toBeGreaterThan(5_000);
});

it('sale de la instantánea y no de una consulta nueva', function (): void {
    $version = ($this->emitir)();

    // Se cambia el registro DESPUÉS de emitir.
    Implantacion::query()
        ->where('sistema_id', $this->sistema->id)
        ->update(['estado' => EstadoImplantacion::Implantado->value]);

    $texto = entradaDelDocx(descargarWord($this->documento->id, $version->id), 'word/document.xml');

    /*
     * Si el Word se construyera consultando, aquí pondría «Implantado» y el PDF
     * que lo acompaña —con su huella dentro— diría «No iniciado». Dos ficheros
     * de la misma versión contando cosas distintas.
     */
    // Se afirma sobre el texto de CELDA y no sobre el XML entero: la cabecera
    // del resumen dice «Implantados» y contiene la palabra.
    expect($texto)
        ->toContain('>No iniciado</w:t>')
        ->not->toContain('>Implantado</w:t>');
});

it('lleva las marcas de copia de trabajo donde sobreviven a un reenvío', function (): void {
    $version = ($this->emitir)();
    $docx = descargarWord($this->documento->id, $version->id);

    // En las propiedades: sobreviven a copiar el fichero y a renombrarlo.
    $propiedades = entradaDelDocx($docx, 'docProps/core.xml');
    expect($propiedades)
        ->toContain('copia de trabajo')
        ->toContain('Copia de trabajo');

    // Y la huella del PDF que sí es la entrega.
    expect(entradaDelDocx($docx, 'docProps/custom.xml'))
        ->toContain('Statera-Huella-PDF')
        ->toContain((string) $version->hash_sha256);

    // En el pie de todas las páginas.
    expect(entradaDelDocx($docx, 'word/footer1.xml'))
        ->toContain('Copia de trabajo');
});

it('incluye la narrativa que ha redactado la organización', function (): void {
    app(GuardarNarrativa::class)($this->documento, ['conclusiones' => 'Conclusión propia del SGSI.']);

    $version = ($this->emitir)();

    expect(entradaDelDocx(descargarWord($this->documento->id, $version->id), 'word/document.xml'))
        ->toContain('Conclusión propia del SGSI.');
});

/**
 * La copia de trabajo es del documento que se entregó, no del que Statera
 * generaría hoy.
 *
 * Antes esto no se cumplía: el `.docx` montaba una secuencia fija desde
 * `ContenidoDocumento` y lo redactado en el editor no aparecía por ningún lado.
 * Quien abriera el Word de una versión emitida vería un documento distinto del
 * PDF que lleva al lado, con la huella de ese PDF impresa dentro.
 */
it('lleva lo que se redactó a mano en el editor', function (): void {
    $contenido = app(RegistroGeneradores::class)
        ->para($this->documento->tipo)
        ->construir($this->documento, new DocumentoVersion(['documento_id' => $this->documento->id]), []);

    $fila = app(ResolverCuerpo::class)->fila($this->documento, $contenido);

    $cuerpo = $fila->cuerpo;
    $cuerpo['content'][] = Nodo::de('seccion', [], [
        Nodo::encabezado(2, 'Apartado escrito a mano'),
        Nodo::parrafo('Esta frase la escribió la organización en el editor.'),
    ]);

    app(GuardarCuerpo::class)($fila, $cuerpo);

    $version = ($this->emitir)();

    expect(entradaDelDocx(descargarWord($this->documento->id, $version->id), 'word/document.xml'))
        ->toContain('Apartado escrito a mano')
        ->toContain('Esta frase la escribió la organización en el editor.');
});

/**
 * Y en el orden en que se redactó, no en el que decidía el escritor.
 */
it('respeta el orden del documento', function (): void {
    $version = ($this->emitir)();

    $texto = entradaDelDocx(descargarWord($this->documento->id, $version->id), 'word/document.xml');

    $resumen = strpos($texto, 'Resumen');
    $limitaciones = strpos($texto, 'Limitaciones de esta declaración');
    $versiones = strpos($texto, 'Control de versiones');

    expect($resumen)->toBeLessThan($limitaciones)
        ->and($limitaciones)->toBeLessThan($versiones);
});

it('una versión sin instantánea no se puede exportar', function (): void {
    $version = DocumentoVersion::factory()->delDocumento($this->documento->id)->create();

    $this->actingAs($this->usuario)
        ->get("/documentos/{$this->documento->id}/versiones/{$version->id}/word")
        ->assertNotFound();
});

it('no se descarga el Word de otra organización', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    $sistema = Sistema::factory()->de($ajena)->conMarco(Marco::query()->firstOrFail())->create();
    $documento = Documento::factory()->soa()->paraSistema($sistema->id)->create(['codigo' => 'SOA-AJE']);
    $version = DocumentoVersion::factory()->delDocumento($documento->id)->emitida()->create();

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->get("/documentos/{$documento->id}/versiones/{$version->id}/word")
        ->assertNotFound();
});
