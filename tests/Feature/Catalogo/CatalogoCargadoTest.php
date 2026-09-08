<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\EstadoMarco;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\AplicabilidadEns;
use App\Domain\Catalogo\Models\Mapeo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use Illuminate\Support\Facades\DB;

/**
 * Integridad del catálogo real que se versiona en `catalogo/`.
 *
 * Estos tests no prueban el importador (eso es ImportadorCatalogoTest): prueban
 * que el CONTENIDO cargado es coherente. Un error aquí significa que el YAML
 * está mal, y el YAML es lo que decide qué se le exige a cada organización.
 */
beforeEach(function (): void {
    $importador = app(ImportadorCatalogo::class);

    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        $importador->importar($fichero);
    }
});

it('carga los dos marcos vigentes', function (): void {
    expect(Marco::query()->pluck('codigo')->sort()->values()->all())
        ->toBe(['ENS-RD311-2022', 'ISO27001-2022']);

    expect(Marco::query()->where('codigo', 'ISO27001-2022')->value('estado'))->toBe(EstadoMarco::Vigente);
});

it('carga los 93 controles del Anexo A repartidos en cuatro temas', function (): void {
    $porTema = DB::table('requisitos as hijo')
        ->join('requisitos as tema', 'tema.id', '=', 'hijo.parent_id')
        ->join('marcos', 'marcos.id', '=', 'hijo.marco_id')
        ->where('marcos.codigo', 'ISO27001-2022')
        ->whereIn('tema.codigo', ['A.5', 'A.6', 'A.7', 'A.8'])
        ->groupBy('tema.codigo')
        ->selectRaw('tema.codigo as tema, count(*) as total')
        ->pluck('total', 'tema')
        ->map(fn ($total): int => (int) $total)
        ->all();

    expect($porTema)->toBe([
        'A.5' => 37,   // organizativos
        'A.6' => 8,    // personas
        'A.7' => 14,   // físicos
        'A.8' => 34,   // tecnológicos
    ]);

    expect(array_sum($porTema))->toBe(93);
});

it('carga las cláusulas 4 a 10 del cuerpo de la ISO', function (): void {
    $raices = Requisito::query()
        ->delMarco('ISO27001-2022')
        ->whereNull('parent_id')
        ->where('tipo', 'clausula')
        ->pluck('codigo')
        ->all();

    expect($raices)->toEqualCanonicalizing(['4', '5', '6', '7', '8', '9', '10']);

    // 6.3 es nueva en la revisión de 2022 y el auditor la pregunta.
    expect(Requisito::query()->delMarco('ISO27001-2022')->where('codigo', '6.3')->exists())->toBeTrue();
});

it('asigna los cinco atributos de la ISO 27002 a todos los controles del Anexo A', function (): void {
    $sinAtributos = Requisito::query()
        ->delMarco('ISO27001-2022')
        ->whereIn('parent_id', function ($query): void {
            $query->select('id')->from('requisitos')->whereIn('codigo', ['A.5', 'A.6', 'A.7', 'A.8']);
        })
        ->get()
        ->filter(function (Requisito $control): bool {
            $claves = ['tipo_control', 'propiedades_seguridad', 'conceptos_ciberseguridad', 'capacidades_operativas', 'dominios_seguridad'];

            foreach ($claves as $clave) {
                if (! isset($control->atributos[$clave]) || $control->atributos[$clave] === []) {
                    return true;
                }
            }

            return false;
        })
        ->pluck('codigo');

    expect($sinAtributos)->toBeEmpty("Controles sin los cinco atributos: {$sinAtributos->implode(', ')}");
});

it('los atributos quedan consultables desde el índice GIN de JSONB', function (): void {
    $preventivos = DB::table('requisitos')
        ->whereRaw("atributos->'tipo_control' @> ?::jsonb", ['"preventivo"'])
        ->count();

    expect($preventivos)->toBeGreaterThan(50);
});

it('carga los tres marcos del Anexo II del ENS', function (): void {
    $raices = Requisito::query()
        ->delMarco('ENS-RD311-2022')
        ->whereNull('parent_id')
        ->orderBy('orden')
        ->pluck('codigo')
        ->all();

    expect($raices)->toBe(['org', 'op', 'mp']);
});

it('toda medida hoja del ENS tiene aplicabilidad para las tres categorías', function (): void {
    $hojas = Requisito::query()
        ->delMarco('ENS-RD311-2022')
        ->whereNotExists(function ($query): void {
            $query->select(DB::raw(1))
                ->from('requisitos as hijos')
                ->whereColumn('hijos.parent_id', 'requisitos.id');
        })
        ->get();

    expect($hojas)->not->toBeEmpty();

    $incompletas = $hojas->filter(
        fn (Requisito $medida): bool => $medida->aplicabilidad()->count() !== 3
    )->pluck('codigo');

    expect($incompletas)->toBeEmpty("Medidas sin las tres categorías: {$incompletas->implode(', ')}");
});

