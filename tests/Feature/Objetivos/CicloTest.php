<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Objetivo\CambiarEstadoObjetivo;
use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Excepciones\TransicionDeObjetivoNoPermitida;
use App\Domain\Objetivo\Models\Objetivo;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| El ciclo de un objetivo de seguridad (cláusula 6.2)
|--------------------------------------------------------------------------
|
| Aquí está la regla del módulo: **un borrador se escribe como se pueda y un
| compromiso no**. Aprobar exige plazo y deja firma; cerrar con resultado exige
| decir por qué cuando el resultado es malo; y las tres decisiones que
| comprometen a la organización van con el séptimo verbo de supervisión del
| producto.
|
| Lo que se prueba dos veces a propósito —en el dominio y por HTTP— es lo que
| vale también para un importador: las reglas de motivo y de plazo viven en
| `CambiarEstadoObjetivo` y el `FormRequest` sólo las repite para que el mensaje
| llegue al campo.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('no se puede aprobar un objetivo sin decir para cuándo', function (): void {
    $objetivo = Objetivo::factory()->create(['fecha_objetivo' => null]);

    expect(fn () => app(CambiarEstadoObjetivo::class)($objetivo, EstadoObjetivo::Aprobado, $this->usuario))
        ->toThrow(TransicionDeObjetivoNoPermitida::class, 'la cláusula 6.2 lo pide por escrito');

    expect($objetivo->refresh()->estado)->toBe(EstadoObjetivo::Propuesto);
});

it('aprobar estampa la firma y deja histórico', function (): void {
    $objetivo = Objetivo::factory()->create(['fecha_objetivo' => Carbon::today()->addMonths(6)]);

    app(CambiarEstadoObjetivo::class)($objetivo, EstadoObjetivo::Aprobado, $this->usuario, 'Comité del 3 de marzo.');

    $objetivo->refresh();

    expect($objetivo->estado)->toBe(EstadoObjetivo::Aprobado)
        ->and($objetivo->aprobado_por_id)->toBe($this->usuario->id)
        ->and($objetivo->aprobado_en)->not->toBeNull()
        ->and($objetivo->nota_aprobacion)->toBe('Comité del 3 de marzo.')
        ->and($objetivo->fecha_cierre)->toBeNull();

    $transicion = $objetivo->transiciones()->first();

    expect($transicion?->estado_anterior)->toBe(EstadoObjetivo::Propuesto)
        ->and($transicion?->estado_nuevo)->toBe(EstadoObjetivo::Aprobado)
        ->and($transicion?->usuario_id)->toBe($this->usuario->id);
});

it('reabrir un objetivo aprobado no reescribe quién lo firmó', function (): void {
    $firmante = usuarioCon();
    $objetivo = Objetivo::factory()->enEstado(EstadoObjetivo::Aprobado, $firmante->id)->create();

    $cambiar = app(CambiarEstadoObjetivo::class);
    $cambiar($objetivo, EstadoObjetivo::NoAlcanzado, $this->usuario, 'No llegó el presupuesto.');
    $cambiar($objetivo, EstadoObjetivo::Aprobado, $this->usuario, 'Se le da otro trimestre.');

    // La firma es de quien firmó, no de quien lo reabrió: mismo criterio que el
    // objetivo sellado de una medición, que corregir una cifra no cambia.
    expect($objetivo->refresh()->aprobado_por_id)->toBe($firmante->id)
        ->and($objetivo->estado)->toBe(EstadoObjetivo::Aprobado)
        ->and($objetivo->fecha_cierre)->toBeNull();
});

it('volver al borrador suelta la firma entera', function (): void {
    $firmante = usuarioCon();
    $objetivo = Objetivo::factory()->enEstado(EstadoObjetivo::Aprobado, $firmante->id)->create();

    $cambiar = app(CambiarEstadoObjetivo::class);
    $cambiar($objetivo, EstadoObjetivo::Retirado, $this->usuario, 'Cambió la prioridad.');
    $cambiar($objetivo, EstadoObjetivo::Propuesto, $this->usuario);

    $objetivo->refresh();

    expect($objetivo->aprobado_por_id)->toBeNull()
        ->and($objetivo->aprobado_en)->toBeNull()
        ->and($objetivo->nota_aprobacion)->toBeNull()
        ->and($objetivo->fecha_cierre)->toBeNull()
        ->and($objetivo->estado)->toBe(EstadoObjetivo::Propuesto);

    // Lo que no se pierde es el rastro: la retirada y la vuelta siguen en el
    // histórico, con su autor y su motivo. Son dos y no tres porque la factory
    // crea la fila ya aprobada, sin pasar por `RegistrarObjetivo`.
    expect($objetivo->transiciones()->count())->toBe(2);
});

