<?php

declare(strict_types=1);

use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Contenido\DeclaracionAplicabilidadIso;
use App\Domain\Documento\Cuerpo\ColumnasTabla;
use App\Domain\Documento\Cuerpo\EsquemaCuerpo;
use App\Domain\Documento\Cuerpo\HtmlDocumento;
use App\Domain\Documento\Cuerpo\ResolverCuerpo;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoCuerpo;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Implantacion\CambiarAplicabilidad;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;

/**
 * Lo que el documento entrega cuando nadie lo ha tocado.
 *
 * El cuerpo editable sustituyó a once plantillas Blade, y la única forma de
 * saber que el cambio de motor no degradó el entregable es comprobar que sigue
 * saliendo el mismo marcado con las mismas clases de `documento.css`, que es lo
 * que decide el diseño del PDF. Un documento que se ve peor no rompe ningún
 * test: se entrega.
 *
 * Aquí se afirma sobre HTML a propósito —al revés que `ContenidoSoaTest`, que
 * afirma sobre el objeto tipado—, porque lo que se está probando **es** la
 * maquetación.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $importador = app(ImportadorCatalogo::class);
    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        $importador->importar($fichero);
    }

    $this->sistema = Sistema::factory()
        ->de($this->organizacion)
        ->conMarco(Marco::query()->where('codigo', 'ISO27001-2022')->firstOrFail())
        ->create(['alcance_declarado' => 'Servicios de desarrollo y explotación de la plataforma.']);

    app(GeneradorImplantaciones::class)->generar($this->sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($this->sistema->id)->create();
    $this->version = DocumentoVersion::factory()->delDocumento($this->documento->id)->create();

    $this->html = function (): string {
        $contenido = app(DeclaracionAplicabilidadIso::class)
            ->construir($this->documento->fresh(), $this->version->fresh());

        return app(HtmlDocumento::class)($this->documento->fresh(), $contenido);
    };
});

it('pinta la portada con la marca, la ficha y el alcance declarado', function (): void {
    $html = ($this->html)();

    expect($html)->toContain('<section class="portada">')
        ->and($html)->toContain('<div class="portada__filete"></div>')
        ->and($html)->toContain('<div class="portada__marca">Statera</div>')
        ->and($html)->toContain('<h1 class="portada__titulo">')
        ->and($html)->toContain('<div class="ficha">')
        ->and($html)->toContain('<div class="ficha__clave">Organización</div>')
        ->and($html)->toContain('<div class="ficha__clave">Clasificación</div>')
        ->and($html)->toContain('<div class="caja caja--marca">')
        ->and($html)->toContain('Servicios de desarrollo y explotación de la plataforma.')
        ->and($html)->toContain('<div class="portada__pie pequeno suave">');
});

it('dice en portada que no se mantiene a mano mientras nadie lo edite', function (): void {
    expect(($this->html)())
        ->toContain('no se mantienen a mano')
        ->not->toContain('se editó a mano');
});

it('pinta la tabla larga con su cabecera repetible y los grupos dentro del cuerpo', function (): void {
    $html = ($this->html)();

    /*
     * Las anchuras se leen de `ColumnasTabla` en vez de escribirse aquí: son
     * medidas que cambian —ya cambiaron una vez, porque sumaban más que el ancho
     * útil de la hoja— y un literal en el test sólo consigue que haya que
     * tocarlo cada vez sin comprobar nada de más. Que quepan lo fija
     * `ColumnasTablaTest`; lo que se comprueba aquí es que llegan al marcado.
     */
    $columnas = ColumnasTabla::para(TipoDocumento::SoaIso);
    $primera = $columnas[0];
    $ultima = $columnas[array_key_last($columnas)];

    expect($html)->toContain('<table class="tabla--fija">')
        // La cabecera va en `<thead>`: es lo que Chromium repite al cortar la
        // página, y sin ella una tabla de 93 filas pierde los títulos de columna
        // a partir de la segunda hoja.
        ->and($html)->toContain('<thead><tr><th scope="col" style="width: '.$primera['ancho'].'">'.$primera['titulo'].'</th>')
        ->and($html)->toContain('<th scope="col" style="width: '.$ultima['ancho'].'">'.$ultima['titulo'].'</th>')
        // La fila de grupo NO: se queda entre las filas que agrupa.
        ->and($html)->toContain('<tr class="grupo"><th scope="colgroup" colspan="10">')
        ->and(substr_count($html, '<tr class="grupo">'))->toBe(4);
});

it('pinta las 93 filas del Anexo A, cada una con su badge de estado', function (): void {
    $html = ($this->html)();

    expect(substr_count($html, '<td class="codigo">'))->toBe(93)
        ->and($html)->toContain('<td class="codigo">A.5.1</td>')
        ->and($html)->toContain('<span class="badge badge--no_iniciado">No iniciado</span>')
        // Se dice explícitamente: una celda vacía se lee como un descuido.
        ->and($html)->toContain('<span class="suave">Sin evidencia registrada</span>');
});