it('la exigencia de cada celda es no_aplica, aplica o un refuerzo', function (): void {
    $exigencias = AplicabilidadEns::query()->distinct()->pluck('exigencia')->map(strval(...))->all();

    foreach ($exigencias as $exigencia) {
        expect($exigencia === 'no_aplica' || $exigencia === 'aplica' || preg_match('/^R[0-9]+$/', $exigencia) === 1)
            ->toBeTrue("Exigencia no reconocida: {$exigencia}");
    }
});

it('la exigencia nunca decrece al subir de categoría', function (): void {
    $peso = fn (string $exigencia): int => match (true) {
        $exigencia === 'no_aplica' => 0,
        $exigencia === 'aplica' => 1,
        default => 1 + (int) substr($exigencia, 1),
    };

    $porRequisito = AplicabilidadEns::query()
        ->get(['requisito_id', 'categoria', 'exigencia'])
        ->groupBy('requisito_id');

    $incoherentes = [];

    foreach ($porRequisito as $requisitoId => $celdas) {
        $valores = $celdas->keyBy(fn ($celda): string => $celda->categoria->value);

        $basica = $peso((string) $valores['basica']->exigencia);
        $media = $peso((string) $valores['media']->exigencia);
        $alta = $peso((string) $valores['alta']->exigencia);

        if ($basica > $media || $media > $alta) {
            $incoherentes[] = Requisito::query()->whereKey($requisitoId)->value('codigo');
        }
    }

    expect($incoherentes)->toBeEmpty('Medidas cuya exigencia decrece al subir de categoría: '.implode(', ', $incoherentes));
});

it('una medida modulada por dimensión lo está en las tres categorías', function (): void {
    $moduladas = AplicabilidadEns::query()
        ->whereNotNull('dimension_moduladora')
        ->get()
        ->groupBy('requisito_id');

    expect($moduladas)->not->toBeEmpty();

    foreach ($moduladas as $requisitoId => $celdas) {
        $codigo = Requisito::query()->whereKey($requisitoId)->value('codigo');

        expect($celdas)->toHaveCount(3, "La medida {$codigo} está modulada solo en algunas categorías.");
        expect($celdas->pluck('dimension_moduladora')->unique())
            ->toHaveCount(1, "La medida {$codigo} usa dimensiones moduladoras distintas según la categoría.");
    }
});

it('todos los mapeos apuntan a requisitos existentes de marcos distintos', function (): void {
    expect(Mapeo::query()->count())->toBeGreaterThan(0);

    $cruzados = Mapeo::query()
        ->join('requisitos as origen', 'origen.id', '=', 'mapeos.requisito_origen_id')
        ->join('requisitos as destino', 'destino.id', '=', 'mapeos.requisito_destino_id')
        ->whereColumn('origen.marco_id', 'destino.marco_id')
        ->count();

    expect($cruzados)->toBe(0, 'Hay mapeos entre requisitos del mismo marco.');
});

it('la CTE recursiva devuelve el árbol completo op -> op.acc -> op.acc.4', function (): void {
    $raiz = Requisito::query()->delMarco('ENS-RD311-2022')->where('codigo', 'op')->firstOrFail();

    $subarbol = Requisito::subarbol($raiz->id);
    $codigos = $subarbol->pluck('codigo');

    expect($codigos)->toContain('op', 'op.acc', 'op.acc.4', 'op.cont.2')
        ->and($subarbol->firstWhere('codigo', 'op')->profundidad)->toBe(0)
        ->and($subarbol->firstWhere('codigo', 'op.acc')->profundidad)->toBe(1)
        ->and($subarbol->firstWhere('codigo', 'op.acc.4')->profundidad)->toBe(2);
});

it('la ruta de un requisito va de la raíz a la hoja', function (): void {
    $medida = Requisito::query()->delMarco('ENS-RD311-2022')->where('codigo', 'op.acc.4')->firstOrFail();

    expect(Requisito::ruta($medida->id)->pluck('codigo')->all())
        ->toBe(['op', 'op.acc', 'op.acc.4']);
});

it('reimportar el catálogo completo no produce ningún cambio', function (): void {
    $importador = app(ImportadorCatalogo::class);

    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        expect($importador->importar($fichero)->hayCambios())
            ->toBeFalse('El fichero '.basename($fichero).' no es idempotente.');
    }
});
