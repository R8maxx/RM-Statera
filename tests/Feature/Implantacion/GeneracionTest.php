<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Models\AplicabilidadEns;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;

/**
 * Generación del conjunto de implantaciones desde el catálogo.
 *
 * Catálogo sintético, como en los tests del motor: los YAML reales llevan
 * `revisado: false` y un test atado a sus cifras se pondría rojo por una
 * corrección legítima de la matriz.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->generador = app(GeneradorImplantaciones::class);

    // --- Marco con matriz: se comporta como el ENS ---------------------------
    $this->ens = Marco::factory()->create(['codigo' => 'ENS-SINTETICO']);

    $medida = function (string $codigo, array $celdas, ?Dimension $dimension = null): Requisito {
        $requisito = Requisito::factory()
            ->conCodigo($codigo)
            ->create(['marco_id' => $this->ens->id, 'titulo' => "Medida {$codigo}"]);

        foreach ($celdas as $categoria => $exigencia) {
            $fabrica = AplicabilidadEns::factory()->para(CategoriaEns::from($categoria), $exigencia);

            if ($dimension !== null) {
                $fabrica = $fabrica->moduladaPor($dimension);
            }

            $fabrica->create(['requisito_id' => $requisito->id]);
        }

        return $requisito;
    };

    // Nodo de agrupación: `tipo: medida` como los demás, pero sin matriz.
    Requisito::factory()->conCodigo('org')->create(['marco_id' => $this->ens->id]);

    $medida('org.1', ['basica' => 'aplica', 'media' => 'aplica', 'alta' => 'R2']);
    $medida('op.pl.1', ['basica' => 'no_aplica', 'media' => 'aplica', 'alta' => 'R1']);
    $medida('op.acc.5', ['basica' => 'aplica', 'media' => 'R1', 'alta' => 'R2']);
    $medida('op.cont.2', ['basica' => 'no_aplica', 'media' => 'aplica', 'alta' => 'R1'], Dimension::Disponibilidad);

    // --- Marco sin matriz: se comporta como ISO ------------------------------
    $this->iso = Marco::factory()->create(['codigo' => 'ISO-SINTETICO']);
    $tema = Requisito::factory()->conCodigo('A.5')->create(['marco_id' => $this->iso->id]);

    foreach (['A.5.1', 'A.5.2', 'A.5.3'] as $codigo) {
        Requisito::factory()->conCodigo($codigo)->hijoDe($tema)->control()->create();
    }

    $this->sistemaEns = function (array $niveles): Sistema {
        $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->ens)->create();

        foreach ($niveles as $codigo => $nivel) {
            ValoracionDimension::factory()
                ->para(Dimension::from($codigo), NivelDimension::from($nivel))
                ->create(['sistema_id' => $sistema->id]);
        }

        return $sistema;
    };
});

it('genera exactamente el conjunto que dicta el motor', function (): void {
    $sistema = ($this->sistemaEns)(['C' => 'medio', 'I' => 'bajo', 'D' => 'medio']);

    $resultado = $this->generador->generar($sistema);

    expect($resultado->creadas)->toEqualCanonicalizing(['org.1', 'op.pl.1', 'op.acc.5', 'op.cont.2'])
        ->and($resultado->categoria)->toBe('media')
        ->and($resultado->dejanDeAplicar)->toBeEmpty();

    $exigencias = Implantacion::query()
        ->with('requisito')
        ->get()
        ->mapWithKeys(fn (Implantacion $i): array => [$i->requisito->codigo => (string) $i->exigencia_calculada])
        ->all();

    expect($exigencias)->toBe([
        'op.acc.5' => 'R1',
        'op.cont.2' => 'aplica',
        'op.pl.1' => 'aplica',
        'org.1' => 'aplica',
    ]);
});

it('no genera implantación para los nodos de agrupación', function (): void {
    ($this->sistemaEns)(['C' => 'alto']);
    $this->generador->generar(Sistema::query()->firstOrFail());

    $codigos = Implantacion::query()->with('requisito')->get()->pluck('requisito.codigo');

    expect($codigos)->not->toContain('org');
});

it('todas nacen en no_iniciado y con su transición de alta', function (): void {
    $sistema = ($this->sistemaEns)(['C' => 'bajo']);
    $this->generador->generar($sistema);

    $implantaciones = Implantacion::query()->with('transiciones')->get();

    expect($implantaciones)->not->toBeEmpty();

    foreach ($implantaciones as $implantacion) {
        expect($implantacion->estado)->toBe(EstadoImplantacion::NoIniciado)
            ->and($implantacion->aplica)->toBeTrue()
            ->and($implantacion->transiciones)->toHaveCount(1)
            ->and($implantacion->transiciones->first()->esAlta())->toBeTrue()
            ->and($implantacion->transiciones->first()->estado_nuevo)->toBe(EstadoImplantacion::NoIniciado);
    }
});

it('guarda por qué se exige cada medida', function (): void {
    $sistema = ($this->sistemaEns)(['C' => 'alto', 'D' => 'medio']);
    $this->generador->generar($sistema);

    $porCodigo = Implantacion::query()->with('requisito')->get()
        ->keyBy(fn (Implantacion $i): string => $i->requisito->codigo);

    expect($porCodigo['org.1']->origen_exigencia)->toBe(OrigenExigencia::Categoria)
        ->and($porCodigo['org.1']->dimension_moduladora)->toBeNull()
        ->and($porCodigo['op.cont.2']->origen_exigencia)->toBe(OrigenExigencia::ModulacionDimension)
        ->and($porCodigo['op.cont.2']->dimension_moduladora)->toBe(Dimension::Disponibilidad)
        // Modulada por disponibilidad, que está en medio: `aplica`, no R1,
        // aunque la categoría del sistema sea alta.
        ->and((string) $porCodigo['op.cont.2']->exigencia_calculada)->toBe('aplica');
});

it('un sistema fuera del ámbito del ENS no genera nada', function (): void {
    $sistema = ($this->sistemaEns)(['C' => 'na', 'I' => 'na', 'D' => 'na']);

    $resultado = $this->generador->generar($sistema);

    expect($resultado->creadas)->toBeEmpty()
        ->and($resultado->categoria)->toBeNull()
        ->and(Implantacion::query()->count())->toBe(0);
});

it('volver a generar no produce ningún cambio', function (): void {
    $sistema = ($this->sistemaEns)(['C' => 'medio', 'D' => 'alto']);

    $this->generador->generar($sistema);
    $segunda = $this->generador->generar($sistema);

    expect($segunda->hayCambios())->toBeFalse()
        ->and($segunda->sinCambios)->toBe(4);
});

it('en simulación no escribe nada', function (): void {
    $sistema = ($this->sistemaEns)(['C' => 'alto']);

    $resultado = $this->generador->generar($sistema, simulacion: true);

    expect($resultado->creadas)->not->toBeEmpty()
        ->and(Implantacion::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Marco sin matriz: la estrategia de la Declaración de Aplicabilidad
|--------------------------------------------------------------------------
*/

it('en un marco sin matriz aplican todos los requisitos hoja', function (): void {
    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->iso)->create();

    $resultado = $this->generador->generar($sistema);

    expect($resultado->creadas)->toEqualCanonicalizing(['A.5.1', 'A.5.2', 'A.5.3'])
        // El tema es nodo de agrupación: no lleva implantación.
        ->and($resultado->creadas)->not->toContain('A.5');

    $implantaciones = Implantacion::query()->get();

    foreach ($implantaciones as $implantacion) {
        expect($implantacion->aplica)->toBeTrue()
            ->and((string) $implantacion->exigencia_calculada)->toBe('aplica')
            ->and($implantacion->origen_exigencia)->toBe(OrigenExigencia::Catalogo);
    }
});

it('un requisito retirado del catálogo no se genera', function (): void {
    Requisito::query()->where('codigo', 'A.5.2')->update(['vigente' => false]);

    $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->iso)->create();

    expect($this->generador->generar($sistema)->creadas)->toEqualCanonicalizing(['A.5.1', 'A.5.3']);
});
