<?php

declare(strict_types=1);

use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| El cierre de periodo programado
|--------------------------------------------------------------------------
|
| Aquí es donde esto se rompe si se escribe deprisa. Un comando programado no
| tiene petición ni usuario, así que sin contexto el scope no devuelve nada y RLS
| deniega por defecto: **no falla, no ve nada**, y una serie que se queda sin
| puntos es indistinguible de una organización que no mide.
|
*/

it('mide los indicadores calculados de cada organización', function (): void {
    $una = comoOrganizacion();
    $otra = Organizacion::factory()->create();

    Indicador::factory()->calculado(CalculoIndicador::TareasVencidas)->create(['codigo' => 'IND-A']);

    app(ContextoOrganizacion::class)->paraOrganizacion(
        $otra,
        fn () => Indicador::factory()->calculado(CalculoIndicador::EvidenciasCaducadas)->create(['codigo' => 'IND-B']),
    );

    sinOrganizacion();

    $this->artisan('indicadores:medir')->assertSuccessful();

    // Cada una ve sólo la suya: si el comando cruzara la frontera, la segunda
    // consulta devolvería las dos.
    app(ContextoOrganizacion::class)->establecer($una);
    expect(Medicion::query()->count())->toBe(1);

    app(ContextoOrganizacion::class)->paraOrganizacion($otra, function (): void {
        expect(Medicion::query()->count())->toBe(1);
    });
});

/** Volver a correrlo no acumula: es idempotente, como el importador del catálogo. */
it('correrlo dos veces no añade una segunda fila del mismo periodo', function (): void {
    comoOrganizacion();
    Indicador::factory()->calculado(CalculoIndicador::TareasVencidas)->create();

    sinOrganizacion();
    $this->artisan('indicadores:medir')->assertSuccessful();
    $this->artisan('indicadores:medir')->assertSuccessful();

    comoOrganizacion(Organizacion::query()->orderBy('id')->firstOrFail());

    expect(Medicion::query()->count())->toBe(1);
});

/** Un indicador manual no se mide solo: sellar un cero sería inventarse la cifra. */
it('no toca los indicadores manuales ni los retirados', function (): void {
    comoOrganizacion();

    Indicador::factory()->create(['codigo' => 'IND-MANUAL']);
    Indicador::factory()->calculado(CalculoIndicador::TareasVencidas)->retirado()->create(['codigo' => 'IND-RETIRADO']);

    sinOrganizacion();
    $this->artisan('indicadores:medir')->assertSuccessful();

    comoOrganizacion(Organizacion::query()->orderBy('id')->firstOrFail());

    expect(Medicion::query()->count())->toBe(0);
});

/** El `--dry-run` enseña lo que saldría y no escribe. */
it('en simulación no sella nada', function (): void {
    comoOrganizacion();
    Indicador::factory()->calculado(CalculoIndicador::TareasVencidas)->create();

    sinOrganizacion();
    $this->artisan('indicadores:medir --dry-run')->assertSuccessful();

    comoOrganizacion(Organizacion::query()->orderBy('id')->firstOrFail());

    expect(Medicion::query()->count())->toBe(0);
});

/**
 * Mide el periodo **cerrado**, no el que está en curso: una cifra a medias
 * habría que corregirla al día siguiente.
 */
it('sella el periodo anterior y no el que corre', function (): void {
    comoOrganizacion();
    $indicador = Indicador::factory()
        ->calculado(CalculoIndicador::TareasVencidas)
        ->conPeriodicidad(Periodicidad::Mensual)
        ->create();

    sinOrganizacion();
    $this->artisan('indicadores:medir')->assertSuccessful();

    comoOrganizacion(Organizacion::query()->orderBy('id')->firstOrFail());

    [$esperado] = Periodicidad::Mensual->periodoAnteriorA(Carbon::today());

    expect(Medicion::query()->sole()->periodo_inicio->toDateString())->toBe($esperado->toDateString())
        ->and($indicador->refresh()->tienePeriodoSinMedir())->toBeFalse();
});
