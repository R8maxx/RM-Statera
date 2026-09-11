<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\DeclaracionAplicabilidadEns;
use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Sistema\AplicarValoracion;
use App\Domain\Sistema\Models\Sistema;

/**
 * Qué dice exactamente la Declaración de Aplicabilidad del ENS.
 *
 * La diferencia con la SoA es de naturaleza, no de formato: aquí la
 * aplicabilidad no la decide nadie, la deriva el motor desde la valoración de
 * las cinco dimensiones. Lo que el auditor comprueba es que esa derivación se
 * sostiene, así que lo que estos tests fijan es precisamente que el documento la
 * enseña entera.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $importador = app(ImportadorCatalogo::class);
    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        $importador->importar($fichero);
    }

    $this->ens = Marco::query()->where('codigo', 'ENS-RD311-2022')->firstOrFail();

    $this->declaracion = function (array $niveles): ContenidoDocumento {
        $sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->ens)->create([
            'alcance_declarado' => 'Sede electrónica y su plataforma de tramitación.',
        ]);

        app(AplicarValoracion::class)->aplicar(
            $sistema,
            valoracion($niveles),
            ['C' => 'Datos de carácter personal de nivel básico.', 'D' => 'Servicio con interrupciones tolerables.'],
        );

        $documento = Documento::factory()->dda()->paraSistema($sistema->id)->create();
        $version = DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(DeclaracionAplicabilidadEns::class)->construir($documento->fresh(), $version);
    };
});

it('deriva la categoría del máximo de las cinco dimensiones y lo imprime', function (): void {
    // D en medio arrastra la categoría entera: ése es el invariante 4.
    $contenido = ($this->declaracion)(['C' => 'bajo', 'I' => 'bajo', 'D' => 'medio', 'A' => 'bajo', 'T' => 'bajo']);

    expect($contenido->portada['categoria'])->toBe('Media');

    $derivacion = $contenido->extras['derivacion'];

    expect($derivacion['formula'])
        ->toContain('Categoría MEDIA = máximo de')
        ->toContain('D: medio');

    expect($derivacion['dimensiones'])->toHaveCount(5);
});

it('enseña la justificación de cada dimensión, que es lo que el auditor comprueba', function (): void {
    $derivacion = ($this->declaracion)(uniformeArray('bajo'))->extras['derivacion'];

    $confidencialidad = collect($derivacion['dimensiones'])->firstOrFail(
        static fn (array $d): bool => $d['codigo'] === Dimension::Confidencialidad->value,
    );

    expect($confidencialidad['justificacion'])->toContain('nivel básico');
});

it('lleva sólo medidas del Anexo II, ninguna de otro marco', function (): void {
    $codigos = array_map(
        static fn (FilaRequisito $f): string => $f->codigo,
        ($this->declaracion)(uniformeArray('bajo'))->filas,
    );

    expect($codigos)->not->toBeEmpty();

    foreach ($codigos as $codigo) {
        expect($codigo)->toMatch('/^(org|op|mp)\./');
    }
});

it('dice de dónde sale la exigencia de cada medida', function (): void {
    $filas = ($this->declaracion)(uniformeArray('bajo'))->filas;

    $conOrigen = array_filter($filas, static fn (FilaRequisito $f): bool => $f->origenExigencia !== null);

    expect($conOrigen)->not->toBeEmpty();

    $categoria = OrigenExigencia::Categoria->etiqueta();
    expect(array_column($filas, 'origenExigencia'))->toContain($categoria);
});

it('imprime el refuerzo como tal y no como un código suelto', function (): void {
    // En alta hay medidas con refuerzo; en básica casi ninguna.
    $filas = ($this->declaracion)(uniformeArray('alto'))->filas;

    $reforzadas = array_values(array_filter(
        $filas,
        static fn (FilaRequisito $f): bool => $f->exigencia !== null && str_starts_with($f->exigencia, 'Refuerzo'),
    ));

    expect($reforzadas)->not->toBeEmpty();
    expect($reforzadas[0]->exigencia)->toMatch('/^Refuerzo \d+$/');
});

it('imprime las dos brechas conocidas del Anexo II en el propio documento', function (): void {
    $notas = implode(' ', ($this->declaracion)(uniformeArray('bajo'))->extras['notasAnexoII']);

    expect($notas)
        // La modulación por varias dimensiones: exige de más, nunca de menos.
        ->toContain('op.acc.1')
        ->toContain('nunca de menos')
        // El refuerzo a elegir entre alternativas.
        ->toContain('mp.s.2')
        ->toContain('a elegir')
        // Y que los refuerzos se acumulan.
        ->toContain('hasta R2');
});

it('da la madurez de cada marco con su denominador', function (): void {
    $porMarco = ($this->declaracion)(uniformeArray('bajo'))->extras['madurezPorMarco'];

    expect(array_column($porMarco, 'codigo'))->toBe(['org', 'op', 'mp']);

    foreach ($porMarco as $marco) {
        // Sin ninguna evaluada la media no es L0: es que no se sabe.
        expect($marco['media'])->toBeNull();
        expect($marco['evaluadas'])->toBe(0);
        expect($marco['exigibles'])->toBeGreaterThan(0);
    }
});

it('conserva palabra por palabra los párrafos que estaban clavados en la plantilla', function (): void {
    $contenido = ($this->declaracion)(uniformeArray('bajo'));

    expect($contenido->textos->markdown['nota_tabla'])
        ->toContain('Una medida por fila, agrupadas por el nodo del que cuelgan.')
        ->toContain('«Origen de la exigencia» dice de dónde sale lo que se exige')
        // Sin esta frase, «Refuerzo 9» se lee como «sólo el noveno».
        ->toContain('**Los refuerzos del Anexo II se acumulan**');

    expect($contenido->textos->markdown['nota_derivacion'])
        ->toContain('La categoría es el máximo de las cinco dimensiones')
        ->toContain('Ninguna medida se marca a mano');

    expect($contenido->textos->markdown['nota_madurez'])
        ->toContain('La escala L0–L5 del CCN, que es la que se reporta en INES.')
        ->toContain('una media sobre cuatro medidas de setenta y tres no dice lo mismo');
});

it('declara que faltan los riesgos y los roles ENS, en vez de dejarlos en blanco', function (): void {
    $limitaciones = implode(' ', ($this->declaracion)(uniformeArray('bajo'))->limitaciones);

    expect($limitaciones)
        ->toContain('op.pl.1')
        ->toContain('roles ENS')
        ->toContain('pendientes de designación');
});

/** Las cinco dimensiones al mismo nivel, en la forma que espera `valoracion()`. */
function uniformeArray(string $nivel): array
{
    return array_fill_keys(['C', 'I', 'D', 'A', 'T'], NivelDimension::from($nivel));
}
