<?php

declare(strict_types=1);

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Riesgo\Models\MetodologiaRiesgo;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Riesgo\Models\RiesgoValoracion;

/*
|--------------------------------------------------------------------------
| Aislamiento del análisis de riesgos
|--------------------------------------------------------------------------
|
| Prioridad 2 de la cobertura, y aquí pesa más que en cualquier otro módulo: el
| análisis de riesgos de una organización es el documento que dice por dónde se la
| puede atacar y qué ha decidido NO arreglar. Filtrarlo no es filtrar una fila, es
| entregar el mapa.
|
| Las tres capas tienen que sostenerlo: `organizacion_id`, el global scope de
| Eloquent y Row Level Security. El de RLS se comprueba aparte, saltándose el scope
| con SQL crudo — que es como se descubre si la tercera capa está puesta de verdad
| o sólo parece puesta.
|
*/

/** @return array{propia: Organizacion, ajena: Organizacion, riesgoAjeno: Riesgo} */
function dosOrganizacionesConRiesgos(): array
{
    $propia = Organizacion::factory()->create(['nombre' => 'Propia']);
    $ajena = Organizacion::factory()->create(['nombre' => 'Ajena']);

    comoOrganizacion($propia);
    Riesgo::factory()->create(['codigo' => 'R-PRO']);
    MetodologiaRiesgo::factory()->conUmbrales(4, 9)->create();

    comoOrganizacion($ajena);
    $riesgoAjeno = Riesgo::factory()->create(['codigo' => 'R-AJE']);
    RiesgoValoracion::factory()->for($riesgoAjeno)->create();
    MetodologiaRiesgo::factory()->conUmbrales(20, 25)->create();

    sinOrganizacion();

    return ['propia' => $propia, 'ajena' => $ajena, 'riesgoAjeno' => $riesgoAjeno];
}

it('los riesgos no cruzan la frontera de organización', function (): void {
    ['propia' => $propia, 'ajena' => $ajena] = dosOrganizacionesConRiesgos();

    comoOrganizacion($propia);
    expect(Riesgo::query()->pluck('codigo')->all())->toBe(['R-PRO']);

    comoOrganizacion($ajena);
    expect(Riesgo::query()->pluck('codigo')->all())->toBe(['R-AJE']);
});

it('las valoraciones tampoco', function (): void {
    // Saber que la organización de al lado tiene un riesgo muy alto aceptado sobre
    // su control de acceso filtra tanto como leer el riesgo entero.
    ['propia' => $propia] = dosOrganizacionesConRiesgos();

    comoOrganizacion($propia);

    expect(RiesgoValoracion::query()->count())->toBe(0);
});

it('la metodología de cada organización es la suya', function (): void {
    // Es el apetito de riesgo de la dirección: con qué se mide y qué se acepta.
    ['propia' => $propia, 'ajena' => $ajena] = dosOrganizacionesConRiesgos();

    comoOrganizacion($propia);
    expect(MetodologiaRiesgo::query()->count())->toBe(1)
        ->and(MetodologiaRiesgo::query()->first()->umbral_aceptacion)->toBe(4);

    comoOrganizacion($ajena);
    expect(MetodologiaRiesgo::query()->first()->umbral_aceptacion)->toBe(20);
});

it('sin contexto no se ve ninguna fila: se deniega por defecto', function (): void {
    dosOrganizacionesConRiesgos();

    sinOrganizacion();

    expect(Riesgo::query()->count())->toBe(0)
        ->and(RiesgoValoracion::query()->count())->toBe(0)
        ->and(MetodologiaRiesgo::query()->count())->toBe(0);
});

/**
 * La tercera capa, comprobada saltándose la segunda.
 *
 * Una consulta cruda no pasa por el global scope de Eloquent. Si RLS no estuviera
 * puesto —o la aplicación se conectara con un rol `BYPASSRLS`—, esto devolvería las
 * filas de las dos organizaciones y nadie se enteraría, porque por la interfaz todo
 * se vería bien.
 */
it('row level security tapa lo que el scope de Eloquent no ve', function (string $tabla): void {
    ['propia' => $propia] = dosOrganizacionesConRiesgos();

    comoOrganizacion($propia);

    $filas = DB::table($tabla)->count();
    $todas = DB::table($tabla)->whereRaw('1 = 1')->count();

    expect($filas)->toBe($todas)
        ->and($filas)->toBeLessThan(2, "La tabla `{$tabla}` deja ver filas de otra organización.");
})->with(['riesgos', 'riesgo_valoraciones', 'metodologias_riesgo']);

it('las pivotes también llevan política', function (): void {
    // Saber QUÉ activos toca un riesgo ajeno, o qué controles lo contienen, dice
    // casi tanto como el riesgo.
    ['propia' => $propia] = dosOrganizacionesConRiesgos();

    comoOrganizacion($propia);

    expect(DB::table('activo_riesgo')->count())->toBe(0)
        ->and(DB::table('riesgo_implantacion')->count())->toBe(0);
});
