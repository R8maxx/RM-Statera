<?php

declare(strict_types=1);

use App\Domain\Metrica\Enums\CumplimientoIndicador;
use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Domain\Metrica\RegistrarMedicion;
use App\Domain\Metrica\SerieIndicador;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| La serie histórica
|--------------------------------------------------------------------------
|
| Lo que el § 4.14 pide y el panel nunca ha tenido. Aquí se clavan las tres
| reglas que la sostienen: un periodo se mide una vez, el objetivo se congela
| con la fila, y el orden es el del tiempo y no el del alta.
|
*/

beforeEach(function (): void {
    comoOrganizacion();
});

it('medir dos veces el mismo periodo corrige, no acumula', function (): void {
    $indicador = Indicador::factory()->create();
    [$inicio, $fin] = Periodicidad::Trimestral->periodoAnteriorA(Carbon::today());

    $registrar = app(RegistrarMedicion::class);
    $registrar->manual($indicador, $inicio, $fin, ['valor' => 10.0]);
    $registrar->manual($indicador, $inicio, $fin, ['valor' => 12.0]);

    expect(Medicion::query()->where('indicador_id', $indicador->id)->count())->toBe(1)
        ->and((float) $indicador->mediciones()->sole()->valor)->toBe(12.0);
});

/**
 * La regla que evita que subir el listón reescriba el pasado.
 *
 * Es el mismo razonamiento que congela la escala en `riesgo_valoraciones`: sin
 * ella, un trimestre que estuvo en objetivo pasaría a figurar como fallado en
 * cuanto alguien se pusiera más exigente, y nadie sabría por qué.
 */
it('congela el objetivo del indicador al sellar el periodo', function (): void {
    $indicador = Indicador::factory()->conObjetivo(90.0)->create();
    [$inicio, $fin] = Periodicidad::Trimestral->periodoAnteriorA(Carbon::today());

    $medicion = app(RegistrarMedicion::class)->manual($indicador, $inicio, $fin, ['valor' => 95.0]);

    expect((float) $medicion->objetivo)->toBe(90.0);

    $indicador->update(['objetivo' => 99.0]);

    expect((float) $medicion->refresh()->objetivo)->toBe(90.0)
        ->and($indicador->refresh()->cumplimiento($medicion))->toBe(CumplimientoIndicador::EnObjetivo);
});

it('corregir la cifra no mueve el objetivo que se aplicó', function (): void {
    $indicador = Indicador::factory()->conObjetivo(90.0)->create();
    [$inicio, $fin] = Periodicidad::Trimestral->periodoAnteriorA(Carbon::today());

    $registrar = app(RegistrarMedicion::class);
    $registrar->manual($indicador, $inicio, $fin, ['valor' => 95.0]);

    $indicador->update(['objetivo' => 99.0]);

    $corregida = $registrar->manual($indicador, $inicio, $fin, ['valor' => 96.0]);

    expect((float) $corregida->valor)->toBe(96.0)
        ->and((float) $corregida->objetivo)->toBe(90.0);
});

it('la serie va de lo antiguo a lo reciente aunque se apunte al revés', function (): void {
    $indicador = Indicador::factory()->conPeriodicidad(Periodicidad::Trimestral)->create();

    // Se crean desordenadas a propósito: si la serie se ordenara por `id` o por
    // `created_at`, la gráfica pintaría el tiempo al revés.
    Medicion::factory()->for($indicador)->enPeriodo(Carbon::parse('2026-07-15'))->con(30.0)->create();
    Medicion::factory()->for($indicador)->enPeriodo(Carbon::parse('2026-01-15'))->con(10.0)->create();
    Medicion::factory()->for($indicador)->enPeriodo(Carbon::parse('2026-04-15'))->con(20.0)->create();

    $serie = app(SerieIndicador::class)->de($indicador);

    expect(array_column($serie, 'valor'))->toBe([10.0, 20.0, 30.0])
        ->and(array_column($serie, 'etiqueta'))->toBe(['T1 2026', 'T2 2026', 'T3 2026']);
});

/**
 * «Sin objetivo» y «sin medir» no son «fuera de objetivo».
 *
 * Es el argumento de `EstadoControl::PorConfirmar`: colapsarlos dejaría el
 * cuadro de mando en rojo el día que se crea el primer indicador.
 */
it('distingue los cuatro veredictos', function (): void {
    $sinMedir = Indicador::factory()->create();
    expect($sinMedir->cumplimiento())->toBe(CumplimientoIndicador::SinMedir);

    $sinObjetivo = Indicador::factory()->create();
    Medicion::factory()->for($sinObjetivo)->con(5.0)->create();
    expect($sinObjetivo->load('ultimaMedicion')->cumplimiento())->toBe(CumplimientoIndicador::SinObjetivo);

    $dentro = Indicador::factory()->conObjetivo(90.0, SentidoIndicador::MayorMejor)->create();
    Medicion::factory()->for($dentro)->con(91.0, 90.0)->create();
    expect($dentro->load('ultimaMedicion')->cumplimiento())->toBe(CumplimientoIndicador::EnObjetivo);

    $fuera = Indicador::factory()->conObjetivo(90.0, SentidoIndicador::MayorMejor)->create();
    Medicion::factory()->for($fuera)->con(89.0, 90.0)->create();
    expect($fuera->load('ultimaMedicion')->cumplimiento())->toBe(CumplimientoIndicador::FueraDeObjetivo);
});

/**
 * El sentido no es un adorno: sin él, un cuadro de mando felicita por subir las
 * no conformidades vencidas.
 */
it('juzga al revés según el sentido, y el umbral cuenta como alcanzado', function (): void {
    expect(SentidoIndicador::MayorMejor->alcanza(90.0, 90.0))->toBeTrue()
        ->and(SentidoIndicador::MayorMejor->alcanza(89.9, 90.0))->toBeFalse()
        ->and(SentidoIndicador::MenorMejor->alcanza(5.0, 5.0))->toBeTrue()
        ->and(SentidoIndicador::MenorMejor->alcanza(5.1, 5.0))->toBeFalse();
});

it('sella cuándo se midió, que no es cuándo se guardó', function (): void {
    $indicador = Indicador::factory()->create();
    [$inicio, $fin] = Periodicidad::Trimestral->periodoAnteriorA(Carbon::today());

    $medicion = app(RegistrarMedicion::class)->manual($indicador, $inicio, $fin, [
        'valor' => 3.0,
        'medida_en' => $fin,
    ]);

    expect($medicion->medida_en->toDateString())->toBe($fin->toDateString())
        ->and($medicion->origen)->toBe(OrigenMedicion::Manual);
});
