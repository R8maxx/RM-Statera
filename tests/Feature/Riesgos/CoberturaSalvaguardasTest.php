<?php

declare(strict_types=1);

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Enums\NivelMadurez;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Riesgo\CoberturaSalvaguardas;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Riesgo\RegistroRiesgos;
use App\Domain\Riesgo\ValorarRiesgo;
use App\Domain\Riesgo\VincularRiesgo;

/*
|--------------------------------------------------------------------------
| El residual se declara; la cobertura se calcula
|--------------------------------------------------------------------------
|
| La decisión central del módulo, y la que hay que blindar porque se «arregla»
| sola: alguien verá que hay estado y madurez de las salvaguardas y pensará que el
| residual se puede deducir. No se puede. No existe ninguna función publicada de
| (estado, madurez) a riesgo residual, así que cualquiera que inventáramos sería una
| opinión de la herramienta disfrazada de cálculo — y ISO 6.1.3 f) exige que lo
| apruebe el propietario del riesgo, que no puede aprobar lo que dedujo la máquina.
|
| Lo que la herramienta sí hace es SEÑALAR LA CONTRADICCIÓN, igual que
| `Activo::esperaBorradoSeguro()` señala un equipo retirado sin constancia de
| borrado.
|
*/

function riesgoConSalvaguardas(array $estados, array $residual = ['probabilidad_residual' => 1, 'impacto_residual' => 1]): Riesgo
{
    $riesgo = Riesgo::factory()->create();
    $vinculos = app(VincularRiesgo::class);

    foreach ($estados as $estado) {
        $vinculos->salvaguarda($riesgo, Implantacion::factory()->create(['estado' => $estado->value]));
    }

    app(ValorarRiesgo::class)($riesgo->fresh(), ['probabilidad' => 3, 'impacto' => 3, ...$residual]);

    return $riesgo->fresh();
}

it('el residual declarado no se sobrescribe nunca', function (): void {
    comoOrganizacion();

    // Todas las salvaguardas implantadas, y aun así manda lo que dijo la persona.
    $riesgo = riesgoConSalvaguardas(
        [EstadoImplantacion::Implantado, EstadoImplantacion::Implantado],
        ['probabilidad_residual' => 3, 'impacto_residual' => 2],
    );

    expect($riesgo->valoracionVigente->riesgo_residual)->toBe(6);
});

it('cuenta el estado de las salvaguardas, con su denominador', function (): void {
    comoOrganizacion();

    $riesgo = riesgoConSalvaguardas([
        EstadoImplantacion::Implantado,
        EstadoImplantacion::EnProgreso,
        EstadoImplantacion::NoIniciado,
        EstadoImplantacion::NoIniciado,
    ]);

    $cobertura = app(CoberturaSalvaguardas::class)->de($riesgo->load('salvaguardas'));

    expect($cobertura['total'])->toBe(4)
        ->and($cobertura['implantadas'])->toBe(1)
        ->and($cobertura['enProgreso'])->toBe(1)
        ->and($cobertura['sinEmpezar'])->toBe(2);
});

it('la madurez media viaja con el número de evaluadas', function (): void {
    // Una media de 3,5 sobre dos salvaguardas de diez no dice lo mismo que sobre
    // las diez, y sin el denominador las dos se leen igual.
    comoOrganizacion();

    $riesgo = Riesgo::factory()->create();
    $vinculos = app(VincularRiesgo::class);

    $vinculos->salvaguarda($riesgo, Implantacion::factory()->create(['nivel_madurez' => NivelMadurez::L2->value]));
    $vinculos->salvaguarda($riesgo, Implantacion::factory()->create(['nivel_madurez' => NivelMadurez::L4->value]));
    $vinculos->salvaguarda($riesgo, Implantacion::factory()->create(['nivel_madurez' => null]));

    $cobertura = app(CoberturaSalvaguardas::class)->de($riesgo->fresh()->load('salvaguardas'));

    expect($cobertura['madurezMedia'])->toBe(3.0)
        ->and($cobertura['madurezEvaluadas'])->toBe(2)
        ->and($cobertura['total'])->toBe(3);
});

it('sin ninguna salvaguarda evaluada, la media es nula y no cero', function (): void {
    comoOrganizacion();

    $riesgo = Riesgo::factory()->create();
    app(VincularRiesgo::class)->salvaguarda($riesgo, Implantacion::factory()->create(['nivel_madurez' => null]));

    $cobertura = app(CoberturaSalvaguardas::class)->de($riesgo->fresh()->load('salvaguardas'));

    expect($cobertura['madurezMedia'])->toBeNull();
});

