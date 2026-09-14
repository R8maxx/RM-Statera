<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\RegistrarDependencia;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Riesgo\ImpactoIntrinseco;
use App\Domain\Riesgo\MetodologiaDeFabrica;
use App\Domain\Riesgo\Models\Amenaza;
use App\Domain\Riesgo\Models\Riesgo;

/*
|--------------------------------------------------------------------------
| El impacto sale de lo que valen los activos
|--------------------------------------------------------------------------
|
| Éste es el test que demuestra que el grafo de dependencias del inventario sirvió
| para algo. Una base de datos que alguien valoró «bajo» porque «total, es una base
| de datos» vale «alto» si sostiene un servicio esencial, y el riesgo que pesa sobre
| ella tiene que puntuarse contra «alto».
|
| Es justo lo que una hoja de cálculo no hace: valora el activo donde está escrito y
| deja sin proteger lo que hay debajo de lo importante.
|
*/

it('el impacto se deduce de la valoración EFECTIVA, no de la propia', function (): void {
    comoOrganizacion();

    // La base de datos se valoró baja en disponibilidad...
    $base = Activo::factory()->conNivel(Dimension::Disponibilidad, NivelDimension::Bajo)->create();

    // ...y sostiene un servicio esencial valorado alto.
    $servicio = Activo::factory()->conNivel(Dimension::Disponibilidad, NivelDimension::Alto)->create();
    app(RegistrarDependencia::class)->vincular($servicio, $base);

    $amenaza = Amenaza::factory()->sobre([Dimension::Disponibilidad])->create();
    $riesgo = Riesgo::factory()->conAmenaza($amenaza)->create();
    $riesgo->activos()->attach($base->id, ['organizacion_id' => $riesgo->organizacion_id]);

    $impacto = app(ImpactoIntrinseco::class);
    $metodologia = MetodologiaDeFabrica::metodologia();

    $riesgo->load('activos');

    // Con la valoración propia saldría 2 de 5; con la efectiva sale el techo.
    expect($impacto->sugerido($riesgo, $metodologia))->toBe(5)
        ->and($impacto->valoracionDe($riesgo)->nivelDe(Dimension::Disponibilidad))
        ->toBe(NivelDimension::Alto);
});

it('un riesgo sobre varios activos vale lo que el más expuesto, no la media', function (): void {
    comoOrganizacion();

    $trivial = Activo::factory()->valorado(NivelDimension::Bajo)->create();
    $critico = Activo::factory()->valorado(NivelDimension::Alto)->create();

    $riesgo = Riesgo::factory()->create();
    $riesgo->activos()->attach(
        [$trivial->id, $critico->id],
        ['organizacion_id' => $riesgo->organizacion_id],
    );
    $riesgo->load('activos');

    // Promediar escondería el activo crítico detrás del trivial.
    expect(app(ImpactoIntrinseco::class)->sugerido($riesgo, MetodologiaDeFabrica::metodologia()))
        ->toBe(5);
});

it('sólo cuentan las dimensiones sobre las que actúa la amenaza', function (): void {
    comoOrganizacion();

    // Alto en confidencialidad, nada en disponibilidad.
    $activo = Activo::factory()->conNivel(Dimension::Confidencialidad, NivelDimension::Alto)->create();

    // Un corte de luz no afecta a la confidencialidad de nada.
    $corte = Amenaza::factory()->sobre([Dimension::Disponibilidad])->create();
    $escucha = Amenaza::factory()->sobre([Dimension::Confidencialidad])->create();

    $metodologia = MetodologiaDeFabrica::metodologia();
    $impacto = app(ImpactoIntrinseco::class);

    $porCorte = Riesgo::factory()->conAmenaza($corte)->create();
    $porCorte->activos()->attach($activo->id, ['organizacion_id' => $porCorte->organizacion_id]);

    $porEscucha = Riesgo::factory()->conAmenaza($escucha)->create();
    $porEscucha->activos()->attach($activo->id, ['organizacion_id' => $porEscucha->organizacion_id]);

    expect($impacto->sugerido($porCorte->load('activos'), $metodologia))->toBe(1)
        ->and($impacto->sugerido($porEscucha->load('activos'), $metodologia))->toBe(5);
});

it('una amenaza libre cuenta las cinco dimensiones', function (): void {
    // Sin catálogo detrás no hay de dónde saber cuáles aplican, y exigir de más
    // nunca deja a nadie desprotegido.
    comoOrganizacion();

    $activo = Activo::factory()->conNivel(Dimension::Trazabilidad, NivelDimension::Alto)->create();

    $riesgo = Riesgo::factory()->conAmenazaLibre('El proveedor cierra')->create();
    $riesgo->activos()->attach($activo->id, ['organizacion_id' => $riesgo->organizacion_id]);

    expect(app(ImpactoIntrinseco::class)->sugerido($riesgo->load('activos'), MetodologiaDeFabrica::metodologia()))
        ->toBe(5);
});

it('un riesgo sin activos no sugiere impacto cero: no sugiere nada', function (): void {
    // Devolver el mínimo haría pasar por cálculo lo que es una ausencia de datos.
    // Mismo criterio que `na` frente a «bajo» en el Anexo I.
    comoOrganizacion();

    $riesgo = Riesgo::factory()->create();

    expect(app(ImpactoIntrinseco::class)->sugerido($riesgo->load('activos'), MetodologiaDeFabrica::metodologia()))
        ->toBeNull();
});

it('dice qué activo pone el techo, para que la cifra no parezca un error', function (): void {
    comoOrganizacion();

    $trivial = Activo::factory()->valorado(NivelDimension::Bajo)->create(['nombre' => 'Impresora']);
    $critico = Activo::factory()->valorado(NivelDimension::Alto)->create(['nombre' => 'Servicio esencial']);

    $amenaza = Amenaza::factory()->sobre([Dimension::Disponibilidad])->create();
    $riesgo = Riesgo::factory()->conAmenaza($amenaza)->create();
    $riesgo->activos()->attach([$trivial->id, $critico->id], ['organizacion_id' => $riesgo->organizacion_id]);

    $motivos = app(ImpactoIntrinseco::class)->motivos($riesgo->load('activos'));

    $nombres = array_map(static fn (array $motivo): string => $motivo['activo']->nombre, $motivos);

    expect($nombres)->toBe(['Servicio esencial']);
});

it('el desglose por dimensión acompaña a la cifra', function (): void {
    comoOrganizacion();

    $activo = Activo::factory()
        ->conNivel(Dimension::Disponibilidad, NivelDimension::Alto)
        ->conNivel(Dimension::Confidencialidad, NivelDimension::Bajo)
        ->create();

    $amenaza = Amenaza::factory()->sobre([Dimension::Disponibilidad, Dimension::Confidencialidad])->create();
    $riesgo = Riesgo::factory()->conAmenaza($amenaza)->create();
    $riesgo->activos()->attach($activo->id, ['organizacion_id' => $riesgo->organizacion_id]);

    $desglose = app(ImpactoIntrinseco::class)->porDimension($riesgo->load('activos'), MetodologiaDeFabrica::metodologia());

    // Es lo que permite decir POR QUÉ vale lo que vale.
    expect($desglose)->toBe(['D' => 5, 'C' => 2]);
});
