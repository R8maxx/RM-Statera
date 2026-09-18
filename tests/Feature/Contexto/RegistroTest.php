<?php

declare(strict_types=1);

use App\Domain\Contexto\AprobarAnalisis;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\Models\RequisitoInteresado;
use App\Domain\Contexto\RegistroContexto;
use App\Http\Resources\Panel\Indicador;

/**
 * Los indicadores del módulo, y el que cierra el contrato.
 *
 * **La clave de cada indicador ES la clave de su filtro y el scope ES el tercer
 * argumento de su `Filtro::porScope()`.** El último test de este fichero es el que
 * lo garantiza: recorre los indicadores, pide la lista por su filtro y compara.
 * Con la condición escrita dos veces, el día que cambie una el panel dirá 12 y la
 * tabla enseñará 9, y a partir de ahí nadie se fía del panel.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->registro = app(RegistroContexto::class);
});

it('cuenta sólo las cuestiones vigentes', function (): void {
    $analisis = AnalisisContexto::factory()->create();

    CuestionContexto::factory()->count(3)->create(['analisis_alta_id' => $analisis->id]);
    CuestionContexto::factory()->retirada($analisis)->create(['analisis_alta_id' => $analisis->id]);

    expect($this->registro->totalCuestiones())->toBe(3);
});

/*
 * El reparto lleva los cuadrantes vacíos dentro, a diferencia de casi todos los
 * del producto. Aquí el cero dice algo: un DAFO sin ninguna oportunidad es una
 * organización que sólo ha mirado lo que le puede salir mal.
 */
it('el reparto del DAFO enseña los cuatro cuadrantes, vacíos incluidos', function (): void {
    CuestionContexto::factory()->deTipo(TipoCuestion::Debilidad)->create();

    $reparto = $this->registro->porTipo();

    expect($reparto)->toHaveCount(4)
        ->and(array_column(array_map(fn ($tramo) => (array) $tramo, $reparto), 'valor'))
        ->toBe([0, 1, 0, 0]);
});

it('la tarjeta del panel dice desde cuándo es el análisis vigente', function (): void {
    CuestionContexto::factory()->create();

    expect($this->registro->paraElPanel()->analisisVigente)->toBeNull();

    $borrador = AnalisisContexto::query()->borrador()->firstOrFail();
    $borrador->update([
        'clima_pertinente' => false,
        'clima_justificacion' => 'La actividad no depende de instalaciones sensibles al clima.',
    ]);

    app(AprobarAnalisis::class)($borrador->refresh(), $this->usuario);

    $panel = $this->registro->paraElPanel();

    expect($panel->analisisVigente)->not->toBeNull()
        ->and($panel->mesesDesdeElAnalisis)->toBe(0)
        ->and($panel->climaPertinente)->toBeFalse();
});

it('cuenta los requisitos que obligan aparte de los que no', function (): void {
    $parte = ParteInteresada::factory()->create();

    RequisitoInteresado::factory()->for($parte, 'parteInteresada')->legal()->create();
    RequisitoInteresado::factory()->for($parte, 'parteInteresada')->create();

    $panel = $this->registro->paraElPanel();

    expect($panel->requisitosQueObligan)->toBe(1)
        ->and($panel->obligacionesSinCubrir)->toBe(1)
        ->and($panel->partes)->toBe(1);
});

/*
 * El contrato del módulo: pulsar una cifra tiene que enseñar exactamente esa
 * cifra. Se comprueba pidiendo la tabla por el filtro del propio indicador.
 */
it('el filtro de cada indicador devuelve exactamente su cifra', function (): void {
    $analisis = AnalisisContexto::factory()->create();

    CuestionContexto::factory()->deTipo(TipoCuestion::Amenaza)->count(2)
        ->create(['analisis_alta_id' => $analisis->id]);
    CuestionContexto::factory()->deTipo(TipoCuestion::Fortaleza)
        ->create(['analisis_alta_id' => $analisis->id]);

    $indicadores = [
        ...$this->registro->pendientesCuestiones(),
        ...$this->registro->pendientesPartes(),
    ];

    expect($indicadores)->not->toBeEmpty();

    foreach ($indicadores as $indicador) {
        /** @var Indicador $indicador */
        $respuesta = $this->actingAs($this->usuario)->get($indicador->base.'?'.$indicador->filtro);

        $respuesta->assertOk();
        $respuesta->assertInertia(fn ($pagina) => $pagina->where(
            'meta.total',
            $indicador->valor,
        ));
    }
});
