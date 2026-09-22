<?php

declare(strict_types=1);

use App\Domain\Documento\Contenido\DocumentoRedactado;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;

/*
|--------------------------------------------------------------------------
| Con qué nombre se identifica la organización en el documento
|--------------------------------------------------------------------------
|
| Una Declaración de Aplicabilidad la firma una **persona jurídica**, no una
| marca, así que la portada y la cabecera de todas las páginas imprimen la razón
| social. `organizaciones.nombre` —que es el nombre de pantalla, el del sidebar
| y el de los correos— se queda de respaldo.
|
| El respaldo es lo que hace que **ningún documento cambie de texto por migrar**:
| toda organización que ya existía tiene la razón social a nulo, y ahí sigue
| saliendo exactamente lo de siempre. Por eso este fichero prueba los dos lados.
|
| Se elige un documento REDACTADO a propósito: la portada común la arma
| `ArmaContenidoComun` para los seis tipos, y una política no necesita ni
| catálogo ni sistema, así que el test es rápido y no prueba nada de más.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $this->portada = function (): array {
        $documento = Documento::factory()->politica()->create();
        $version = DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(DocumentoRedactado::class)
            ->construir($documento->fresh(), $version->fresh())
            ->portada;
    };
});

it('imprime la razón social cuando la hay', function (): void {
    $this->organizacion->forceFill([
        'nombre' => 'Merino',
        'razon_social' => 'Talleres Merino y Asociados, S.L.',
    ])->save();

    expect(($this->portada)()['organizacion'])->toBe('Talleres Merino y Asociados, S.L.');
});

it('sin razón social sigue imprimiendo el nombre de siempre', function (): void {
    $this->organizacion->forceFill(['nombre' => 'Merino', 'razon_social' => null])->save();

    expect(($this->portada)()['organizacion'])->toBe('Merino');
});

it('una razón social vacía no cuenta como razón social', function (): void {
    // Es `?:` y no `??`: el formulario normaliza, pero un importador o una
    // escritura directa pueden dejar la cadena vacía, y ahí el documento no
    // puede quedarse sin nombre de organización.
    $this->organizacion->forceFill(['nombre' => 'Merino', 'razon_social' => ''])->save();

    expect(($this->portada)()['organizacion'])->toBe('Merino');
});

it('el CIF sigue viajando aparte del nombre', function (): void {
    // La ficha de la portada pinta «Organización: nombre · CIF», así que son dos
    // claves y no una cadena compuesta.
    $this->organizacion->forceFill([
        'razon_social' => 'Talleres Merino y Asociados, S.L.',
        'cif' => 'B12345678',
    ])->save();

    $portada = ($this->portada)();

    expect($portada['cif'])->toBe('B12345678')
        ->and($portada['organizacion'])->not->toContain('B12345678');
});
