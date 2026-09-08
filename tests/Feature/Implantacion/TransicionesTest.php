<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\CambiarEstado;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Excepciones\TransicionNoPermitida;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Implantacion\Models\ImplantacionTransicion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;

/**
 * Transiciones de estado. Prioridad 3 de cobertura.
 *
 * El auditor no pregunta "¿está implantado?", pregunta "¿desde cuándo?"
 * (invariante 7). Un cambio de estado sin su fila de histórico es un agujero en
 * la traza, así que las dos cosas van en la misma transacción o no van.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->cambiar = app(CambiarEstado::class);
    $this->usuario = User::factory()->create(['organizacion_id' => $this->organizacion->id]);

    $marco = Marco::factory()->create();
    $requisito = Requisito::factory()->create(['marco_id' => $marco->id]);
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();

    $this->implantacion = Implantacion::factory()->create([
        'sistema_id' => $sistema->id,
        'requisito_id' => $requisito->id,
    ]);
});

it('registra cada transición con estado anterior, nuevo, autor y fecha', function (): void {
    ($this->cambiar)($this->implantacion, EstadoImplantacion::Planificado, $this->usuario, 'Entra en el plan de adecuación.');

    $transicion = ImplantacionTransicion::query()->latest('id')->firstOrFail();

    expect($this->implantacion->refresh()->estado)->toBe(EstadoImplantacion::Planificado)
        ->and($transicion->estado_anterior)->toBe(EstadoImplantacion::NoIniciado)
        ->and($transicion->estado_nuevo)->toBe(EstadoImplantacion::Planificado)
        ->and($transicion->usuario_id)->toBe($this->usuario->id)
        ->and($transicion->nota)->toBe('Entra en el plan de adecuación.')
        ->and($transicion->created_at)->not->toBeNull();
});

it('permite el camino completo y deja el rastro entero', function (): void {
    foreach ([EstadoImplantacion::Planificado, EstadoImplantacion::EnProgreso, EstadoImplantacion::Implantado] as $estado) {
        ($this->cambiar)($this->implantacion, $estado, $this->usuario);
    }

    $recorrido = $this->implantacion->refresh()->transiciones->pluck('estado_nuevo.value')->all();

    expect($recorrido)->toBe(['planificado', 'en_progreso', 'implantado'])
        ->and($this->implantacion->estado)->toBe(EstadoImplantacion::Implantado);
});

it('permite volver atrás, que es lo que el histórico tiene que poder enseñar', function (): void {
    ($this->cambiar)($this->implantacion, EstadoImplantacion::Implantado, $this->usuario);
    ($this->cambiar)($this->implantacion, EstadoImplantacion::EnProgreso, $this->usuario, 'La verificación de eficacia falló.');

    expect($this->implantacion->refresh()->estado)->toBe(EstadoImplantacion::EnProgreso)
        ->and($this->implantacion->transiciones)->toHaveCount(2);
});

it('un cambio al mismo estado no genera transición', function (): void {
    ($this->cambiar)($this->implantacion, EstadoImplantacion::NoIniciado, $this->usuario);

    expect(ImplantacionTransicion::query()->count())->toBe(0);
});

it('no deja poner no_aplica a mano', function (): void {
    ($this->cambiar)($this->implantacion, EstadoImplantacion::NoAplica, $this->usuario);
})->throws(TransicionNoPermitida::class, 'lo deriva el motor de categorización');

it('no deja sacar a mano de no_aplica', function (): void {
    // Se llega ahí sólo por recálculo; se sale de ahí sólo por recálculo.
    $this->implantacion->update([
        'aplica' => false,
        'estado' => EstadoImplantacion::NoAplica->value,
        'justificacion' => 'Deja de exigirse.',
    ]);

    ($this->cambiar)($this->implantacion, EstadoImplantacion::EnProgreso, $this->usuario);
})->throws(TransicionNoPermitida::class);

it('una transición ilegal no deja rastro', function (): void {
    ($this->cambiar)($this->implantacion, EstadoImplantacion::Implantado, $this->usuario);

    try {
        ($this->cambiar)($this->implantacion, EstadoImplantacion::NoIniciado, $this->usuario);
    } catch (TransicionNoPermitida) {
        // Esperado: de implantado no se vuelve a no_iniciado.
    }

    expect($this->implantacion->refresh()->estado)->toBe(EstadoImplantacion::Implantado)
        ->and(ImplantacionTransicion::query()->count())->toBe(1);
});

it('la transición hereda la organización de la implantación', function (): void {
    ($this->cambiar)($this->implantacion, EstadoImplantacion::Planificado, $this->usuario);

    expect(ImplantacionTransicion::query()->latest('id')->firstOrFail()->organizacion_id)
        ->toBe($this->organizacion->id);
});