it('dar un objetivo por no alcanzado exige decir por qué', function (): void {
    $objetivo = Objetivo::factory()->enEstado(EstadoObjetivo::Aprobado, $this->usuario->id)->create();

    expect(fn () => app(CambiarEstadoObjetivo::class)($objetivo, EstadoObjetivo::NoAlcanzado, $this->usuario))
        ->toThrow(TransicionDeObjetivoNoPermitida::class, 'pregunta la revisión por la dirección');
});

it('retirar un objetivo exige decir por qué', function (): void {
    $objetivo = Objetivo::factory()->create();

    expect(fn () => app(CambiarEstadoObjetivo::class)($objetivo, EstadoObjetivo::Retirado, $this->usuario))
        ->toThrow(TransicionDeObjetivoNoPermitida::class, 'exige decir por qué');
});

it('no se vuelve a propuesto desde un compromiso firmado', function (): void {
    $objetivo = Objetivo::factory()->enEstado(EstadoObjetivo::Aprobado, $this->usuario->id)->create();

    expect(fn () => app(CambiarEstadoObjetivo::class)($objetivo, EstadoObjetivo::Propuesto, $this->usuario))
        ->toThrow(TransicionDeObjetivoNoPermitida::class);
});

it('un objetivo retirado desde el borrador se cierra sin firma', function (): void {
    $objetivo = Objetivo::factory()->create();

    app(CambiarEstadoObjetivo::class)($objetivo, EstadoObjetivo::Retirado, $this->usuario, 'Ya no aplica.');

    $objetivo->refresh();

    // El `CHECK` de la firma no alcanza a `retirado` a propósito: rellenarla
    // sería fabricar una aprobación que nadie dio.
    expect($objetivo->aprobado_en)->toBeNull()
        ->and($objetivo->fecha_cierre)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| El séptimo verbo de supervisión
|--------------------------------------------------------------------------
*/

it('un técnico propone objetivos y no los firma', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $objetivo = Objetivo::factory()->create(['fecha_objetivo' => Carbon::today()->addMonths(3)]);

    $this->actingAs($tecnico)
        ->post("/objetivos/{$objetivo->id}/estado", ['estado' => EstadoObjetivo::Aprobado->value])
        ->assertForbidden();

    expect($objetivo->refresh()->estado)->toBe(EstadoObjetivo::Propuesto);
});

it('un técnico tampoco retira un objetivo: es renunciar a un compromiso', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $objetivo = Objetivo::factory()->create();

    $this->actingAs($tecnico)
        ->post("/objetivos/{$objetivo->id}/estado", [
            'estado' => EstadoObjetivo::Retirado->value,
            'nota' => 'Cambió la prioridad.',
        ])
        ->assertForbidden();
});

it('el responsable de seguridad firma, y la transición queda registrada', function (): void {
    $objetivo = Objetivo::factory()->create(['fecha_objetivo' => Carbon::today()->addMonths(3)]);

    $this->actingAs($this->usuario)
        ->post("/objetivos/{$objetivo->id}/estado", ['estado' => EstadoObjetivo::Aprobado->value])
        ->assertRedirect();

    expect($objetivo->refresh()->estado)->toBe(EstadoObjetivo::Aprobado)
        ->and($objetivo->aprobado_por_id)->toBe($this->usuario->id);
});

it('el formulario devuelve el error en el campo cuando falta el motivo', function (): void {
    $objetivo = Objetivo::factory()->enEstado(EstadoObjetivo::Aprobado, $this->usuario->id)->create();

    $this->actingAs($this->usuario)
        ->post("/objetivos/{$objetivo->id}/estado", ['estado' => EstadoObjetivo::NoAlcanzado->value])
        ->assertSessionHasErrors('nota');

    expect($objetivo->refresh()->estado)->toBe(EstadoObjetivo::Aprobado);
});