it('cierra con las limitaciones del sistema y el control de versiones', function (): void {
    $html = ($this->html)();

    expect($html)->toContain('<h2>Limitaciones de esta declaración</h2>')
        ->and($html)->toContain('<div class="limitaciones">')
        ->and($html)->toContain('<h2>Control de versiones</h2>')
        ->and($html)->toContain('No hay versiones emitidas anteriores.')
        ->and($html)->toContain('no puede figurar dentro de él');
});

it('marca la exclusión sin justificar en la tabla y la repite en su apartado', function (): void {
    // Un control del Anexo A, no una cláusula 4–10: las cláusulas son el sistema
    // de gestión y no figuran en la SoA, así que excluir una no se vería aquí.
    $implantacion = Implantacion::query()
        ->where('sistema_id', $this->sistema->id)
        ->whereHas('requisito', fn ($consulta) => $consulta->where('codigo', 'like', 'A.%'))
        ->firstOrFail();

    app(CambiarAplicabilidad::class)->excluir($implantacion, 'No hay desarrollo propio en el alcance.');

    $html = ($this->html)();

    expect($html)->toContain('<h2>Controles excluidos y su justificación</h2>')
        ->and($html)->toContain('No hay desarrollo propio en el alcance.')
        // La duplicación es deliberada: es la sección que el auditor abre
        // primero, porque 6.1.3 d) le obliga a comprobar cada exclusión.
        ->and(substr_count($html, 'No hay desarrollo propio en el alcance.'))->toBe(2)
        ->and($html)->toContain('<td><strong>No</strong></td>');
});

it('dice que no se ha excluido nada cuando no se ha excluido nada', function (): void {
    expect(($this->html)())
        ->toContain('<p class="vacio">No se ha excluido ningún control del Anexo A.');
});

it('no emite ninguna clase que documento.css no sepa pintar', function (): void {
    preg_match_all('/class="([^"]+)"/', ($this->html)(), $coincidencias);

    $emitidas = [];

    foreach ($coincidencias[1] as $atributo) {
        foreach (explode(' ', $atributo) as $clase) {
            $emitidas[$clase] = true;
        }
    }

    $hoja = file_get_contents(resource_path('documentos/documento.css'));
    preg_match_all('/\.([a-zA-Z][\w-]*)/', (string) $hoja, $declaradas);

    // Una clase que no está en la hoja no pinta nada: es una falta de ortografía
    // que sólo se ve abriendo el PDF, y ésta es la entrega en la que el marcado
    // pasó de estar escrito en Blade a componerse en PHP.
    expect(array_diff(array_keys($emitidas), $declaradas[1]))->toBe([]);
});

it('estrena el cuerpo una sola vez y no lo vuelve a pisar al generar', function (): void {
    $contenido = app(DeclaracionAplicabilidadIso::class)
        ->construir($this->documento->fresh(), $this->version->fresh());

    $resolver = app(ResolverCuerpo::class);

    $primero = $resolver->fila($this->documento->fresh(), $contenido);
    $segundo = $resolver->fila($this->documento->fresh(), $contenido);

    expect(DocumentoCuerpo::query()->count())->toBe(1)
        ->and($segundo->id)->toBe($primero->id)
        // Nace sin tocar: `cuerpo` y `generado` son lo mismo y no hay nada que
        // declarar todavía.
        ->and($primero->cuerpo)->toBe($primero->generado)
        ->and($primero->estaEditado())->toBeFalse();
});

it('vuelve a pedir las limitaciones y el control de versiones aunque las borren del cuerpo', function (): void {
    $contenido = app(DeclaracionAplicabilidadIso::class)
        ->construir($this->documento->fresh(), $this->version->fresh());

    $fila = app(ResolverCuerpo::class)->fila($this->documento->fresh(), $contenido);

    // Alguien vacía a mano los dos bloques blindados en el editor.
    $cuerpo = $fila->cuerpo;
    $vaciados = json_decode((string) preg_replace(
        '/"fuente":"('.implode('|', EsquemaCuerpo::SIEMPRE_RECALCULADOS).')","?[^}]*/',
        '"fuente":"$1"',
        (string) json_encode($cuerpo),
    ), true);

    $fila->update(['cuerpo' => is_array($vaciados) ? $vaciados : $cuerpo]);

    // Y aun así el documento entregado los lleva: no hay forma de quitarlos.
    expect(($this->html)())
        ->toContain('<div class="limitaciones">')
        ->toContain('no puede figurar dentro de él');
});
