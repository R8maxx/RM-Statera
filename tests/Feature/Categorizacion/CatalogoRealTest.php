<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Categorizacion\MotorCategorizacion;
use App\Domain\Categorizacion\ValoracionDimensiones;

/**
 * El motor contra el catálogo REAL de `catalogo/*.yaml`.
 *
 * A propósito sin cifras fijas. Los tres ficheros llevan `revisado: false` y la
 * matriz del Anexo II cambiará al contrastarla celda a celda con el BOE; un test
 * que hoy afirmara "básica exige 42 medidas" se pondría rojo por una corrección
 * legítima del catálogo. Lo que sí tiene que cumplirse pase lo que pase son las
 * propiedades: los conjuntos crecen con la categoría y nadie se queda sin nada.
 *
 * La matriz celda a celda se prueba en MotorCategorizacionTest, sobre catálogo
 * sintético.
 */
beforeEach(function (): void {
    $importador = app(ImportadorCatalogo::class);

    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        $importador->importar($fichero);
    }

    $this->ens = Marco::query()->where('codigo', 'ENS-RD311-2022')->firstOrFail();
    $this->motor = app(MotorCategorizacion::class);
});

it('cada categoría exige un superconjunto estricto de la anterior', function (): void {
    $basica = $this->motor->calcular($this->ens, uniforme(NivelDimension::Bajo));
    $media = $this->motor->calcular($this->ens, uniforme(NivelDimension::Medio));
    $alta = $this->motor->calcular($this->ens, uniforme(NivelDimension::Alto));

    expect($basica->count())->toBeGreaterThan(0)
        ->and($media->count())->toBeGreaterThan($basica->count())
        ->and($alta->count())->toBeGreaterThanOrEqual($media->count());

    foreach ($basica->codigos() as $codigo) {
        expect($media->exige($codigo))->toBeTrue("La medida {$codigo} desaparece al subir de básica a media.");
    }

    foreach ($media->codigos() as $codigo) {
        expect($alta->exige($codigo))->toBeTrue("La medida {$codigo} desaparece al subir de media a alta.");
    }
});

it('la exigencia de una medida nunca se rebaja al subir de categoría', function (): void {
    $basica = $this->motor->calcular($this->ens, uniforme(NivelDimension::Bajo));
    $alta = $this->motor->calcular($this->ens, uniforme(NivelDimension::Alto));

    foreach ($basica as $codigo => $medida) {
        expect($alta->paraCodigo($codigo)->exigencia->peso())
            ->toBeGreaterThanOrEqual(
                $medida->exigencia->peso(),
                "La medida {$codigo} se rebaja al pasar de básica a alta."
            );
    }
});

it('un sistema fuera del ámbito del ENS no debe ninguna medida', function (): void {
    expect($this->motor->calcular($this->ens, uniforme(NivelDimension::Na))->estaVacio())->toBeTrue();
});

it('el catálogo real modula por dimensión, no por la categoría global', function (): void {
    // El Anexo II modula medidas por confidencialidad, disponibilidad y
    // trazabilidad. Con una sola dimensión valorada, las moduladas que salgan
    // tienen que ser exactamente las de esa dimensión: un sistema de categoría
    // alta por confidencialidad no debe medidas de continuidad.
    $soloC = $this->motor->calcular($this->ens, ValoracionDimensiones::desdeArray(['C' => 'alto']));

    $moduladas = array_filter(
        iterator_to_array($soloC),
        fn ($medida): bool => $medida->origen === OrigenExigencia::ModulacionDimension,
    );

    expect($moduladas)->not->toBeEmpty('El YAML perdió las celdas con `dimension`; la modulación no se está ejercitando.');

    foreach ($moduladas as $medida) {
        expect($medida->dimensionModuladora)->toBe(
            Dimension::Confidencialidad,
            "La medida {$medida->codigo} se exige por {$medida->dimensionModuladora?->value} con esa dimensión a na."
        );
    }

    // Añadir disponibilidad amplía el conjunto y trae medidas moduladas por D.
    $conD = $this->motor->calcular($this->ens, ValoracionDimensiones::desdeArray(['C' => 'alto', 'D' => 'alto']));

    expect($conD->count())->toBeGreaterThan($soloC->count());

    $porDisponibilidad = array_filter(
        iterator_to_array($conD),
        fn ($medida): bool => $medida->dimensionModuladora === Dimension::Disponibilidad,
    );

    expect($porDisponibilidad)->not->toBeEmpty();
});

it('el conjunto cubre los tres marcos del Anexo II', function (): void {
    $familias = $this->motor->calcular($this->ens, uniforme(NivelDimension::Alto))->agrupadoPorFamilia();

    expect(array_keys($familias))->toEqualCanonicalizing(['org', 'op', 'mp']);
});
