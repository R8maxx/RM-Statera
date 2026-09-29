<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Proveedor\Enums\DatoDeFicha;
use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Models\ClausulaContractual;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Proveedor\PendientesDeProveedor;

/*
|--------------------------------------------------------------------------
| Lo que falta para homologar a un proveedor (§ 4.9)
|--------------------------------------------------------------------------
|
| El bloque de arriba de la ficha: la condición de la última evaluación con sus
| tareas, los certificados caducados y los datos de la ficha que no casan con
| lo evaluado. Y que evaluar no tiene que esperar a la fecha.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->ubicacion = ClausulaContractual::factory()->create(['codigo' => 'CLX-UBI', 'dato_de_ficha' => DatoDeFicha::UbicacionDatos]);
    $this->encargo = ClausulaContractual::factory()->create(['codigo' => 'CLX-ENC', 'dato_de_ficha' => DatoDeFicha::EncargadoTratamiento]);
    $this->otra = ClausulaContractual::factory()->create(['codigo' => 'CLX-OTR']);

    $this->evaluar = function (Proveedor $proveedor, array $respuestas, string $resultado = 'apto', string $fecha = '2026-09-01') {
        $clausulas = collect([$this->ubicacion, $this->encargo, $this->otra])
            ->mapWithKeys(fn (ClausulaContractual $clausula): array => [
                $clausula->id => ['resultado' => $respuestas[$clausula->codigo] ?? 'cumple', 'nota' => null],
            ])->all();

        return $this->actingAs($this->responsable)->post("/proveedores/{$proveedor->id}/evaluaciones", [
            'fecha' => $fecha,
            'resultado' => $resultado,
            'conclusiones' => $resultado === 'apto' ? null : 'Falta el encargo de tratamiento.',
            'clausulas' => $clausulas,
        ]);
    };

    $this->pendiente = fn (Proveedor $proveedor): array => app(PendientesDeProveedor::class)->de($proveedor->fresh() ?? $proveedor);
});

it('avisa de la ubicación sin determinar dada por cumplida, y sólo de eso', function (string $ubicacion, string $respuesta, bool $contradice): void {
    $proveedor = Proveedor::factory()->create(['ubicacion_datos' => $ubicacion, 'es_subencargado_rgpd' => true]);

    ($this->evaluar)($proveedor, ['CLX-UBI' => $respuesta], $respuesta === 'cumple' ? 'apto' : 'apto_con_condiciones')
        ->assertSessionHasNoErrors();

    expect(collect(($this->pendiente)($proveedor)['contradicciones'])->pluck('codigo')->all())
        ->toBe($contradice ? ['CLX-UBI'] : []);
})->with([
    'desconocida y cumple' => ['desconocida', 'cumple', true],
    'desconocida y no cumple' => ['desconocida', 'no_cumple', false],
    'en el EEE y cumple' => ['ue_eee', 'cumple', false],
]);

it('avisa del encargo exigido a quien no es encargado, y del saltado a quien lo es', function (bool $encargado, string $respuesta, bool $contradice): void {
    $proveedor = Proveedor::factory()->create(['es_subencargado_rgpd' => $encargado]);

    ($this->evaluar)($proveedor, ['CLX-ENC' => $respuesta], $respuesta === 'no_cumple' ? 'apto_con_condiciones' : 'apto')
        ->assertSessionHasNoErrors();

    expect(collect(($this->pendiente)($proveedor)['contradicciones'])->pluck('codigo')->all())
        ->toBe($contradice ? ['CLX-ENC'] : []);
})->with([
    'no encargado y se le exige' => [false, 'no_cumple', true],
    'no encargado y no aplica' => [false, 'no_aplica', false],
    'encargado y no aplica' => [true, 'no_aplica', true],
    'encargado y cumple' => [true, 'cumple', false],
]);

it('la condición de la última evaluación trae lo que no se cumple y sus tareas abiertas', function (): void {
    $proveedor = Proveedor::factory()->create(['es_subencargado_rgpd' => true]);

    ($this->evaluar)($proveedor, ['CLX-ENC' => 'no_cumple'], 'apto_con_condiciones')->assertSessionHasNoErrors();

    $this->actingAs($this->responsable)->post("/proveedores/{$proveedor->id}/tareas", [
        'titulo' => 'Firmar el encargo',
        'prioridad' => 'alta',
        'responsable_id' => $this->responsable->id,
        'fecha_limite' => today()->addMonth()->toDateString(),
    ])->assertSessionHasNoErrors();

    $pendiente = ($this->pendiente)($proveedor);

    expect($pendiente['condicion']['incumplidas'] ?? null)->toHaveCount(1)
        ->and($pendiente['condicion']['conclusiones'] ?? null)->toBe('Falta el encargo de tratamiento.')
        ->and($pendiente['tareasAbiertas'][0]['responsable'] ?? null)->toBe($this->responsable->name)
        ->and($pendiente['tareasAbiertas'][0]['plazo']['etiqueta'] ?? null)->toBe('En plazo')
        ->and($pendiente['total'])->toBe(1);
});

it('apto y con los certificados vigentes, no hay nada pendiente', function (): void {
    $proveedor = Proveedor::factory()->create(['es_subencargado_rgpd' => true]);

    ($this->evaluar)($proveedor, [])->assertSessionHasNoErrors();

    expect(($this->pendiente)($proveedor))
        ->condicion->toBeNull()
        ->total->toBe(0);
});

it('el certificado caducado sube al bloque, y lo retirado no tiene nada pendiente', function (): void {
    $proveedor = Proveedor::factory()->create();
    $proveedor->certificaciones()->create(['tipo' => 'iso27001', 'caduca_en' => today()->subMonth()->toDateString()]);

    expect(($this->pendiente)($proveedor))
        ->sinEvaluar->toBeTrue()
        ->certificacionesCaducadas->toHaveCount(1)
        ->total->toBe(2);

    $this->actingAs($this->responsable)
        ->post("/proveedores/{$proveedor->id}/retirar", ['motivo' => 'Fin del contrato.'])
        ->assertSessionHasNoErrors();

    expect($proveedor->fresh()?->estado)->toBe(EstadoProveedor::Retirado);

    expect(($this->pendiente)($proveedor)['total'])->toBe(0);
});

it('un condicionado se evalúa antes de su fecha y queda homologado', function (): void {
    $proveedor = Proveedor::factory()->create(['es_subencargado_rgpd' => true]);

    ($this->evaluar)($proveedor, ['CLX-ENC' => 'no_cumple'], 'apto_con_condiciones', today()->subMonth()->toDateString())
        ->assertSessionHasNoErrors();

    expect($proveedor->fresh()?->proxima_evaluacion?->isFuture())->toBeTrue();

    ($this->evaluar)($proveedor, [], 'apto', today()->toDateString())->assertSessionHasNoErrors();

    expect($proveedor->fresh()?->estado)->toBe(EstadoProveedor::Homologado);
});

it('cada contradicción dice qué pone la ficha, qué se contestó y las dos salidas', function (): void {
    $proveedor = Proveedor::factory()->create(['es_subencargado_rgpd' => false]);

    ($this->evaluar)($proveedor, ['CLX-ENC' => 'no_cumple'], 'apto_con_condiciones')->assertSessionHasNoErrors();

    expect(($this->pendiente)($proveedor)['contradicciones'][0] ?? null)
        ->valor->toBe('No')
        ->resultado->toBe('No cumple')
        ->motivo->toContain('Trata datos personales por cuenta de la organización')
        ->sugerencia->toContain('evaluar otra vez');
});

it('la pantalla de evaluar enseña lo que dice la ficha junto a las cláusulas que lo contrastan', function (): void {
    $proveedor = Proveedor::factory()->create(['ubicacion_datos' => 'desconocida']);

    $this->actingAs($this->responsable)->get("/proveedores/{$proveedor->id}/evaluar")
        ->assertInertia(fn ($pagina) => $pagina
            ->where('ficha.ubicacion_datos.valor', 'Sin determinar')
            ->where('ficha.encargado_tratamiento.valor', 'No'));
});

it('la ficha manda el bloque de pendientes', function (): void {
    $proveedor = Proveedor::factory()->create(['ubicacion_datos' => 'desconocida']);

    ($this->evaluar)($proveedor, [])->assertSessionHasNoErrors();

    $this->actingAs($this->responsable)->get("/proveedores/{$proveedor->id}")
        ->assertInertia(fn ($pagina) => $pagina
            ->component('proveedores/Ficha')
            ->where('pendiente.contradicciones.0.clave', 'encargado_tratamiento')
            ->where('pendiente.contradicciones.1.clave', 'ubicacion_datos'));
});
