<?php

declare(strict_types=1);

use App\Domain\Aviso\RejillaMes;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| La rejilla del mes
|--------------------------------------------------------------------------
|
| Aritmética de fechas: la clase de código donde un fallo se ve tarde y mal —un
| plazo en la casilla equivocada no lo nota nadie hasta que alguien se fía—.
|
*/

it('siempre tiene seis semanas, aunque el mes quepa en cinco', function (string $mes): void {
    expect(RejillaMes::de($mes)->dias)->toHaveCount(42);
})->with(['2026-02', '2026-09', '2027-01', '2024-02']);

it('empieza en lunes', function (): void {
    $primero = RejillaMes::de('2026-09')->dias[0];

    expect(Carbon::parse($primero['dia'])->dayOfWeekIso)->toBe(1);
});

/**
 * Un mes que empieza en domingo es el caso que rompe las rejillas escritas
 * deprisa: con semana de lunes a domingo, el día 1 cae en la última casilla de
 * la primera fila y hacen falta seis días de relleno por delante.
 */
it('coloca bien un mes que empieza en domingo', function (): void {
    // El 1 de noviembre de 2026 es domingo.
    $rejilla = RejillaMes::de('2026-11');

    expect($rejilla->dias[0]['dia'])->toBe('2026-10-26')
        ->and($rejilla->dias[6]['dia'])->toBe('2026-11-01')
        ->and($rejilla->dias[6]['delMes'])->toBeTrue()
        ->and($rejilla->dias[5]['delMes'])->toBeFalse();
});

it('marca como del mes exactamente los días del mes', function (): void {
    $delMes = array_filter(RejillaMes::de('2026-02')->dias, fn (array $dia): bool => $dia['delMes']);

    expect($delMes)->toHaveCount(28);
});

it('cuenta bien un febrero bisiesto', function (): void {
    $delMes = array_filter(RejillaMes::de('2024-02')->dias, fn (array $dia): bool => $dia['delMes']);

    expect($delMes)->toHaveCount(29);
});

it('cruza el año al navegar', function (): void {
    $enero = RejillaMes::de('2026-01');

    expect($enero->anterior)->toBe('2025-12')
        ->and($enero->siguiente)->toBe('2026-02');

    expect(RejillaMes::de('2026-12')->siguiente)->toBe('2027-01');
});

/**
 * `addMonth` sobre un 31 se va a marzo. Aquí la rejilla arranca siempre el día
 * 1, así que no puede pasar, pero el día que alguien pase otra fecha conviene
 * que siga sin pasar.
 */
it('no se salta un mes corto al navegar', function (): void {
    expect(RejillaMes::de('2026-01')->siguiente)->toBe('2026-02')
        ->and(RejillaMes::de('2026-03')->anterior)->toBe('2026-02');
});

it('los extremos son los de la rejilla, no los del mes', function (): void {
    $rejilla = RejillaMes::de('2026-09');

    expect($rejilla->primerDia)->toBe($rejilla->dias[0]['dia'])
        ->and($rejilla->ultimoDia)->toBe($rejilla->dias[41]['dia'])
        // Septiembre de 2026 empieza en martes: la rejilla arranca el 31 de agosto.
        ->and($rejilla->primerDia)->toBe('2026-08-31');
});

it('marca el día de hoy y sólo ese', function (): void {
    $hoy = Carbon::parse('2026-09-12');

    $marcados = array_filter(RejillaMes::de('2026-09', $hoy)->dias, fn (array $dia): bool => $dia['esHoy']);

    expect($marcados)->toHaveCount(1)
        ->and(reset($marcados)['dia'])->toBe('2026-09-12');
});

it('marca el fin de semana', function (): void {
    $rejilla = RejillaMes::de('2026-09');

    // Sábado y domingo son las dos últimas casillas de cada fila.
    expect($rejilla->dias[5]['finDeSemana'])->toBeTrue()
        ->and($rejilla->dias[6]['finDeSemana'])->toBeTrue()
        ->and($rejilla->dias[0]['finDeSemana'])->toBeFalse();
});

/**
 * Lo que llega por la query string no puede reventar la pantalla: un 500 en una
 * URL que la gente comparte es peor que enseñar otro mes.
 */
it('un mes que no se entiende es el de hoy', function (string $basura): void {
    $hoy = Carbon::parse('2026-09-12');

    expect(RejillaMes::de($basura, $hoy)->mes)->toBe('2026-09');
})->with(['', '2026', '2026-13', '2026-00', 'septiembre', '2026-9', "2026-09'; DROP TABLE tareas;--"]);

it('pone la inicial del mes en mayúscula', function (): void {
    expect(RejillaMes::de('2026-09')->etiqueta)->toBe('Septiembre de 2026');
});
