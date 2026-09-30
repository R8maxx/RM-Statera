<?php

declare(strict_types=1);

use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Enums\UnidadIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Domain\Metrica\SerieIndicador;
use App\Domain\Metrica\Situacion;
use App\Http\Resources\Metrica\SituacionIndicador;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| La situación de hoy
|--------------------------------------------------------------------------
|
| La franja de arriba de la ficha. Lo que se clava aquí es lo que sale mal en
| silencio: que la distancia de un porcentaje se escriba en puntos, que
| «faltan» no se diga de lo que sobra, y que la distancia se mida contra el
| objetivo que se aplicó y no contra el de hoy.
|
*/

beforeEach(function (): void {
    comoOrganizacion();
});

function situacionDe(Indicador $indicador): ?SituacionIndicador
{
    return app(Situacion::class)->de($indicador, app(SerieIndicador::class)->de($indicador));
}

function indicadorEnPorcentaje(float $objetivo): Indicador
{
    return Indicador::factory()->create([
        'unidad' => UnidadIndicador::Porcentaje->value,
        'sentido' => SentidoIndicador::MayorMejor->value,
        'objetivo' => $objetivo,
    ]);
}

it('no hay situación sin mediciones', function (): void {
    expect(situacionDe(Indicador::factory()->create()))->toBeNull();
});

it('escribe la distancia de un porcentaje en puntos, y la variación con signo', function (): void {
    $indicador = indicadorEnPorcentaje(60.0);
    Medicion::factory()->for($indicador)->enPeriodo(Carbon::parse('2026-01-15'))->con(46.0, 60.0)->create();
    Medicion::factory()->for($indicador)->enPeriodo(Carbon::parse('2026-04-15'))->con(63.0, 60.0)->create();

    $situacion = situacionDe($indicador);

    expect($situacion?->periodo)->toBe('T2 2026')
        ->and($situacion?->valorEscrito)->toBe('63 %')
        ->and($situacion?->alcanzado)->toBeTrue()
        ->and($situacion?->distancia)->toBe('3 pts por encima')
        ->and($situacion?->variacion)->toBe('+17 pts')
        ->and($situacion?->anterior)->toBe('De 46 % en T1 2026')
        ->and($situacion?->posicion)->toBe(0.63)
        ->and($situacion?->posicionObjetivo)->toBe(0.6);
});

it('dice lo que falta cuando más es mejor', function (): void {
    $indicador = indicadorEnPorcentaje(60.0);
    Medicion::factory()->for($indicador)->con(46.0, 60.0)->create();

    expect(situacionDe($indicador)?->distancia)->toBe('Faltan 14 pts')
        ->and(situacionDe($indicador)?->variacion)->toBeNull();
});

/** En «evidencias caducadas ≤ 0», estar en tres no es que falten tres. */
it('dice lo que sobra cuando menos es mejor, y sin barra', function (): void {
    $indicador = Indicador::factory()->calculado(CalculoIndicador::EvidenciasCaducadas)->create(['objetivo' => 0.0]);
    Medicion::factory()->for($indicador)->enPeriodo(Carbon::parse('2026-01-15'))->con(5.0, 0.0)->create();
    Medicion::factory()->for($indicador)->enPeriodo(Carbon::parse('2026-04-15'))->con(3.0, 0.0)->create();

    $situacion = situacionDe($indicador);

    expect($situacion?->distancia)->toBe('Sobran 3')
        ->and($situacion?->variacion)->toBe('−2')
        ->and($situacion?->posicion)->toBeNull();
});

it('mide la distancia contra el objetivo congelado en la fila, no contra el de hoy', function (): void {
    $indicador = indicadorEnPorcentaje(90.0);
    Medicion::factory()->for($indicador)->con(63.0, 60.0)->create();

    expect(situacionDe($indicador)?->distancia)->toBe('3 pts por encima');
});

it('no dice «faltan 0 pts» por debajo de lo que la unidad escribe', function (): void {
    $indicador = indicadorEnPorcentaje(60.0);
    Medicion::factory()->for($indicador)->con(59.8, 60.0)->create();

    expect(situacionDe($indicador)?->distancia)->toBe('Rozando el objetivo');
});
