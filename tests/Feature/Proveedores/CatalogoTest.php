<?php

declare(strict_types=1);

use App\Domain\Catalogo\Excepciones\CatalogoInvalido;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Proveedor\Models\ClausulaContractual;

/*
|--------------------------------------------------------------------------
| El catálogo de cláusulas de proveedor (§ 4.9)
|--------------------------------------------------------------------------
|
| Datos y no código (invariante 3), con el contrato de siempre: idempotente,
| retirada por marca y referencias que tienen que existir.
|
*/

beforeEach(function (): void {
    $importador = app(ImportadorCatalogo::class);

    foreach (['iso27001-2022.yaml', 'ens-rd311-2022.yaml'] as $marco) {
        $importador->importar(base_path("catalogo/{$marco}"));
    }

    $this->importador = $importador;
    $this->fichero = base_path('catalogo/clausulas-proveedor.yaml');
});

it('importa el fichero real y la segunda pasada no cambia nada', function (): void {
    $primera = $this->importador->importar($this->fichero);
    $segunda = $this->importador->importar($this->fichero);

    expect($primera->nuevos)->not->toBeEmpty()
        ->and($segunda->hayCambios())->toBeFalse()
        ->and(ClausulaContractual::query()->vigentes()->count())->toBe(count($primera->nuevos));
});

it('lo que desaparece se retira y no se borra', function (): void {
    $this->importador->importar($this->fichero);

    $temporal = tempnam(sys_get_temp_dir(), 'clausulas').'.yaml';
    file_put_contents($temporal, "clausulas:\n  - codigo: CLA-01\n    titulo: Confidencialidad\n");

    $resultado = $this->importador->importar($temporal);

    expect($resultado->retirados)->not->toBeEmpty()
        ->and(ClausulaContractual::query()->where('codigo', 'CLA-02')->firstOrFail()->vigente)->toBeFalse();

    unlink($temporal);
});

it('una referencia a un requisito que no existe es un error de importación', function (): void {
    $temporal = tempnam(sys_get_temp_dir(), 'clausulas').'.yaml';
    file_put_contents($temporal, "clausulas:\n  - codigo: CLA-X\n    titulo: Inventada\n    referencias:\n      - {marco: ISO27001-2022, requisito: A.99.99}\n");

    expect(fn () => $this->importador->importar($temporal))->toThrow(CatalogoInvalido::class);

    unlink($temporal);
});

it('el fichero de cláusulas va después de los marcos al importar un directorio', function (): void {
    $ficheros = array_map('basename', $this->importador->ficherosDe(base_path('catalogo')));

    expect(array_search('clausulas-proveedor.yaml', $ficheros, true))
        ->toBeGreaterThan(array_search('iso27001-2022.yaml', $ficheros, true))
        ->toBeGreaterThan(array_search('ens-rd311-2022.yaml', $ficheros, true));
});
