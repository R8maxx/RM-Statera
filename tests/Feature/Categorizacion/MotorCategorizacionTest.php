<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Models\AplicabilidadEns;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\PerfilCumplimiento;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Categorizacion\MotorCategorizacion;
use Illuminate\Support\Facades\DB;

/**
 * Motor de categorización ENS. Prioridad 1 de cobertura (§11 del stack): un
 * fallo silencioso aquí deja a una organización fuera de conformidad sin que
 * nadie se entere.
 *
 * El catálogo de estos tests es SINTÉTICO a propósito. Los YAML reales llevan
 * `revisado: false` y su matriz cambiará al contrastarla con el BOE; un test
 * atado a sus cifras se pondría rojo por una corrección legítima del catálogo.
 */
beforeEach(function (): void {
    $this->marco = Marco::factory()->create(['codigo' => 'ENS-SINTETICO']);
    $this->motor = app(MotorCategorizacion::class);

    /**
     * Alta de una medida con su fila de matriz para cada categoría.
     *
     * @param  array<string, string>  $celdas  Exigencia por categoría.
     */
    $this->medida = function (string $codigo, array $celdas, ?Dimension $dimension = null): Requisito {
        $requisito = Requisito::factory()
            ->conCodigo($codigo)
            ->create(['marco_id' => $this->marco->id, 'titulo' => "Medida {$codigo}"]);

        foreach ($celdas as $categoria => $exigencia) {
            $fabrica = AplicabilidadEns::factory()->para(CategoriaEns::from($categoria), $exigencia);

            if ($dimension !== null) {
                $fabrica = $fabrica->moduladaPor($dimension);
            }

            $fabrica->create(['requisito_id' => $requisito->id]);
        }

        return $requisito;
    };

    // Un nodo de agrupación: es `tipo: medida` en el catálogo igual que una
    // medida real, pero no tiene matriz. Nunca debe salir en el conjunto.
    $this->grupo = Requisito::factory()->conCodigo('grupo')->create(['marco_id' => $this->marco->id]);

    ($this->medida)('org.1', ['basica' => 'aplica', 'media' => 'aplica', 'alta' => 'R2']);
    ($this->medida)('op.pl.1', ['basica' => 'no_aplica', 'media' => 'aplica', 'alta' => 'R1']);
    ($this->medida)('op.acc.5', ['basica' => 'aplica', 'media' => 'R1', 'alta' => 'R2']);
    ($this->medida)('mp.s.9', ['basica' => 'no_aplica', 'media' => 'no_aplica', 'alta' => 'no_aplica']);

    // Moduladas por dimensión: las lee por el nivel de SU dimensión, no por la
    // categoría global del sistema.
    ($this->medida)('op.cont.2', ['basica' => 'no_aplica', 'media' => 'aplica', 'alta' => 'R1'], Dimension::Disponibilidad);
    ($this->medida)('op.exp.10', ['basica' => 'no_aplica', 'media' => 'no_aplica', 'alta' => 'aplica'], Dimension::Trazabilidad);
});

it('devuelve conjunto vacío cuando las cinco dimensiones son na', function (): void {
    $conjunto = $this->motor->calcular($this->marco, uniforme(NivelDimension::Na));

    expect($conjunto->estaVacio())->toBeTrue()
        ->and($conjunto->count())->toBe(0);
});

it('reproduce la matriz completa para cada categoría', function (NivelDimension $nivel, CategoriaEns $categoria): void {
    $conjunto = $this->motor->calcular($this->marco, uniforme($nivel));

    // Con las cinco dimensiones al mismo nivel, las medidas moduladas resuelven
    // en esa misma categoría, así que el conjunto tiene que coincidir celda a
    // celda con la matriz de esa categoría, quitando las que no aplican.
    $esperadas = DB::table('aplicabilidad_ens')
        ->join('requisitos', 'requisitos.id', '=', 'aplicabilidad_ens.requisito_id')
        ->where('requisitos.marco_id', $this->marco->id)
        ->where('aplicabilidad_ens.categoria', $categoria->value)
        ->where('aplicabilidad_ens.exigencia', '!=', 'no_aplica')
        ->pluck('aplicabilidad_ens.exigencia', 'requisitos.codigo')
        ->all();

    $obtenidas = [];

    foreach ($conjunto as $codigo => $medida) {
        $obtenidas[$codigo] = (string) $medida->exigencia;
    }

    ksort($esperadas);
    ksort($obtenidas);

    expect($obtenidas)->toBe($esperadas);
})->with([
    'básica' => [NivelDimension::Bajo, CategoriaEns::Basica],
    'media' => [NivelDimension::Medio, CategoriaEns::Media],
    'alta' => [NivelDimension::Alto, CategoriaEns::Alta],
]);

