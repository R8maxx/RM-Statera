<?php

declare(strict_types=1);

use App\Domain\Metrica\Enums\CumplimientoIndicador;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;

/*
|--------------------------------------------------------------------------
| La regla escrita dos veces
|--------------------------------------------------------------------------
|
| `Indicador::scopeFueraDeObjetivo()` la aplica PostgreSQL sobre miles de filas
| y `SentidoIndicador::alcanza()` decide el badge de una. No hay forma de tener
| una sola, así que hay que tener el test: es el mismo tratamiento que
| `ValoracionEfectiva`, que tiene dos entradas y un test que fija que coinciden.
|
| Sin esto, la tabla enseñaría una cifra y la ficha otra, y a partir de ahí nadie
| se fía de ninguna de las dos.
|
*/

beforeEach(function (): void {
    comoOrganizacion();
});

it('el scope y el veredicto dicen lo mismo en toda la matriz', function (SentidoIndicador $sentido, float $valor, bool $esperadoFuera): void {
    $indicador = Indicador::factory()->conObjetivo(50.0, $sentido)->create();
    Medicion::factory()->for($indicador)->con($valor, 50.0)->create();

    $porScope = Indicador::query()->fueraDeObjetivo()->whereKey($indicador->id)->exists();
    $porVeredicto = $indicador->load('ultimaMedicion')->cumplimiento() === CumplimientoIndicador::FueraDeObjetivo;

    expect($porScope)->toBe($esperadoFuera, 'El scope no dice lo mismo que el veredicto.')
        ->and($porVeredicto)->toBe($esperadoFuera, 'El veredicto no dice lo mismo que el scope.');
})->with([
    'mayor mejor, por encima' => [SentidoIndicador::MayorMejor, 51.0, false],
    'mayor mejor, justo en el umbral' => [SentidoIndicador::MayorMejor, 50.0, false],
    'mayor mejor, por debajo' => [SentidoIndicador::MayorMejor, 49.0, true],
    'menor mejor, por debajo' => [SentidoIndicador::MenorMejor, 49.0, false],
    'menor mejor, justo en el umbral' => [SentidoIndicador::MenorMejor, 50.0, false],
    'menor mejor, por encima' => [SentidoIndicador::MenorMejor, 51.0, true],
]);

/**
 * Un indicador sin objetivo no está fuera de objetivo, y uno sin medir tampoco.
 * Si el scope los arrastrara, la alerta del panel contaría como incumplimiento
 * lo que sólo es una declaración a medias.
 */
it('no arrastra a los que no tienen objetivo ni a los que no se han medido', function (): void {
    $sinMedir = Indicador::factory()->conObjetivo(50.0)->create();

    $sinObjetivo = Indicador::factory()->create();
    Medicion::factory()->for($sinObjetivo)->con(1.0)->create();

    expect(Indicador::query()->fueraDeObjetivo()->count())->toBe(0)
        ->and($sinMedir->cumplimiento())->toBe(CumplimientoIndicador::SinMedir)
        ->and($sinObjetivo->load('ultimaMedicion')->cumplimiento())->toBe(CumplimientoIndicador::SinObjetivo);
});

/**
 * El scope mira la ÚLTIMA medición, no cualquiera. Con un `whereHas` ingenuo,
 * un indicador que falló hace dos años y lleva ocho trimestres en objetivo
 * seguiría contando como fuera.
 */
it('mira la última medición y no el historial entero', function (): void {
    $indicador = Indicador::factory()->conObjetivo(50.0)->create();

    Medicion::factory()->for($indicador)->enPeriodo(now()->subYear())->con(10.0, 50.0)->create();
    Medicion::factory()->for($indicador)->enPeriodo(now()->subMonths(4))->con(80.0, 50.0)->create();

    expect(Indicador::query()->fueraDeObjetivo()->count())->toBe(0)
        ->and($indicador->load('ultimaMedicion')->cumplimiento())->toBe(CumplimientoIndicador::EnObjetivo);
});
