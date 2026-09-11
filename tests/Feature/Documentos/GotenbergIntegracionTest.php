<?php

declare(strict_types=1);

use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Facades\Storage;

/**
 * El único test que habla con el contenedor de Gotenberg.
 *
 * Se salta si no está levantado, para que la suite corra en cualquier máquina:
 * `docker compose up -d gotenberg` y vuelve a entrar. Lo que comprueba es lo que
 * el doble no puede comprobar —que el PDF sale, que es PDF/A-3b y que las
 * fuentes van embebidas—; el contenido se prueba en los tests de contenido.
 */
beforeEach(function (): void {
    Storage::fake('documentos');

    $organizacion = comoOrganizacion();

    $importador = app(ImportadorCatalogo::class);
    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        $importador->importar($fichero);
    }

    $iso = Marco::query()->where('codigo', 'ISO27001-2022')->firstOrFail();
    $sistema = Sistema::factory()->de($organizacion)->conMarco($iso)->create([
        'alcance_declarado' => 'Alcance sintético para el test de integración.',
    ]);

    app(GeneradorImplantaciones::class)->generar($sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($sistema->id)->create();
})->group('gotenberg')->skip(
    fn (): bool => ! gotenbergDisponible(),
    'Gotenberg no está levantado (docker compose up -d gotenberg).',
);

it('produce un PDF/A-3b de verdad, con la SoA completa dentro', function (): void {
    $version = app(GenerarDocumento::class)->encolar($this->documento)->fresh();

    expect($version->estado_generacion->value)->toBe('generada');

    $pdf = Storage::disk('documentos')->get((string) $version->ruta);

    expect($pdf)->toStartWith('%PDF-');
    // Noventa y tres controles no caben en diez kilobytes: si sale menos, algo
    // se quedó por el camino.
    expect(strlen($pdf))->toBeGreaterThan(10_000);

    /*
     * En un PDF/A el XMP va SIN comprimir, así que se puede buscar tal cual.
     * Lo que no se puede es buscar el texto de un control con `str_contains`:
     * el contenido va en streams comprimidos y daría un falso negativo.
     */
    expect($pdf)->toContain('pdfaid:part>3')->toContain('pdfaid:conformance>B');

    /*
     * `/FontFile2` o `/FontFile3` demuestra que las fuentes van EMBEBIDAS, que es
     * lo que exige PDF/A y lo que hace que el texto siga siendo seleccionable.
     *
     * No se afirma CUÁLES son: Chromium embebe Instrument Sans y JetBrains Mono
     * —comprobado generando sin `pdfa()`— y el paso a PDF/A las resustituye por
     * Noto Sans y Arial. Es conocido, está aceptado y está escrito en CLAUDE.md;
     * atarlo aquí sería fijar un detalle de Ghostscript que cambiará al actualizar
     * el contenedor.
     */
    expect($pdf)->toMatch('/\/FontFile[23]/');

    expect($version->hash_sha256)->toBe(hash('sha256', $pdf));
})->group('gotenberg')->skip(
    fn (): bool => ! gotenbergDisponible(),
    'Gotenberg no está levantado (docker compose up -d gotenberg).',
);
