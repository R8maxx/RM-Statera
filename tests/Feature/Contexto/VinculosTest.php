<?php

declare(strict_types=1);

use App\Domain\Contexto\AbrirTareaDeCuestion;
use App\Domain\Contexto\Enums\NaturalezaRequisito;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\Models\RequisitoInteresado;
use App\Domain\Contexto\VincularImplantacionARequisito;
use App\Domain\Contexto\VincularRiesgoACuestion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;

/**
 * Los tres vínculos del módulo, que son lo que impide que el DAFO sea un papel.
 *
 * Una amenaza que abre un riesgo —ISO 6.1.1 pide que la apreciación de riesgos se
 * haga considerando las cuestiones del 4.1—, una debilidad que genera trabajo, y
 * un requisito legal atado a la medida que lo cubre, que es el que la Declaración
 * de Aplicabilidad puede imprimir como justificación de inclusión.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('una cuestión y un riesgo se vinculan en los dos sentidos', function (): void {
    $cuestion = CuestionContexto::factory()->deTipo(TipoCuestion::Amenaza)->create();
    $riesgo = Riesgo::factory()->create();

    app(VincularRiesgoACuestion::class)->vincular($cuestion, $riesgo, $this->usuario);

    expect($cuestion->riesgos()->count())->toBe(1)
        ->and($cuestion->riesgos()->first()?->id)->toBe($riesgo->id);

    // Vincular dos veces no puede reventar con un error de índice único.
    app(VincularRiesgoACuestion::class)->vincular($cuestion, $riesgo, $this->usuario);

    expect($cuestion->riesgos()->count())->toBe(1);
});

it('el indicador de cuestiones sin riesgo sólo cuenta las adversas', function (): void {
    CuestionContexto::factory()->deTipo(TipoCuestion::Fortaleza)->create();
    CuestionContexto::factory()->deTipo(TipoCuestion::Oportunidad)->create();
    $amenaza = CuestionContexto::factory()->deTipo(TipoCuestion::Amenaza)->create();

    expect(CuestionContexto::query()->sinRiesgo()->count())->toBe(1);

    app(VincularRiesgoACuestion::class)->vincular($amenaza, Riesgo::factory()->create(), $this->usuario);

    expect(CuestionContexto::query()->sinRiesgo()->count())->toBe(0);
});

/*
 * El origen se pone y no se pregunta, igual que en la acción correctiva. Éste es
 * además el único camino que produce tareas con `OrigenTarea::Contexto`.
 */
it('la tarea de una cuestión nace con su origen puesto y vinculada', function (): void {
    $cuestion = CuestionContexto::factory()->deTipo(TipoCuestion::Debilidad)->create();

    $tarea = app(AbrirTareaDeCuestion::class)($cuestion, [
        'titulo' => 'Levantar el inventario de software',
        'prioridad' => PrioridadTarea::Alta->value,
    ], $this->usuario);

    expect($tarea->origen)->toBe(OrigenTarea::Contexto)
        ->and($cuestion->tareas()->count())->toBe(1);
});

/*
 * **Sin doble vínculo**, a diferencia de la acción correctiva. Allí hacía falta
 * porque el plan de adecuación imprimía «sin trabajo planificado» sobre una
 * medida que sí lo tenía; aquí no hay medida detrás por construcción, y forzarla
 * contra una implantación arbitraria es lo que `OrigenTarea::Propia` existe para
 * evitar.
 */
it('la tarea de una cuestión no se cuelga de ninguna implantación', function (): void {
    $cuestion = CuestionContexto::factory()->deTipo(TipoCuestion::Debilidad)->create();

    $tarea = app(AbrirTareaDeCuestion::class)($cuestion, [
        'titulo' => 'Lo que sea',
        'prioridad' => PrioridadTarea::Media->value,
    ], $this->usuario);

    expect($tarea->implantaciones()->count())->toBe(0);
});

it('desvincular una tarea la deja viva en el plan de acción', function (): void {
    $cuestion = CuestionContexto::factory()->deTipo(TipoCuestion::Debilidad)->create();

    $tarea = app(AbrirTareaDeCuestion::class)($cuestion, [
        'titulo' => 'Lo que sea',
        'prioridad' => PrioridadTarea::Media->value,
    ], $this->usuario);

    app(AbrirTareaDeCuestion::class)->desvincular($cuestion, $tarea);

    expect($cuestion->tareas()->count())->toBe(0)
        ->and($tarea->fresh())->not->toBeNull();
});

it('un requisito de una parte interesada se ata a la medida que lo cubre', function (): void {
    $parte = ParteInteresada::factory()->create();
    $requisito = RequisitoInteresado::factory()->for($parte, 'parteInteresada')->legal()->create();
    $implantacion = Implantacion::factory()->create();

    app(VincularImplantacionARequisito::class)->vincular($requisito, $implantacion, $this->usuario);

    expect($requisito->implantaciones()->count())->toBe(1)
        // Y se recorre en los dos sentidos: es lo que la SoA necesita para
        // justificar la inclusión sin registrar el vínculo dos veces.
        ->and($implantacion->requisitosInteresados()->count())->toBe(1);
});

/*
 * «Obligación sin cubrir» cuenta lo que ata —legal y contractual— y deja fuera
 * las expectativas. Meterlas señalaría treinta cosas donde hay tres.
 */
it('el indicador de obligaciones sin cubrir deja fuera las expectativas', function (): void {
    $parte = ParteInteresada::factory()->create();

    RequisitoInteresado::factory()->for($parte, 'parteInteresada')->legal()->create();
    RequisitoInteresado::factory()->for($parte, 'parteInteresada')
        ->deNaturaleza(NaturalezaRequisito::Expectativa)->create();

    expect(RequisitoInteresado::query()->obligacionSinCubrir()->count())->toBe(1)
        ->and(ParteInteresada::query()->conObligacionSinCubrir()->count())->toBe(1);

    $legal = RequisitoInteresado::query()->queObligan()->firstOrFail();
    app(VincularImplantacionARequisito::class)->vincular(
        $legal,
        Implantacion::factory()->create(),
        $this->usuario,
    );

    expect(RequisitoInteresado::query()->obligacionSinCubrir()->count())->toBe(0)
        ->and(ParteInteresada::query()->conObligacionSinCubrir()->count())->toBe(0);
});

/*
 * Borrar un requisito se lleva sus vínculos por la cascada de la pivote, y es lo
 * correcto: el vínculo dice «esta medida cubre este requisito», y sin el
 * requisito no dice nada. Lo que NO puede llevarse es la implantación.
 */
it('borrar un requisito no se lleva la implantación por delante', function (): void {
    $parte = ParteInteresada::factory()->create();
    $requisito = RequisitoInteresado::factory()->for($parte, 'parteInteresada')->legal()->create();
    $implantacion = Implantacion::factory()->create();

    app(VincularImplantacionARequisito::class)->vincular($requisito, $implantacion, $this->usuario);
    $requisito->delete();

    expect($implantacion->fresh())->not->toBeNull()
        ->and($implantacion->requisitosInteresados()->count())->toBe(0);
});
