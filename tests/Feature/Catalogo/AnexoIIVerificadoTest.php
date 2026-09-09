<?php

declare(strict_types=1);

use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\AplicabilidadEns;
use App\Domain\Catalogo\Models\Requisito;

/*
|--------------------------------------------------------------------------
| Celdas contrastadas con el BOE
|--------------------------------------------------------------------------
|
| Prioridad 1 de cobertura: un fallo en el motor de categorización deja a un
| cliente fuera de conformidad sin que nadie se entere. Y la forma más fácil de
| tener ese fallo no es un error en el motor, es una celda mal escrita en el
| catálogo.
|
| Aquí van clavadas las celdas donde el catálogo estaba mal antes de contrastarlo
| con BOE-A-2022-7191, para que no vuelvan a torcerse. Nueve de ellas exigían de
| MENOS en categoría básica, que es exactamente el fallo silencioso que importa.
|
*/

beforeEach(function (): void {
    $importador = app(ImportadorCatalogo::class);

    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        $importador->importar($fichero);
    }

    $this->celda = function (string $codigo, string $categoria): AplicabilidadEns {
        $requisito = Requisito::query()->delMarco('ENS-RD311-2022')->where('codigo', $codigo)->firstOrFail();

        return AplicabilidadEns::query()
            ->where('requisito_id', $requisito->id)
            ->where('categoria', $categoria)
            ->firstOrFail();
    };
});

it('exige en básica las nueve medidas que el catálogo se dejaba fuera', function (): void {
    // Todas dicen «aplica» en la columna BÁSICA del Anexo II, y el catálogo las
    // tenía en `no_aplica`: un sistema de categoría básica se quedaba sin ellas.
    $medidas = [
        'op.pl.4',   // Dimensionamiento/gestión de la capacidad
        'op.exp.7',  // Gestión de incidentes
        'op.exp.8',  // Registro de la actividad
        'op.exp.9',  // Registro de la gestión de incidentes
        'op.exp.10', // Protección de claves criptográficas
        'op.mon.1',  // Detección de intrusión
        'op.mon.2',  // Sistema de métricas
        'mp.com.2',  // Protección de la confidencialidad
        'mp.info.3', // Cifrado de la información
    ];

    foreach ($medidas as $codigo) {
        expect(($this->celda)($codigo, 'basica')->exigencia->valor)->toBe('aplica', $codigo);
    }
});

it('guarda el refuerzo mayor de una celda acumulativa', function (): void {
    // En el Anexo II los refuerzos se suman: «+ R1 + R2 + R3» exige los tres.
    // Se guarda el mayor, y `R3` se lee como «hasta R3».
    $celdas = [
        ['op.pl.2', 'alta', 'R3'],  // + R1 + R2 + R3
        ['op.exp.6', 'alta', 'R4'], // + R1 + R2 + R3 + R4
        ['op.exp.8', 'alta', 'R5'], // + R1 + R2 + R3 + R4 + R5
        ['op.mon.3', 'alta', 'R6'], // + R1 + R2 + R3 + R4 + R5 + R6
        ['mp.com.3', 'alta', 'R4'], // + R1 + R2 + R3 + R4
    ];

    foreach ($celdas as [$codigo, $categoria, $esperado]) {
        expect(($this->celda)($codigo, $categoria)->exigencia->valor)->toBe($esperado, $codigo);
    }
});

it('no baja la exigencia donde el BOE no la baja', function (): void {
    // El catálogo inventaba refuerzos en alta donde el Anexo II dice «aplica» a
    // secas. Exigir de más también es un error: obliga a trabajo que nadie pide.
    expect(($this->celda)('op.ext.1', 'alta')->exigencia->valor)->toBe('aplica')
        ->and(($this->celda)('op.ext.2', 'alta')->exigencia->valor)->toBe('aplica')
        ->and(($this->celda)('mp.per.1', 'alta')->exigencia->valor)->toBe('aplica')
        ->and(($this->celda)('mp.com.1', 'alta')->exigencia->valor)->toBe('aplica')
        ->and(($this->celda)('mp.info.2', 'alta')->exigencia->valor)->toBe('aplica');
});

it('`op.exp.11` es numeración del RD 3/2010 y no está vigente', function (): void {
    // El RD 311/2022 llega hasta `op.exp.10`, que es «Protección de claves
    // criptográficas». Lo que había era la numeración del esquema anterior.
    $vigentes = Requisito::query()->delMarco('ENS-RD311-2022')->vigentes()->pluck('codigo');

    expect($vigentes)->not->toContain('op.exp.11')
        ->and($vigentes)->toContain('op.exp.10');

    $claves = Requisito::query()->delMarco('ENS-RD311-2022')->where('codigo', 'op.exp.10')->firstOrFail();

    expect($claves->titulo)->toBe('Protección de claves criptográficas');
});

it('los títulos son los del BOE, no una paráfrasis', function (): void {
    $titulos = [
        'mp.com.3' => 'Protección de la integridad y de la autenticidad',
        'mp.info.1' => 'Datos personales',
        'mp.s.4' => 'Protección frente a denegación de servicio',
        'op.pl.4' => 'Dimensionamiento/gestión de la capacidad',
    ];

    foreach ($titulos as $codigo => $titulo) {
        $requisito = Requisito::query()->delMarco('ENS-RD311-2022')->where('codigo', $codigo)->firstOrFail();

        expect($requisito->titulo)->toBe($titulo);
    }
});

it('la modulación por dimensión es la del Anexo II donde el modelo la admite', function (): void {
    $moduladas = [
        'op.pl.4' => 'D',
        'mp.if.5' => 'D',
        'mp.if.6' => 'D',
        'mp.eq.2' => 'A',
        'mp.eq.4' => 'C',
        'mp.si.5' => 'C',
        'mp.info.5' => 'C',
    ];

    foreach ($moduladas as $codigo => $dimension) {
        foreach (['basica', 'media', 'alta'] as $categoria) {
            expect(($this->celda)($codigo, $categoria)->dimension_moduladora?->value)->toBe($dimension, $codigo);
        }
    }
});

it('las moduladas por varias dimensiones se leen por categoría, que nunca exige de menos', function (): void {
    /*
     * El Anexo II las modula por un CONJUNTO de dimensiones y la tabla sólo
     * guarda una. Leerlas por categoría —el máximo de las cinco— exige de más
     * cuando la dimensión más alta es una de las que el Anexo II deja fuera, y
     * nunca de menos. Es la única aproximación segura mientras el modelo no
     * admita el conjunto, y este test fija que sigue siendo esa y no otra.
     */
    $medidas = ['op.acc.1', 'op.acc.2', 'op.acc.3', 'op.acc.4', 'op.acc.5', 'op.acc.6', 'mp.com.3', 'mp.si.2', 'mp.info.3'];

    foreach ($medidas as $codigo) {
        foreach (['basica', 'media', 'alta'] as $categoria) {
            expect(($this->celda)($codigo, $categoria)->dimension_moduladora)->toBeNull($codigo);
        }
    }
});