it('deriva la categoría del máximo de las cinco dimensiones', function (): void {
    // Sólo la disponibilidad sube: la categoría es media aunque el resto sea bajo.
    $conjunto = $this->motor->calcular($this->marco, valoracion([
        'C' => 'bajo', 'I' => 'bajo', 'D' => 'medio', 'A' => 'na', 'T' => 'na',
    ]));

    expect((string) $conjunto->paraCodigo('op.pl.1')->exigencia)->toBe('aplica')
        ->and((string) $conjunto->paraCodigo('op.acc.5')->exigencia)->toBe('R1');
});

it('nunca exige una medida cuya celda es no_aplica en las tres categorías', function (): void {
    foreach ([NivelDimension::Bajo, NivelDimension::Medio, NivelDimension::Alto] as $nivel) {
        expect($this->motor->calcular($this->marco, uniforme($nivel))->exige('mp.s.9'))->toBeFalse();
    }
});

it('ignora los nodos de agrupación, que no tienen matriz', function (): void {
    $conjunto = $this->motor->calcular($this->marco, uniforme(NivelDimension::Alto));

    expect($conjunto->exige('grupo'))->toBeFalse();
});

it('excluye los requisitos retirados del catálogo', function (): void {
    Requisito::query()->where('codigo', 'org.1')->update(['vigente' => false]);

    $conjunto = $this->motor->calcular($this->marco, uniforme(NivelDimension::Alto));

    expect($conjunto->exige('org.1'))->toBeFalse()
        ->and($conjunto->exige('op.acc.5'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Modulación por nivel de dimensión
|--------------------------------------------------------------------------
|
| El caso que se cuela en cualquier implementación ingenua: una medida modulada
| se lee por el nivel de SU dimensión, no por la categoría global del sistema.
|
*/

it('no exige una medida modulada cuando su dimensión es na, por alta que sea la categoría', function (): void {
    // Categoría alta por confidencialidad, pero sin disponibilidad ni trazabilidad.
    $conjunto = $this->motor->calcular($this->marco, valoracion([
        'C' => 'alto', 'I' => 'alto', 'D' => 'na', 'A' => 'na', 'T' => 'na',
    ]));

    expect($conjunto->exige('op.cont.2'))->toBeFalse()
        ->and($conjunto->exige('op.exp.10'))->toBeFalse()
        // Las no moduladas sí se exigen, y al nivel de la categoría alta.
        ->and((string) $conjunto->paraCodigo('org.1')->exigencia)->toBe('R2');
});

it('exige una medida modulada al nivel de su dimensión, no al de la categoría', function (): void {
    // Categoría alta por confidencialidad; la disponibilidad se queda en medio.
    $conjunto = $this->motor->calcular($this->marco, valoracion([
        'C' => 'alto', 'I' => 'bajo', 'D' => 'medio', 'A' => 'na', 'T' => 'na',
    ]));

    // La categoría global es alta, pero op.cont.2 se lee en la fila de media.
    expect((string) $conjunto->paraCodigo('op.cont.2')->exigencia)->toBe('aplica')
        ->and($conjunto->paraCodigo('op.cont.2')->origen)->toBe(OrigenExigencia::ModulacionDimension)
        ->and($conjunto->paraCodigo('op.cont.2')->dimensionModuladora)->toBe(Dimension::Disponibilidad);
});

it('exige una medida modulada aunque la categoría global sea básica', function (): void {
    // Sistema de categoría alta que lo es precisamente por la trazabilidad.
    $conjunto = $this->motor->calcular($this->marco, valoracion([
        'C' => 'bajo', 'I' => 'bajo', 'D' => 'na', 'A' => 'na', 'T' => 'alto',
    ]));

    expect((string) $conjunto->paraCodigo('op.exp.10')->exigencia)->toBe('aplica')
        ->and($conjunto->exige('op.cont.2'))->toBeFalse();
});

it('marca el origen de cada exigencia', function (): void {
    $conjunto = $this->motor->calcular($this->marco, uniforme(NivelDimension::Alto));

    expect($conjunto->paraCodigo('org.1')->origen)->toBe(OrigenExigencia::Categoria)
        ->and($conjunto->paraCodigo('org.1')->dimensionModuladora)->toBeNull()
        ->and($conjunto->paraCodigo('op.cont.2')->origen)->toBe(OrigenExigencia::ModulacionDimension);
});

/*
|--------------------------------------------------------------------------
| Refuerzos y monotonía
|--------------------------------------------------------------------------
*/

it('devuelve el nivel de refuerzo exigido', function (): void {
    $conjunto = $this->motor->calcular($this->marco, uniforme(NivelDimension::Alto));

    $medida = $conjunto->paraCodigo('op.acc.5');

    expect($medida->nivelRefuerzo())->toBe(2)
        ->and($medida->exigencia->esAplicable())->toBeTrue()
        ->and((string) $medida->exigencia)->toBe('R2');
});

it('subir una dimensión nunca reduce el conjunto ni rebaja una exigencia', function (): void {
    $escalones = [NivelDimension::Na, NivelDimension::Bajo, NivelDimension::Medio, NivelDimension::Alto];

    foreach (Dimension::cases() as $dimension) {
        $anterior = null;

        foreach ($escalones as $nivel) {
            $valoracion = valoracion(['C' => 'bajo', 'I' => 'bajo', 'D' => 'na', 'A' => 'na', 'T' => 'na'])
                ->conNivel($dimension, $nivel);

            $conjunto = $this->motor->calcular($this->marco, $valoracion);

            if ($anterior !== null) {
                foreach ($anterior as $codigo => $medida) {
                    expect($conjunto->exige($codigo))
                        ->toBeTrue("Subir {$dimension->value} a {$nivel->value} hizo desaparecer {$codigo}.");

                    expect($conjunto->paraCodigo($codigo)->exigencia->peso())
                        ->toBeGreaterThanOrEqual(
                            $medida->exigencia->peso(),
                            "Subir {$dimension->value} a {$nivel->value} rebajó la exigencia de {$codigo}."
                        );
                }
            }

            $anterior = $conjunto;
        }
    }
});

/*
|--------------------------------------------------------------------------
| Perfiles de cumplimiento
|--------------------------------------------------------------------------
*/

it('interseca con el perfil: lo que no está en él no se exige', function (): void {
    $perfil = PerfilCumplimiento::factory()->create(['marco_id' => $this->marco->id]);
    $perfil->requisitos()->attach(Requisito::query()->where('codigo', 'org.1')->value('id'));

    $conjunto = $this->motor->calcular($this->marco, uniforme(NivelDimension::Alto), $perfil);

    expect($conjunto->codigos())->toBe(['org.1'])
        ->and((string) $conjunto->paraCodigo('org.1')->exigencia)->toBe('R2');
});

it('un perfil endurece la exigencia que dicta la categoría', function (): void {
    $perfil = PerfilCumplimiento::factory()->create(['marco_id' => $this->marco->id]);
    $perfil->requisitos()->attach(
        Requisito::query()->where('codigo', 'op.pl.1')->value('id'),
        ['exigencia' => 'R3'],
    );

    // En categoría media, op.pl.1 sería `aplica`; el perfil lo sube a R3.
    $conjunto = $this->motor->calcular($this->marco, uniforme(NivelDimension::Medio), $perfil);

    expect((string) $conjunto->paraCodigo('op.pl.1')->exigencia)->toBe('R3')
        ->and($conjunto->paraCodigo('op.pl.1')->origen)->toBe(OrigenExigencia::Perfil);
});

it('un perfil nunca rebaja lo que impone la categoría', function (): void {
    $perfil = PerfilCumplimiento::factory()->create(['marco_id' => $this->marco->id]);
    $perfil->requisitos()->attach(
        Requisito::query()->where('codigo', 'op.acc.5')->value('id'),
        ['exigencia' => 'aplica'],
    );

    // En categoría alta la matriz exige R2; el perfil dice `aplica` y pierde.
    $conjunto = $this->motor->calcular($this->marco, uniforme(NivelDimension::Alto), $perfil);

    expect((string) $conjunto->paraCodigo('op.acc.5')->exigencia)->toBe('R2')
        ->and($conjunto->paraCodigo('op.acc.5')->origen)->toBe(OrigenExigencia::Categoria);
});

it('un perfil puede exigir una medida que la categoría dejaba fuera', function (): void {
    $perfil = PerfilCumplimiento::factory()->create(['marco_id' => $this->marco->id]);
    $perfil->requisitos()->attach(
        Requisito::query()->where('codigo', 'op.pl.1')->value('id'),
        ['exigencia' => 'R1'],
    );

    // En básica, op.pl.1 es `no_aplica`. El perfil la exige de todas formas.
    $conjunto = $this->motor->calcular($this->marco, uniforme(NivelDimension::Bajo), $perfil);

    expect((string) $conjunto->paraCodigo('op.pl.1')->exigencia)->toBe('R1')
        ->and($conjunto->paraCodigo('op.pl.1')->origen)->toBe(OrigenExigencia::Perfil);
});

it('agrupa el conjunto por familia del Anexo II', function (): void {
    $familias = $this->motor->calcular($this->marco, uniforme(NivelDimension::Alto))->agrupadoPorFamilia();

    expect(array_keys($familias))->toEqualCanonicalizing(['org', 'op']);
});
