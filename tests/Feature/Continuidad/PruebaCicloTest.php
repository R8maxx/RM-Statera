<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\CancelarPrueba;
use App\Domain\Continuidad\CodigoPrueba;
use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Enums\TipoPrueba;
use App\Domain\Continuidad\Excepciones\ServicioNoValido;
use App\Domain\Continuidad\Excepciones\TransicionDePruebaNoPermitida;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Continuidad\Models\PruebaContinuidadTransicion;
use App\Domain\Continuidad\PlanificarPrueba;
use App\Domain\Continuidad\RegistrarResultadoPrueba;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use Illuminate\Database\QueryException;

/*
|--------------------------------------------------------------------------
| El ciclo de una prueba de continuidad: § 4.11 y op.cont.3
|--------------------------------------------------------------------------
|
| Un plan sin pruebas es papel: op.cont.3 no pide tener el plan escrito, pide
| comprobarlo. La aserción que paga el módulo es la comparación entre lo que
| el BIA promete (RTO) y lo que la prueba de verdad alcanzó.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->plan = Documento::factory()->planContinuidad()->create();
    $this->servicio = Activo::factory()->deTipo(TipoActivo::Servicios)->create();

    $this->planificar = app(PlanificarPrueba::class);
    $this->registrarResultado = app(RegistrarResultadoPrueba::class);
    $this->cancelar = app(CancelarPrueba::class);
    $this->codigo = app(CodigoPrueba::class);
});

function atributosDePrueba(Documento $plan, ?string $codigo = null): array
{
    return [
        'codigo' => $codigo ?? app(CodigoPrueba::class)->siguiente(),
        'titulo' => 'Simulacro de caída del correo corporativo',
        'documento_id' => $plan->id,
        'tipo' => TipoPrueba::Simulacro->value,
        'fecha_prevista' => now()->addDays(15)->toDateString(),
    ];
}

it('nace planificada con código PC y su transición', function (): void {
    $codigo = $this->codigo->siguiente();

    $prueba = ($this->planificar)(
        atributosDePrueba($this->plan, $codigo),
        [$this->servicio->id],
        $this->usuario,
    );

    expect($prueba->estado)->toBe(EstadoPrueba::Planificada)
        ->and($prueba->codigo)->toBe(sprintf('PC-%d-01', now()->year))
        ->and(PruebaContinuidadTransicion::query()->count())->toBe(1)
        ->and(PruebaContinuidadTransicion::query()->sole()->estado_anterior)->toBeNull()
        ->and(PruebaContinuidadTransicion::query()->sole()->estado_nuevo)->toBe(EstadoPrueba::Planificada)
        ->and($prueba->servicios()->pluck('activos.id'))->toEqual(collect([$this->servicio->id]));
});

it('no registra resultado sin fecha ni resultado', function (): void {
    $prueba = ($this->planificar)(atributosDePrueba($this->plan), [$this->servicio->id], $this->usuario);

    expect(fn () => $prueba->update(['estado' => EstadoPrueba::Realizada->value]))
        ->toThrow(QueryException::class);
});

it('registrar resultado sella fecha, resultado y los tiempos alcanzados por servicio', function (): void {
    $prueba = ($this->planificar)(atributosDePrueba($this->plan), [$this->servicio->id], $this->usuario);

    $resultado = ($this->registrarResultado)($prueba, [
        'fecha_realizacion' => now()->toDateString(),
        'resultado' => ResultadoPrueba::Superada->value,
        'conclusiones' => 'El correo se restableció dentro de plazo.',
        'servicios' => [
            $this->servicio->id => ['rto_alcanzado_horas' => 6, 'rpo_alcanzado_horas' => 1],
        ],
    ], $this->usuario);

    expect($resultado->estado)->toBe(EstadoPrueba::Realizada)
        ->and($resultado->fecha_realizacion?->toDateString())->toBe(now()->toDateString())
        ->and($resultado->resultado)->toBe(ResultadoPrueba::Superada)
        ->and($resultado->conclusiones)->toBe('El correo se restableció dentro de plazo.');

    $pivote = $resultado->servicios->firstWhere('id', $this->servicio->id)?->pivot;

    expect((int) $pivote->rto_alcanzado_horas)->toBe(6)
        ->and((int) $pivote->rpo_alcanzado_horas)->toBe(1)
        ->and(PruebaContinuidadTransicion::query()->count())->toBe(2);
});

it('compara el RTO alcanzado con el objetivo del BIA', function (): void {
    BiaServicio::factory()->deActivo($this->servicio)->create(['rto_horas' => 4]);

    $superado = ($this->planificar)(atributosDePrueba($this->plan), [$this->servicio->id], $this->usuario);
    $superado = ($this->registrarResultado)($superado, [
        'fecha_realizacion' => now()->toDateString(),
        'resultado' => ResultadoPrueba::Fallida->value,
        'servicios' => [$this->servicio->id => ['rto_alcanzado_horas' => 6]],
    ], $this->usuario);

    expect($superado->excedeRto($this->servicio))->toBeTrue();

    $cumplido = ($this->planificar)(atributosDePrueba($this->plan), [$this->servicio->id], $this->usuario);
    $cumplido = ($this->registrarResultado)($cumplido, [
        'fecha_realizacion' => now()->toDateString(),
        'resultado' => ResultadoPrueba::Superada->value,
        'servicios' => [$this->servicio->id => ['rto_alcanzado_horas' => 3]],
    ], $this->usuario);

    expect($cumplido->excedeRto($this->servicio))->toBeFalse();

    $servicioSinBia = Activo::factory()->deTipo(TipoActivo::Servicios)->create();
    $sinBia = ($this->planificar)(atributosDePrueba($this->plan), [$servicioSinBia->id], $this->usuario);
    $sinBia = ($this->registrarResultado)($sinBia, [
        'fecha_realizacion' => now()->toDateString(),
        'resultado' => ResultadoPrueba::Superada->value,
        'servicios' => [$servicioSinBia->id => ['rto_alcanzado_horas' => 2]],
    ], $this->usuario);

    expect($sinBia->excedeRto($servicioSinBia))->toBeNull();
});

it('cancelar exige motivo', function (): void {
    $prueba = ($this->planificar)(atributosDePrueba($this->plan), [$this->servicio->id], $this->usuario);

    expect(fn () => ($this->cancelar)($prueba, '', $this->usuario))
        ->toThrow(TransicionDePruebaNoPermitida::class);

    $cancelada = ($this->cancelar)($prueba, 'Se pospone por indisponibilidad del proveedor.', $this->usuario);

    expect($cancelada->estado)->toBe(EstadoPrueba::Cancelada)
        ->and($cancelada->motivo_cancelacion)->toBe('Se pospone por indisponibilidad del proveedor.')
        ->and(PruebaContinuidadTransicion::query()->latest('id')->first()?->nota)
        ->toBe('Se pospone por indisponibilidad del proveedor.');
});

it('rechaza planificar sobre un documento que no es un plan de continuidad', function (): void {
    $procedimiento = Documento::factory()->deTipo(TipoDocumento::Procedimiento)->create(['sistema_id' => null]);

    expect(fn () => ($this->planificar)(atributosDePrueba($procedimiento), [$this->servicio->id], $this->usuario))
        ->toThrow(ServicioNoValido::class);

    expect(PruebaContinuidad::query()->count())->toBe(0);
});

it('no deja borrar un plan con pruebas', function (): void {
    ($this->planificar)(atributosDePrueba($this->plan), [$this->servicio->id], $this->usuario);

    expect(fn () => $this->plan->delete())->toThrow(QueryException::class);
});

it('vencidas() recoge sólo las planificadas con fecha pasada', function (): void {
    $vencida = PruebaContinuidad::factory()->deDocumento($this->plan)->planificada()->create([
        'fecha_prevista' => now()->subDays(5)->toDateString(),
    ]);
    PruebaContinuidad::factory()->deDocumento($this->plan)->planificada()->create([
        'fecha_prevista' => now()->addDays(5)->toDateString(),
    ]);
    PruebaContinuidad::factory()->deDocumento($this->plan)->realizada()->create([
        'fecha_prevista' => now()->subDays(5)->toDateString(),
    ]);

    $vencidas = PruebaContinuidad::query()->vencidas()->get();

    expect($vencidas->pluck('id'))->toEqual(collect([$vencida->id]));
});
