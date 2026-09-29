<?php

declare(strict_types=1);

use App\Domain\Obligacion\CicloCompromiso;
use App\Domain\Obligacion\Enums\TramoCiclo;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\RegistrarCumplimiento;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El ciclo de un compromiso
|--------------------------------------------------------------------------
|
| La barra de la ficha dice dónde estuvo cubierto y dónde hubo un hueco. Lo que
| no puede hacer es contradecir a la tabla: el final de lo cubierto es siempre
| `proximaFecha()`, y un hueco es exactamente lo que `vencidos()` contaría.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

/**
 * @return list<array{0: string, 1: string, 2: string}>
 */
function tramosDe(Compromiso $compromiso, string $hoy): array
{
    $compromiso->load('cumplimientos');

    return array_map(
        fn (array $tramo): array => [$tramo['tipo']->value, $tramo['desde'], $tramo['hasta']],
        (new CicloCompromiso)($compromiso, Carbon::parse($hoy)),
    );
}

it('sin cumplimientos y en plazo, sólo hay primer plazo', function (): void {
    $compromiso = Compromiso::factory()->cada(12)->create(['computa_desde' => '2025-09-23']);

    expect(tramosDe($compromiso, '2026-03-01'))->toBe([
        ['plazo', '2025-09-23', '2026-09-23'],
    ]);
});

it('sin cumplimientos y pasado el plazo, el hueco llega hasta hoy', function (): void {
    $compromiso = Compromiso::factory()->cada(12)->create(['computa_desde' => '2024-01-10']);

    expect(tramosDe($compromiso, '2025-03-10'))->toBe([
        ['plazo', '2024-01-10', '2025-01-10'],
        ['vencido', '2025-01-10', '2025-03-10'],
    ]);
});

it('cumplir antes de que venza el primer plazo lo corta sin dejar hueco', function (): void {
    $compromiso = Compromiso::factory()->cada(12)->create(['computa_desde' => '2025-09-23']);
    app(RegistrarCumplimiento::class)($compromiso, Carbon::parse('2026-06-23'));

    expect(tramosDe($compromiso, '2026-09-29'))->toBe([
        ['plazo', '2025-09-23', '2026-06-23'],
        ['cubierto', '2026-06-23', '2027-06-23'],
    ]);
});

it('un cumplimiento tardío deja un hueco cerrado, no uno abierto', function (): void {
    $compromiso = Compromiso::factory()->cada(12)->create(['computa_desde' => '2024-01-01']);
    app(RegistrarCumplimiento::class)($compromiso, Carbon::parse('2025-04-01'));

    expect(tramosDe($compromiso, '2025-06-01'))->toBe([
        ['plazo', '2024-01-01', '2025-01-01'],
        ['sin_cubrir', '2025-01-01', '2025-04-01'],
        ['cubierto', '2025-04-01', '2026-04-01'],
    ]);
});

it('dos cumplimientos que se solapan no pintan dos veces el mismo día', function (): void {
    $compromiso = Compromiso::factory()->cada(12)->create(['computa_desde' => '2023-06-01']);
    app(RegistrarCumplimiento::class)($compromiso, Carbon::parse('2024-05-01'));
    app(RegistrarCumplimiento::class)($compromiso->fresh(), Carbon::parse('2025-03-01'));

    expect(tramosDe($compromiso->fresh(), '2025-06-01'))->toBe([
        ['plazo', '2023-06-01', '2024-05-01'],
        ['cubierto', '2024-05-01', '2025-05-01'],
        ['cubierto', '2025-05-01', '2026-03-01'],
    ]);
});

/**
 * La barra y la tabla no pueden discrepar: si una dice «al día» y la otra
 * pinta un hueco abierto, una de las dos miente.
 */
it('lo cubierto acaba en la próxima fecha, y sólo hay hueco abierto si está vencido', function (string $hoy): void {
    Carbon::setTestNow($hoy);

    $compromiso = Compromiso::factory()->cada(6)->create(['computa_desde' => '2024-01-31']);
    app(RegistrarCumplimiento::class)($compromiso, Carbon::parse('2024-09-15'));
    app(RegistrarCumplimiento::class)($compromiso->fresh(), Carbon::parse('2025-02-01'), cubreHasta: Carbon::parse('2025-06-30'));

    $compromiso = $compromiso->fresh()?->load('cumplimientos');
    $tramos = (new CicloCompromiso)($compromiso);
    $abierto = collect($tramos)->firstWhere('tipo', TramoCiclo::Vencido);
    $cubiertos = collect($tramos)->reject(fn (array $tramo): bool => $tramo['tipo'] === TramoCiclo::Vencido);

    expect($cubiertos->last()['hasta'])->toBe($compromiso->proximaFecha()->toDateString())
        ->and($abierto !== null)->toBe($compromiso->vencido());
})->with(['2025-05-01', '2025-09-01']);

it('una obligación retirada no acumula hueco después de retirarse', function (): void {
    $compromiso = Compromiso::factory()->cada(12)->create(['computa_desde' => '2023-01-01', 'activo' => false]);

    expect(tramosDe($compromiso, '2025-06-01'))->toBe([
        ['plazo', '2023-01-01', '2024-01-01'],
    ]);
});

it('la ficha manda el ciclo y los tipos de referencia con su columna', function (): void {
    $compromiso = Compromiso::factory()->cada(12)->create(['computa_desde' => Carbon::today()->subMonths(3)]);

    $this->actingAs($this->usuario)
        ->get("/obligaciones/{$compromiso->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('obligaciones/Ficha')
            ->has('ciclo', 1)
            ->where('ciclo.0.tipo.valor', 'plazo')
            ->where('hoy', Carbon::today()->toDateString())
            ->has('referencias', 4)
            ->where('referencias.0.campo', 'auditoria_id'));
});
