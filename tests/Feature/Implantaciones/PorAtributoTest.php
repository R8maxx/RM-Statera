<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Implantacion\ResumenPorAtributo;
use App\Domain\Sistema\Models\Sistema;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Lo exigible agrupado por atributo de la ISO 27002 (§ 4.4)
|--------------------------------------------------------------------------
|
| Lo que se clava: cuenta sobre lo exigible, un control con varios valores
| cuenta en cada uno, el orden y las etiquetas son los del vocabulario, y la
| dimensión que llega de la petición sólo vale si está en él.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $marco = Marco::factory()->create(['atributos' => [[
        'clave' => 'tipo_control',
        'etiqueta' => 'Tipo de control',
        'valores' => [
            ['valor' => 'preventivo', 'etiqueta' => 'Preventivo'],
            ['valor' => 'detectivo', 'etiqueta' => 'Detectivo'],
            ['valor' => 'correctivo', 'etiqueta' => 'Correctivo'],
        ],
    ]]]);

    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();
    $this->otro = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();

    $control = function (array $tipos, EstadoImplantacion $estado, bool $aplica = true, ?Sistema $sistema = null) use ($marco): Implantacion {
        $requisito = Requisito::factory()->create(['marco_id' => $marco->id, 'atributos' => ['tipo_control' => $tipos]]);

        // Una exclusión va en `no_aplica` y con su justificación: lo exige la base.
        return Implantacion::factory()->for($sistema ?? $this->sistema)->enEstado($aplica ? $estado : EstadoImplantacion::NoAplica)->create([
            'requisito_id' => $requisito->id,
            'aplica' => $aplica,
            'justificacion' => $aplica ? null : 'No se exige a este sistema.',
        ]);
    };

    $control(['preventivo'], EstadoImplantacion::Implantado);
    $control(['preventivo', 'detectivo'], EstadoImplantacion::EnProgreso);
    $control(['detectivo'], EstadoImplantacion::NoIniciado);
    // No exigible: no cuenta en ningún valor.
    $control(['correctivo'], EstadoImplantacion::NoIniciado, false);
    // De otro sistema: cuenta con todos, no con el filtro.
    $control(['correctivo'], EstadoImplantacion::Implantado, true, $this->otro);
});

it('cuenta lo exigible por valor, y un control con dos valores en cada uno', function (): void {
    $filas = collect(app(ResumenPorAtributo::class)->para('tipo_control'))->keyBy('valor');

    expect($filas->keys()->all())->toBe(['preventivo', 'detectivo', 'correctivo'])
        ->and($filas['preventivo']['total'])->toBe(2)
        ->and($filas['preventivo']['implantadas'])->toBe(1)
        ->and($filas['detectivo']['total'])->toBe(2)
        ->and($filas['detectivo']['implantadas'])->toBe(0)
        ->and($filas['correctivo']['total'])->toBe(1);
});

it('acotado a un sistema, deja fuera lo de los demás', function (): void {
    $filas = collect(app(ResumenPorAtributo::class)->para('tipo_control', $this->sistema->id))->keyBy('valor');

    expect($filas['correctivo']['total'])->toBe(0)
        ->and($filas['preventivo']['total'])->toBe(2);
});

it('una dimensión que no está en el vocabulario no se consulta', function (): void {
    expect(app(ResumenPorAtributo::class)->para("tipo_control') or 1=1 --"))->toBeNull();
});

it('la pantalla agrupa por la dimensión pedida y enlaza a la tabla filtrada', function (): void {
    $this->actingAs($this->usuario)
        ->get('/implantaciones/atributos?dimension=no_existe')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('implantaciones/PorAtributo')
            // Una dimensión que no existe cae en la primera, no en un error.
            ->where('dimension', 'tipo_control')
            ->has('filas', 3)
            ->where('filas.0.etiqueta', 'Preventivo')
            ->where('filas.0.segmentos.0.clave', 'implantado'));

    // La fila lleva al filtro que ya existe: lo que se cuenta aquí es lo que filtra la tabla.
    $this->actingAs($this->usuario)
        ->get("/implantaciones?filter[atributo_tipo_control][]=preventivo&filter[sistema_id]={$this->sistema->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));
});
