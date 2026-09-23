<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Enums\NivelImpacto;
use App\Domain\Continuidad\Enums\TramoImpacto;
use App\Domain\Continuidad\Excepciones\ServicioNoValido;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\RegistrarBia;
use App\Domain\Continuidad\UmbralTolerable;
use Illuminate\Database\QueryException;

beforeEach(fn () => comoOrganizacion());

it('deriva el umbral del primer tramo muy alto', function (): void {
    $bia = BiaServicio::factory()->create([
        'impacto_4h' => NivelImpacto::Bajo, 'impacto_1d' => NivelImpacto::Alto,
        'impacto_3d' => NivelImpacto::MuyAlto, 'impacto_1s' => NivelImpacto::MuyAlto,
        'impacto_1m' => NivelImpacto::MuyAlto, 'rto_horas' => 24,
    ]);

    expect(UmbralTolerable::de($bia))->toBe(TramoImpacto::TresDias)
        ->and(UmbralTolerable::horas($bia))->toBe(72)
        ->and($bia->rtoIncoherente())->toBeFalse();
});

it('no tiene umbral si ningún tramo llega a muy alto', function (): void {
    $bia = BiaServicio::factory()->create(['impacto_1m' => NivelImpacto::Alto]);
    expect(UmbralTolerable::de($bia))->toBeNull()->and($bia->rtoIncoherente())->toBeFalse();
});

it('marca incoherente un RTO por encima del umbral', function (): void {
    $bia = BiaServicio::factory()->create([
        'impacto_4h' => NivelImpacto::MuyAlto, 'impacto_1d' => NivelImpacto::MuyAlto,
        'impacto_3d' => NivelImpacto::MuyAlto, 'impacto_1s' => NivelImpacto::MuyAlto,
        'impacto_1m' => NivelImpacto::MuyAlto, 'rto_horas' => 8,
    ]);
    expect($bia->rtoIncoherente())->toBeTrue()
        ->and(BiaServicio::query()->rtoIncoherente()->count())->toBe(1);
});

it('la base rechaza un impacto que baja con el tiempo', function (): void {
    expect(fn () => BiaServicio::factory()->create([
        'impacto_1d' => NivelImpacto::Alto, 'impacto_3d' => NivelImpacto::Medio,
    ]))->toThrow(QueryException::class);
});

it('la base rechaza un BIA sobre un activo que no es un servicio', function (): void {
    $hardware = Activo::factory()->create(['tipo' => 'hardware']);
    expect(fn () => app(RegistrarBia::class)(['activo_id' => $hardware->id] + BiaServicio::factory()->raw()))
        ->toThrow(ServicioNoValido::class);
});
