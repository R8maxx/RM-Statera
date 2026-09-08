<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\Exigencia;
use App\Domain\Categorizacion\ConjuntoExigible;
use App\Domain\Categorizacion\DiferenciaConjuntos;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Categorizacion\MedidaExigible;

/**
 * @param  array<string, string>  $medidas  Exigencia por código de medida.
 */
function conjunto(array $medidas): ConjuntoExigible
{
    $id = 0;

    return new ConjuntoExigible(array_map(
        fn (string $codigo, string $exigencia): MedidaExigible => new MedidaExigible(
            requisitoId: ++$id,
            codigo: $codigo,
            titulo: "Medida {$codigo}",
            exigencia: Exigencia::desde($exigencia),
            origen: OrigenExigencia::Categoria,
        ),
        array_keys($medidas),
        array_values($medidas),
    ));
}

it('no detecta cambios entre dos conjuntos iguales', function (): void {
    $conjunto = conjunto(['org.1' => 'aplica', 'op.acc.5' => 'R1']);

    $diferencia = DiferenciaConjuntos::entre($conjunto, conjunto(['org.1' => 'aplica', 'op.acc.5' => 'R1']));

    expect($diferencia->hayCambios())->toBeFalse()
        ->and($diferencia->resumen())->toBe([
            'nuevas' => 0,
            'dejan_de_aplicar' => 0,
            'cambian_de_exigencia' => 0,
        ]);
});

it('separa las medidas nuevas, las que dejan de aplicar y las que cambian de exigencia', function (): void {
    $anterior = conjunto(['org.1' => 'aplica', 'op.acc.5' => 'R1', 'mp.eq.2' => 'aplica']);
    $nuevo = conjunto(['org.1' => 'aplica', 'op.acc.5' => 'R2', 'op.cont.2' => 'aplica']);

    $diferencia = DiferenciaConjuntos::entre($anterior, $nuevo);

    expect($diferencia->hayCambios())->toBeTrue()
        ->and(array_map(fn ($m): string => $m->codigo, $diferencia->nuevas))->toBe(['op.cont.2'])
        ->and(array_map(fn ($m): string => $m->codigo, $diferencia->dejanDeAplicar))->toBe(['mp.eq.2'])
        ->and($diferencia->cambianDeExigencia)->toHaveCount(1);

    $cambio = $diferencia->cambianDeExigencia[0];

    expect($cambio['codigo'])->toBe('op.acc.5')
        ->and((string) $cambio['anterior']->exigencia)->toBe('R1')
        ->and((string) $cambio['nueva']->exigencia)->toBe('R2');
});

it('un conjunto anterior vacío convierte todo en nuevo', function (): void {
    $diferencia = DiferenciaConjuntos::entre(new ConjuntoExigible, conjunto(['org.1' => 'aplica']));

    expect($diferencia->nuevas)->toHaveCount(1)
        ->and($diferencia->dejanDeAplicar)->toBeEmpty();
});

it('un conjunto nuevo vacío hace que todo deje de aplicar', function (): void {
    $diferencia = DiferenciaConjuntos::entre(conjunto(['org.1' => 'aplica']), new ConjuntoExigible);

    // No se borran: el punto 3 marcará sus implantaciones, sin destruirlas.
    expect($diferencia->dejanDeAplicar)->toHaveCount(1)
        ->and($diferencia->nuevas)->toBeEmpty();
});

it('el conjunto se indexa por código y no admite duplicados', function (): void {
    $conjunto = conjunto(['op.acc.5' => 'R1', 'org.1' => 'aplica']);

    expect($conjunto->codigos())->toBe(['op.acc.5', 'org.1'])
        ->and($conjunto->count())->toBe(2)
        ->and($conjunto->exige('org.1'))->toBeTrue()
        ->and($conjunto->paraCodigo('no.existe'))->toBeNull();
});

it('agrupa por familia tomando el primer segmento del código', function (): void {
    $familias = conjunto([
        'org.1' => 'aplica',
        'op.acc.5' => 'R1',
        'op.cont.2' => 'aplica',
        'mp.eq.2' => 'aplica',
    ])->agrupadoPorFamilia();

    expect(array_keys($familias))->toEqualCanonicalizing(['org', 'op', 'mp'])
        ->and($familias['op'])->toHaveCount(2);
});
