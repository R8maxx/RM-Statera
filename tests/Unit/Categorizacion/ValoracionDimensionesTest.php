<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\ValoracionDimensiones;

it('deriva la categoría como el máximo de las cinco dimensiones, en las 1024 combinaciones', function (): void {
    $niveles = NivelDimension::cases();
    $comprobadas = 0;

    foreach ($niveles as $c) {
        foreach ($niveles as $i) {
            foreach ($niveles as $d) {
                foreach ($niveles as $a) {
                    foreach ($niveles as $t) {
                        $valoracion = new ValoracionDimensiones($c, $i, $d, $a, $t);

                        $maximo = max($c->peso(), $i->peso(), $d->peso(), $a->peso(), $t->peso());

                        $esperada = match ($maximo) {
                            0 => null,
                            1 => CategoriaEns::Basica,
                            2 => CategoriaEns::Media,
                            default => CategoriaEns::Alta,
                        };

                        expect($valoracion->categoria())->toBe($esperada);
                        $comprobadas++;
                    }
                }
            }
        }
    }

    expect($comprobadas)->toBe(1024);
});

it('las cinco dimensiones a na dejan el sistema fuera del ámbito del ENS', function (): void {
    $valoracion = new ValoracionDimensiones;

    // `na` en las cinco no es categoría básica: es que el ENS no aplica.
    expect($valoracion->categoria())->toBeNull()
        ->and($valoracion->enAmbitoEns())->toBeFalse()
        ->and($valoracion->nivelMaximo())->toBe(NivelDimension::Na);
});

it('una sola dimensión valorada ya mete el sistema en el ámbito', function (): void {
    $valoracion = (new ValoracionDimensiones)->conNivel(Dimension::Trazabilidad, NivelDimension::Bajo);

    expect($valoracion->categoria())->toBe(CategoriaEns::Basica)
        ->and($valoracion->enAmbitoEns())->toBeTrue();
});

it('lee el nivel de cada dimensión por su código', function (): void {
    $valoracion = ValoracionDimensiones::desdeArray([
        'C' => 'alto', 'I' => 'medio', 'D' => 'bajo', 'A' => 'na', 'T' => NivelDimension::Medio,
    ]);

    expect($valoracion->nivelDe(Dimension::Confidencialidad))->toBe(NivelDimension::Alto)
        ->and($valoracion->nivelDe(Dimension::Integridad))->toBe(NivelDimension::Medio)
        ->and($valoracion->nivelDe(Dimension::Disponibilidad))->toBe(NivelDimension::Bajo)
        ->and($valoracion->nivelDe(Dimension::Autenticidad))->toBe(NivelDimension::Na)
        ->and($valoracion->nivelDe(Dimension::Trazabilidad))->toBe(NivelDimension::Medio);
});

it('las dimensiones que no se pasan quedan en na', function (): void {
    $valoracion = ValoracionDimensiones::desdeArray(['D' => 'alto']);

    expect($valoracion->categoria())->toBe(CategoriaEns::Alta)
        ->and($valoracion->nivelDe(Dimension::Confidencialidad))->toBe(NivelDimension::Na);
});

it('rechaza una dimensión que no es una de las cinco', function (): void {
    ValoracionDimensiones::desdeArray(['X' => 'alto']);
})->throws(InvalidArgumentException::class, 'Dimensión no reconocida');

it('rechaza un nivel que no está en la escala del Anexo I', function (): void {
    ValoracionDimensiones::desdeArray(['C' => 'muy_alto']);
})->throws(InvalidArgumentException::class, 'Nivel no reconocido');

it('es inmutable: conNivel devuelve una copia', function (): void {
    $original = ValoracionDimensiones::desdeArray(['C' => 'bajo']);
    $copia = $original->conNivel(Dimension::Confidencialidad, NivelDimension::Alto);

    expect($original->confidencialidad)->toBe(NivelDimension::Bajo)
        ->and($copia->confidencialidad)->toBe(NivelDimension::Alto)
        ->and($original->equivale($copia))->toBeFalse()
        ->and($original->equivale(ValoracionDimensiones::desdeArray(['C' => 'bajo'])))->toBeTrue();
});

it('traduce nivel de dimensión a categoría en un solo sitio', function (): void {
    expect(NivelDimension::Na->aCategoria())->toBeNull()
        ->and(NivelDimension::Bajo->aCategoria())->toBe(CategoriaEns::Basica)
        ->and(NivelDimension::Medio->aCategoria())->toBe(CategoriaEns::Media)
        ->and(NivelDimension::Alto->aCategoria())->toBe(CategoriaEns::Alta);
});
