<?php

declare(strict_types=1);

use App\Domain\Continuidad\CambiarEstadoBia;
use App\Domain\Continuidad\EditarBia;
use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Excepciones\TransicionDeBiaNoPermitida;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\BiaServicioTransicion;
use App\Domain\Continuidad\RegistrarBia;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->bia = app(RegistrarBia::class)(BiaServicio::factory()->raw(), $this->usuario);
});

it('nace en borrador con su primera transición', function (): void {
    expect($this->bia->estado)->toBe(EstadoBia::Borrador)
        ->and(BiaServicioTransicion::query()->sole()->estado_anterior)->toBeNull();
});

it('aprobar sella aprobador, fecha y revisión a doce meses', function (): void {
    $bia = app(CambiarEstadoBia::class)($this->bia, EstadoBia::Aprobado, $this->usuario);
    expect($bia->aprobado_por_id)->toBe($this->usuario->id)
        ->and($bia->fecha_aprobacion?->toDateString())->toBe(today()->toDateString())
        ->and($bia->fecha_revision?->toDateString())->toBe(today()->addYear()->toDateString());
});

it('editar un BIA aprobado lo devuelve a borrador y lo deja en el histórico', function (): void {
    $bia = app(CambiarEstadoBia::class)($this->bia, EstadoBia::Aprobado, $this->usuario);
    $bia = app(EditarBia::class)($bia, ['rto_horas' => 12], $this->usuario);
    expect($bia->estado)->toBe(EstadoBia::Borrador)
        ->and($bia->aprobado_por_id)->toBeNull()
        ->and(BiaServicioTransicion::query()->latest('id')->first()?->nota)->toContain('Editado');
});

it('pasar a obsoleto exige motivo', function (): void {
    expect(fn () => app(CambiarEstadoBia::class)($this->bia, EstadoBia::Obsoleto, $this->usuario))
        ->toThrow(TransicionDeBiaNoPermitida::class);
});

it('un servicio no tiene dos BIA', function (): void {
    expect(fn () => app(RegistrarBia::class)(['activo_id' => $this->bia->activo_id] + BiaServicio::factory()->raw(), $this->usuario))
        ->toThrow(QueryException::class);
});

/**
 * `EditarBia` no es una puerta trasera hacia el estado. `estado` está en
 * `BiaServicio::$fillable` porque lo necesita `CambiarEstadoBia`, y aquí tiene
 * que rechazarse: dejarlo pasar cambiaría el estado sin fila en el histórico
 * (invariante 7) y sin el motivo que exige pasar a `obsoleto`.
 */
it('editar no deja colar un cambio de estado', function (): void {
    expect(fn () => app(EditarBia::class)($this->bia, ['estado' => 'obsoleto'], $this->usuario))
        ->toThrow(InvalidArgumentException::class);

    expect($this->bia->fresh()?->estado)->toBe(EstadoBia::Borrador)
        ->and(BiaServicioTransicion::query()->count())->toBe(1);
});
