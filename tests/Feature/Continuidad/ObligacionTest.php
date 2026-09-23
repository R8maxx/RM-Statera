<?php

declare(strict_types=1);

use App\Domain\Catalogo\Excepciones\CatalogoInvalido;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Models\Documento;
use App\Domain\Obligacion\Enums\ReferenciaCumplimiento;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\Obligacion;
use App\Domain\Obligacion\ObligacionesAplicables;
use App\Domain\Obligacion\Referencia;
use App\Domain\Sistema\AplicarValoracion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\QueryException;
use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| Tarea 9: la obligación anual, derivada del requisito
|--------------------------------------------------------------------------
|
| `ens.pruebas-continuidad` deja de leer `categoria_minima` y pasa a declarar
| `requisito: op.cont.3`. En el Anexo II esa medida está modulada por
| Disponibilidad, así que lo que la propone no es la categoría global del
| sistema sino que D llegue a alto — con el catálogo REAL, para que un test
| verde de aquí signifique algo sobre lo que un auditor va a ver.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $importador = app(ImportadorCatalogo::class);
    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        $importador->importar($fichero);
    }

    $this->ens = Marco::query()->where('codigo', 'ENS-RD311-2022')->firstOrFail();
    $this->organizacion->update(['sujeto_obligado_ens' => true]);
});

it('propone las pruebas a un sistema con disponibilidad alta', function (): void {
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->ens)->create();

    app(AplicarValoracion::class)->aplicar(
        $sistema,
        valoracion(['C' => 'bajo', 'I' => 'bajo', 'D' => 'alto', 'A' => 'bajo', 'T' => 'bajo']),
        [],
    );

    expect(app(ObligacionesAplicables::class)->para($this->organizacion->fresh())->pluck('codigo'))
        ->toContain('ens.pruebas-continuidad');
});

it('no las propone a un sistema alto sólo en confidencialidad', function (): void {
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->ens)->create();

    app(AplicarValoracion::class)->aplicar(
        $sistema,
        valoracion(['C' => 'alto', 'I' => 'bajo', 'D' => 'bajo', 'A' => 'bajo', 'T' => 'bajo']),
        [],
    );

    expect(app(ObligacionesAplicables::class)->para($this->organizacion->fresh())->pluck('codigo'))
        ->not->toContain('ens.pruebas-continuidad');
});

it('el importador resuelve el requisito por clave natural y es idempotente', function (): void {
    $obligacion = Obligacion::query()->where('codigo', 'ens.pruebas-continuidad')->firstOrFail();
    $requisito = Requisito::query()->delMarco('ENS-RD311-2022')->where('codigo', 'op.cont.3')->firstOrFail();

    expect($obligacion->requisito_id)->toBe($requisito->id);

    $resultado = app(ImportadorCatalogo::class)->importar(base_path('catalogo/obligaciones.yaml'), simulacion: true);

    expect($resultado->modificados)->toBeEmpty()
        ->and($resultado->hayCambios())->toBeFalse();
});

it('un requisito inexistente hace fallar la importación con mensaje legible', function (): void {
    $fichero = tempnam(sys_get_temp_dir(), 'obl').'.yaml';
    file_put_contents($fichero, Yaml::dump([
        'obligaciones' => [[
            'codigo' => 'test.requisito-inexistente',
            'nombre' => 'Con un requisito que no existe',
            'marco' => 'ENS-RD311-2022',
            'requisito' => 'op.cont.no-existe',
            'periodicidad_meses' => 12,
        ]],
    ], 6));

    try {
        app(ImportadorCatalogo::class)->importar($fichero);

        $this->fail('Se esperaba CatalogoInvalido.');
    } catch (CatalogoInvalido $e) {
        expect($e->errores)->toContain('obligaciones[0]: requisito [op.cont.no-existe] no existe en el marco [ENS-RD311-2022].');
    } finally {
        unlink($fichero);
    }
});

it('una prueba realizada se registra como referencia del cumplimiento', function (): void {
    $compromiso = Compromiso::factory()->create();
    $prueba = PruebaContinuidad::factory()->realizada()->create();

    $cumplimiento = $compromiso->cumplimientos()->create([
        'fecha' => now()->subDay(),
        'cubre_hasta' => now()->addYear(),
        'prueba_continuidad_id' => $prueba->id,
    ]);

    $referencia = Referencia::de($cumplimiento);

    expect($referencia)->not->toBeNull()
        ->and($referencia->tipo)->toBe(ReferenciaCumplimiento::PruebaContinuidad)
        ->and($referencia->id)->toBe($prueba->id)
        ->and($referencia->url)->toBe("/continuidad/pruebas/{$prueba->id}");
});

it('un cumplimiento no puede citar prueba y documento a la vez', function (): void {
    $compromiso = Compromiso::factory()->create();
    $prueba = PruebaContinuidad::factory()->realizada()->create();
    $documento = Documento::factory()->politica()->create();

    expect(fn () => $compromiso->cumplimientos()->create([
        'fecha' => now()->subDay(),
        'cubre_hasta' => now()->addYear(),
        'prueba_continuidad_id' => $prueba->id,
        'documento_id' => $documento->id,
    ]))->toThrow(QueryException::class);
});

/*
 * Por HTTP, la referencia sólo admite una prueba realizada: una planificada o
 * cancelada no demuestra nada y `Referencia` la enlazaría igual como prueba.
 */
it('el formulario de cumplimiento sólo acepta una prueba realizada', function (): void {
    $compromiso = Compromiso::factory()->create();
    $plan = Documento::factory()->planContinuidad()->create();

    foreach ([
        PruebaContinuidad::factory()->deDocumento($plan)->planificada()->create(),
        PruebaContinuidad::factory()->deDocumento($plan)->cancelada()->create(),
    ] as $prueba) {
        $this->actingAs($this->usuario)
            ->post("/obligaciones/{$compromiso->id}/cumplimientos", [
                'fecha' => now()->subDay()->toDateString(),
                'prueba_continuidad_id' => $prueba->id,
            ])
            ->assertSessionHasErrors('prueba_continuidad_id');
    }

    expect($compromiso->cumplimientos()->count())->toBe(0);

    $realizada = PruebaContinuidad::factory()->deDocumento($plan)->realizada()->create();

    $this->actingAs($this->usuario)
        ->post("/obligaciones/{$compromiso->id}/cumplimientos", [
            'fecha' => now()->subDay()->toDateString(),
            'prueba_continuidad_id' => $realizada->id,
        ])
        ->assertSessionHasNoErrors();

    expect($compromiso->cumplimientos()->sole()->prueba_continuidad_id)->toBe($realizada->id);
});
