<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Models\ClausulaContractual;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Proveedor\Models\ProveedorEvaluacion;

/*
|--------------------------------------------------------------------------
| Evaluar el contrato de un proveedor (§ 4.9, A.5.20, op.ext.1)
|--------------------------------------------------------------------------
|
| Cada cláusula vigente se contesta; el resultado pone el estado; la próxima
| evaluación se deriva de la política de la organización; y lo registrado no
| se edita.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->clausulas = ClausulaContractual::factory()->count(3)->create();
    $this->proveedor = Proveedor::factory()->create(['criticidad_declarada' => 'alta']);

    $this->respuestas = fn (string $resultado = 'cumple'): array => $this->clausulas
        ->mapWithKeys(fn (ClausulaContractual $clausula): array => [$clausula->id => ['resultado' => $resultado, 'nota' => null]])
        ->all();

    $this->evaluar = fn (array $datos) => $this->actingAs($this->responsable)
        ->post("/proveedores/{$this->proveedor->id}/evaluaciones", $datos);
});

it('apto homologa y la próxima toca según la criticidad', function (): void {
    ($this->evaluar)(['fecha' => '2026-09-01', 'resultado' => 'apto', 'clausulas' => ($this->respuestas)()])
        ->assertSessionHasNoErrors();

    $proveedor = $this->proveedor->fresh();

    expect($proveedor?->estado)->toBe(EstadoProveedor::Homologado)
        // Criticidad alta: 12 meses por defecto.
        ->and($proveedor?->proxima_evaluacion?->toDateString())->toBe('2027-09-01')
        ->and(ProveedorEvaluacion::query()->firstOrFail()->clausulas()->count())->toBe(3);
});

it('cada estado sale de su resultado', function (string $resultado, EstadoProveedor $estado): void {
    ($this->evaluar)([
        'fecha' => today()->toDateString(),
        'resultado' => $resultado,
        'conclusiones' => 'Falta el encargo de tratamiento.',
        'clausulas' => ($this->respuestas)('no_cumple'),
    ])->assertSessionHasNoErrors();

    expect($this->proveedor->fresh()?->estado)->toBe($estado);
})->with([
    ['apto_con_condiciones', EstadoProveedor::Condicionado],
    ['no_apto', EstadoProveedor::Rechazado],
]);

it('todas las cláusulas vigentes llevan respuesta', function (): void {
    $respuestas = ($this->respuestas)();
    array_pop($respuestas);

    ($this->evaluar)(['fecha' => today()->toDateString(), 'resultado' => 'apto', 'clausulas' => $respuestas])
        ->assertSessionHasErrors();

    expect(ProveedorEvaluacion::query()->count())->toBe(0);
});

it('apto con una cláusula que no se cumple no se admite', function (): void {
    $respuestas = ($this->respuestas)();
    $respuestas[array_key_first($respuestas)]['resultado'] = 'no_cumple';

    ($this->evaluar)(['fecha' => today()->toDateString(), 'resultado' => 'apto', 'clausulas' => $respuestas])
        ->assertSessionHasErrors('resultado');

    expect($this->proveedor->fresh()?->estado)->toBe(EstadoProveedor::EnEvaluacion);
});

it('lo que no es apto sin más necesita conclusiones', function (): void {
    ($this->evaluar)(['fecha' => today()->toDateString(), 'resultado' => 'no_apto', 'clausulas' => ($this->respuestas)('no_cumple')])
        ->assertSessionHasErrors('conclusiones');
});

it('un proveedor retirado no se evalúa', function (): void {
    $this->proveedor->forceFill(['estado' => 'retirado'])->save();

    ($this->evaluar)(['fecha' => today()->toDateString(), 'resultado' => 'apto', 'clausulas' => ($this->respuestas)()])
        ->assertSessionHasErrors('resultado');
});

it('una evaluación registrada no se edita', function (): void {
    ($this->evaluar)(['fecha' => today()->toDateString(), 'resultado' => 'apto', 'clausulas' => ($this->respuestas)()]);

    $evaluacion = ProveedorEvaluacion::query()->firstOrFail();

    expect(fn () => $evaluacion->update(['resultado' => 'no_apto']))->toThrow(RuntimeException::class);
});

it('cambiar la política de la organización mueve la próxima fecha', function (): void {
    ($this->evaluar)(['fecha' => '2026-09-01', 'resultado' => 'apto', 'clausulas' => ($this->respuestas)()]);

    $this->actingAs($this->responsable)->put('/organizacion', [
        'nombre' => $this->organizacion->nombre,
        'sujeto_obligado_ens' => false,
        'proveedor_sector_publico' => false,
        'reevaluacion_proveedor_alta_meses' => 6,
        'reevaluacion_proveedor_media_meses' => 24,
        'reevaluacion_proveedor_baja_meses' => 36,
    ])->assertSessionHasNoErrors();

    expect($this->proveedor->fresh()?->proxima_evaluacion?->toDateString())->toBe('2027-03-01');
});

it('una reevaluación que confirma el estado no ensucia el histórico', function (): void {
    ($this->evaluar)(['fecha' => '2026-01-01', 'resultado' => 'apto', 'clausulas' => ($this->respuestas)()]);
    ($this->evaluar)(['fecha' => '2026-09-01', 'resultado' => 'apto', 'clausulas' => ($this->respuestas)()]);

    $proveedor = $this->proveedor->fresh();

    // Alta + homologado; la segunda evaluación sólo mueve la fecha.
    expect($proveedor?->transiciones()->count())->toBe(1)
        ->and($proveedor?->proxima_evaluacion?->toDateString())->toBe('2027-09-01');
});
