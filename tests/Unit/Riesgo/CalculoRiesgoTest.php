<?php

declare(strict_types=1);

use App\Domain\Riesgo\CalculoRiesgo;
use App\Domain\Riesgo\Enums\NivelRiesgo;
use App\Domain\Riesgo\EscalaRiesgo;
use App\Domain\Riesgo\Excepciones\EscalaInvalida;
use App\Domain\Riesgo\Metodologia;
use App\Domain\Riesgo\MetodologiaDeFabrica;

/*
|--------------------------------------------------------------------------
| El cálculo del riesgo
|--------------------------------------------------------------------------
|
| Es la pieza de este módulo equivalente al motor de categorización: función pura,
| sin base de datos, y con la matriz completa probada. Un fallo aquí no rompe
| nada visible — devuelve un número, y el número parece bueno.
|
| Lo que más se prueba no es el producto (que es una multiplicación) sino que el
| NIVEL y el UMBRAL nunca se contradigan. Esa es la decisión de diseño que
| distingue este cálculo de uno por quintiles, y un test es la única forma de que
| siga siendo cierta.
|
*/

/** Una metodología cuadrada de n×n con los umbrales que se le pasen. */
function metodologiaDe(int $lado, int $aceptacion, int $critico): Metodologia
{
    $escalones = [];

    for ($valor = 1; $valor <= $lado; $valor++) {
        $escalones[] = ['valor' => $valor, 'etiqueta' => "Nivel {$valor}"];
    }

    return new Metodologia(
        nombre: 'De prueba',
        referencia: null,
        probabilidad: EscalaRiesgo::desdeArray($escalones),
        impacto: EscalaRiesgo::desdeArray($escalones),
        umbralAceptacion: $aceptacion,
        umbralCritico: $critico,
        periodicidadRevisionMeses: 12,
    );
}

it('el riesgo es probabilidad por impacto, en las 25 casillas de la matriz de fábrica', function (): void {
    $calculo = new CalculoRiesgo;
    $metodologia = MetodologiaDeFabrica::metodologia();
    $casillas = 0;

    for ($probabilidad = 1; $probabilidad <= 5; $probabilidad++) {
        for ($impacto = 1; $impacto <= 5; $impacto++) {
            expect($calculo->producto($metodologia, $probabilidad, $impacto))
                ->toBe($probabilidad * $impacto);
            $casillas++;
        }
    }

    expect($casillas)->toBe(25)
        ->and($metodologia->riesgoMaximo())->toBe(25);
});

it('rechaza un valor que no está en su escala', function (): void {
    // Un impacto de 7 en una escala de 5 no es un riesgo grande: es un dato
    // corrupto, y multiplicarlo lo colaría en el histórico con buena pinta.
    $calculo = new CalculoRiesgo;
    $metodologia = MetodologiaDeFabrica::metodologia();

    expect(fn () => $calculo->producto($metodologia, 7, 3))
        ->toThrow(EscalaInvalida::class, 'La probabilidad vale 7');

    expect(fn () => $calculo->producto($metodologia, 3, 0))
        ->toThrow(EscalaInvalida::class, 'El impacto vale 0');
});

/**
 * El test que sostiene la decisión de diseño: las bandas salen de los umbrales,
 * así que el badge de la tabla y el indicador del panel no pueden discrepar.
 */
it('el nivel y el umbral dicen siempre lo mismo, para toda la escala', function (array $forma): void {
    [$lado, $aceptacion, $critico] = $forma;

    $calculo = new CalculoRiesgo;
    $metodologia = metodologiaDe($lado, $aceptacion, $critico);

    for ($riesgo = 1; $riesgo <= $metodologia->riesgoMaximo(); $riesgo++) {
        $nivel = $calculo->nivel($riesgo, $metodologia);

        expect($nivel->sobreUmbral())->toBe(
            $calculo->porEncimaDelUmbral($riesgo, $metodologia),
            "Con riesgo {$riesgo}, el nivel `{$nivel->value}` y el umbral no dicen lo mismo.",
        );

        expect($nivel->esCritico())->toBe(
            $calculo->esCritico($riesgo, $metodologia),
            "Con riesgo {$riesgo}, el nivel `{$nivel->value}` y el umbral crítico no dicen lo mismo.",
        );
    }
})->with([
    'la de fábrica' => [[5, 8, 15]],
    'umbrales pegados' => [[5, 8, 8]],
    'apetito bajísimo' => [[5, 2, 3]],
    'apetito altísimo' => [[5, 20, 25]],
    'escala corta' => [[3, 4, 7]],
    'escala larga' => [[10, 30, 70]],
]);

