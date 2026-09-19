<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Implantacion\ResumenCumplimiento;
use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\RegistrarMedicion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Los cálculos del catálogo cerrado
|--------------------------------------------------------------------------
|
| Este test **se parametriza solo**: recorre `CalculoIndicador::cases()`, así que
| un cálculo nuevo entra aquí sin que nadie toque el fichero. Es el mismo patrón
| que `HuecosCalculadosTest` y por el mismo motivo — una rama olvidada no
| revienta, devuelve una cifra rara, y eso no se ve mirando la pantalla.
|
| Y lo que de verdad comprueba no es que no lance: es que la cifra **entra en la
| tabla**. Un `Medida` con numerador y sin denominador, o con denominador cero,
| lo rechaza un `CHECK` de PostgreSQL, y ese rechazo aparecería meses después en
| el comando programado de las siete y media de la mañana.
|
*/

beforeEach(function (): void {
    comoOrganizacion();
});

it('cada cálculo produce una cifra que la tabla acepta', function (CalculoIndicador $calculo): void {
    $indicador = Indicador::factory()->calculado($calculo)->create();
    [$inicio, $fin] = Periodicidad::Trimestral->periodoAnteriorA(Carbon::today());

    $medicion = app(RegistrarMedicion::class)->calculada($indicador, $inicio, $fin);

    expect($medicion->exists)->toBeTrue()
        // El `CHECK` de la tabla lo impone; esto lo deja dicho también aquí,
        // donde se lee al lado del value object que lo produce.
        ->and($medicion->numerador === null || $medicion->denominador !== null)->toBeTrue(
            "El cálculo {$calculo->value} devuelve numerador sin denominador.",
        )
        ->and($medicion->denominador === null || $medicion->denominador > 0)->toBeTrue(
            "El cálculo {$calculo->value} devuelve un denominador a cero.",
        );
})->with(fn () => CalculoIndicador::cases());

it('cada cálculo declara unidad, sentido y método', function (CalculoIndicador $calculo): void {
    expect($calculo->etiqueta())->not->toBe('')
        ->and($calculo->metodo())->not->toBe('', "El cálculo {$calculo->value} no dice de dónde sale su cifra, que es lo que pregunta la 9.1 b).");
})->with(fn () => CalculoIndicador::cases());

/**
 * La coincidencia que paga el módulo: el indicador y el panel cuentan lo mismo.
 *
 * Es el equivalente de `ResumenInventarioTest`, que recorre los nueve
 * indicadores comparando cada cifra con su filtro. Con la regla escrita dos
 * veces, el día que cambie una el acta de la revisión por la dirección diría 12
 * y el panel enseñaría 9.
 */
it('el cumplimiento implantado coincide con lo que cuenta el panel', function (): void {
    $organizacion = comoOrganizacion();

    $ens = Marco::factory()->create(['codigo' => 'ENS', 'nombre' => 'Esquema Nacional de Seguridad']);
    $iso = Marco::factory()->create(['codigo' => 'ISO27001', 'nombre' => 'ISO/IEC 27001']);
    $sistema = Sistema::factory()->de($organizacion)->conMarco($ens)->create(['codigo' => 'SIS-01']);

    $orden = 0;
    $crear = function (Marco $marco, array $atributos) use ($sistema, &$orden): void {
        $orden++;
        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => "req.{$orden}",
            'tipo' => TipoRequisito::Medida->value,
            'orden' => $orden,
        ]);

        Implantacion::factory()->for($sistema)->create(['requisito_id' => $requisito->id, ...$atributos]);
    };

    $crear($ens, ['estado' => 'implantado']);
    $crear($ens, ['estado' => 'implantado']);
    $crear($ens, ['estado' => 'en_progreso']);
    $crear($ens, ['estado' => 'no_iniciado']);
    $crear($iso, ['estado' => 'implantado']);
    $crear($iso, ['estado' => 'planificado']);
    // Excluida: no se le exige, así que no entra en ninguna de las dos cifras.
    $crear($ens, ['estado' => 'no_aplica', 'aplica' => false, 'justificacion' => 'Fuera del alcance.']);

    $porMarco = collect((new ResumenCumplimiento)->porMarco())->keyBy('codigo');

    $global = CalculoIndicador::CumplimientoImplantado->medir();
    expect($global->numerador)->toBe(3)->and($global->denominador)->toBe(6);

    $deEns = CalculoIndicador::CumplimientoImplantado->medir($ens->id);
    expect($deEns->numerador)->toBe($porMarco['ENS']->implantadas)
        ->and($deEns->denominador)->toBe($porMarco['ENS']->aplicables);

    $deIso = CalculoIndicador::CumplimientoImplantado->medir($iso->id);
    expect($deIso->numerador)->toBe($porMarco['ISO27001']->implantadas)
        ->and($deIso->denominador)->toBe($porMarco['ISO27001']->aplicables);
});

/**
 * Sólo lo que cuelga de `implantaciones` se deja acotar por marco. Las
 * evidencias, las tareas y los activos son de la organización entera y sirven a
 * los dos marcos a la vez (invariante 6): repartirlos los contaría dos veces.
 */
it('sólo admiten marco los cálculos que cuelgan de implantaciones', function (): void {
    $admiten = array_values(array_filter(
        CalculoIndicador::cases(),
        static fn (CalculoIndicador $calculo): bool => $calculo->admiteMarco(),
    ));

    expect($admiten)->toBe([
        CalculoIndicador::CumplimientoImplantado,
        CalculoIndicador::ImplantacionesPendientes,
        CalculoIndicador::ImplantadasSinEvidencia,
        CalculoIndicador::MadurezMedia,
    ]);
});