it('señala el residual sin respaldo: se declara la rebaja y nada está implantado', function (): void {
    comoOrganizacion();

    $riesgo = riesgoConSalvaguardas([EstadoImplantacion::NoIniciado, EstadoImplantacion::EnProgreso]);

    expect(app(CoberturaSalvaguardas::class)->sinRespaldo($riesgo->load('salvaguardas', 'valoracionVigente')))
        ->toBeTrue()
        ->and($riesgo->residualSinRespaldo())->toBeTrue();
});

it('con una salvaguarda implantada ya hay respaldo', function (): void {
    comoOrganizacion();

    $riesgo = riesgoConSalvaguardas([EstadoImplantacion::NoIniciado, EstadoImplantacion::Implantado]);

    expect($riesgo->residualSinRespaldo())->toBeFalse();
});

it('no se declara rebaja, no hay contradicción que señalar', function (): void {
    // Un residual igual al intrínseco es una respuesta legítima: significa que no
    // se ha conseguido bajar nada.
    comoOrganizacion();

    $riesgo = riesgoConSalvaguardas(
        [EstadoImplantacion::NoIniciado],
        ['probabilidad_residual' => 3, 'impacto_residual' => 3],
    );

    expect($riesgo->residualSinRespaldo())->toBeFalse();
});

it('un riesgo sin valorar no es residual sin respaldo', function (): void {
    comoOrganizacion();

    expect(Riesgo::factory()->create()->residualSinRespaldo())->toBeFalse();
});

/**
 * La regla que copia de `ResumenInventarioTest`: cada indicador cuenta con el mismo
 * scope que usa su filtro. Con la condición escrita dos veces, el día que cambie una
 * el panel dirá 12 y la lista enseñará 9.
 */
it('el indicador y el método de una fila dicen lo mismo', function (): void {
    comoOrganizacion();

    riesgoConSalvaguardas([EstadoImplantacion::NoIniciado]);
    riesgoConSalvaguardas([EstadoImplantacion::Implantado]);
    Riesgo::factory()->create();

    $porFila = Riesgo::query()->get()->filter(
        static fn (Riesgo $riesgo): bool => $riesgo->load('salvaguardas', 'valoracionVigente')->residualSinRespaldo(),
    )->count();

    $indicadores = collect(app(RegistroRiesgos::class)->alertas())->keyBy('clave');

    expect($indicadores['residual_sin_respaldo']->valor)->toBe($porFila)
        ->and($porFila)->toBe(1);
});

it('cada indicador apunta al filtro que lo aísla', function (): void {
    comoOrganizacion();

    $registro = app(RegistroRiesgos::class);

    foreach ([...$registro->alertas(), ...$registro->pendientes()] as $indicador) {
        expect($indicador->filtro)->toBe("filter[{$indicador->clave}]=1")
            ->and($indicador->base)->toBe('/riesgos');
    }
});

it('las alertas son lo que va mal y los pendientes lo que falta por hacer', function (): void {
    // El reparto no es cosmético: mezclarlos hace que un riesgo sin medir pese lo
    // mismo que uno por encima del umbral que la organización declaró inasumible.
    comoOrganizacion();

    $registro = app(RegistroRiesgos::class);

    expect(array_column($registro->alertas(), 'clave'))
        ->toBe(['sobre_umbral', 'residual_sin_respaldo', 'revision_vencida'])
        ->and(array_column($registro->pendientes(), 'clave'))
        ->toBe(['sin_valorar', 'sin_aceptar']);

    // Y todas las alertas gastan el rojo; ninguna pendiente lo hace.
    foreach ($registro->alertas() as $alerta) {
        expect($alerta->tono)->toBe('caducada');
    }

    foreach ($registro->pendientes() as $pendiente) {
        expect($pendiente->tono)->not->toBe('caducada');
    }
});

it('la instantánea guarda las salvaguardas tal como estaban', function (): void {
    // Sin ella, la fila de marzo diría en octubre que se apoyaba en un control
    // implantado que en marzo estaba sin empezar.
    comoOrganizacion();

    $riesgo = Riesgo::factory()->create();
    $implantacion = Implantacion::factory()->create(['estado' => EstadoImplantacion::NoIniciado->value]);
    app(VincularRiesgo::class)->salvaguarda($riesgo, $implantacion);

    $valoracion = app(ValorarRiesgo::class)($riesgo->fresh(), ['probabilidad' => 3, 'impacto' => 3]);

    // El control avanza después de valorar.
    $implantacion->update(['estado' => EstadoImplantacion::Implantado->value]);

    expect($valoracion->fresh()->salvaguardas[0]['estado'])
        ->toBe(EstadoImplantacion::NoIniciado->value);
});