it('el nivel sube con el riesgo y nunca baja', function (): void {
    $calculo = new CalculoRiesgo;
    $metodologia = MetodologiaDeFabrica::metodologia();
    $anterior = 0;

    for ($riesgo = 1; $riesgo <= $metodologia->riesgoMaximo(); $riesgo++) {
        $peso = $calculo->nivel($riesgo, $metodologia)->peso();

        expect($peso)->toBeGreaterThanOrEqual($anterior, "El nivel baja al pasar a riesgo {$riesgo}.");

        $anterior = $peso;
    }
});

it('reparte la matriz de fábrica en las bandas anunciadas', function (): void {
    $calculo = new CalculoRiesgo;
    $metodologia = MetodologiaDeFabrica::metodologia();

    // Zona aceptable 1-7, tratable 8-14, inasumible 15-25.
    expect($calculo->nivel(1, $metodologia))->toBe(NivelRiesgo::MuyBajo)
        ->and($calculo->nivel(3, $metodologia))->toBe(NivelRiesgo::MuyBajo)
        ->and($calculo->nivel(4, $metodologia))->toBe(NivelRiesgo::Bajo)
        ->and($calculo->nivel(5, $metodologia))->toBe(NivelRiesgo::Bajo)
        ->and($calculo->nivel(6, $metodologia))->toBe(NivelRiesgo::Medio)
        ->and($calculo->nivel(7, $metodologia))->toBe(NivelRiesgo::Medio)
        ->and($calculo->nivel(8, $metodologia))->toBe(NivelRiesgo::Alto)
        ->and($calculo->nivel(14, $metodologia))->toBe(NivelRiesgo::Alto)
        ->and($calculo->nivel(15, $metodologia))->toBe(NivelRiesgo::MuyAlto)
        ->and($calculo->nivel(25, $metodologia))->toBe(NivelRiesgo::MuyAlto);
});

it('las bandas cubren la escala entera sin huecos ni solapes', function (array $forma): void {
    [$lado, $aceptacion, $critico] = $forma;

    $calculo = new CalculoRiesgo;
    $metodologia = metodologiaDe($lado, $aceptacion, $critico);
    $bandas = $calculo->bandas($metodologia);

    $esperado = 1;

    foreach ($bandas as $nivel => $banda) {
        expect($banda['desde'])->toBe($esperado, "La banda `{$nivel}` deja un hueco o se solapa.");
        $esperado = $banda['hasta'] + 1;
    }

    expect($esperado - 1)->toBe($metodologia->riesgoMaximo(), 'Las bandas no llegan al techo de la matriz.');
})->with([
    'la de fábrica' => [[5, 8, 15]],
    'umbrales pegados' => [[5, 8, 8]],
    'apetito bajísimo' => [[5, 2, 3]],
    'escala larga' => [[10, 30, 70]],
]);

it('cada banda contiene exactamente los riesgos de su nivel', function (): void {
    $calculo = new CalculoRiesgo;
    $metodologia = MetodologiaDeFabrica::metodologia();

    foreach ($calculo->bandas($metodologia) as $nivel => $banda) {
        for ($riesgo = $banda['desde']; $riesgo <= $banda['hasta']; $riesgo++) {
            expect($calculo->nivel($riesgo, $metodologia)->value)->toBe(
                $nivel,
                "El riesgo {$riesgo} debería caer en la banda `{$nivel}`.",
            );
        }
    }
});

it('con un apetito muy bajo hay bandas vacías, y no salen en la leyenda', function (): void {
    // La organización ha decidido que casi nada le resulta aceptable. La
    // herramienta no le inventa grados que no ha pedido.
    $calculo = new CalculoRiesgo;
    $metodologia = metodologiaDe(5, 2, 3);

    $bandas = $calculo->bandas($metodologia);

    expect($bandas)->toHaveKey(NivelRiesgo::MuyBajo->value)
        ->and($bandas)->not->toHaveKey(NivelRiesgo::Bajo->value)
        ->and($bandas)->not->toHaveKey(NivelRiesgo::Medio->value)
        ->and($calculo->nivel(1, $metodologia))->toBe(NivelRiesgo::MuyBajo)
        ->and($calculo->nivel(2, $metodologia))->toBe(NivelRiesgo::Alto);
});
