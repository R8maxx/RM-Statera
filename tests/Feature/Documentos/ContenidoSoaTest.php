<?php

declare(strict_types=1);

use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\DeclaracionAplicabilidadIso;
use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Implantacion\CambiarAplicabilidad;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;

/**
 * Qué dice exactamente la Declaración de Aplicabilidad de ISO.
 *
 * Es el test de más prioridad del módulo, y el motivo es el de siempre: aquí un
 * fallo es silencioso. Un documento con las cláusulas 4 a 10 mezcladas entre los
 * controles, o con una exclusión sin justificar, no revienta nada: se entrega, y
 * el hallazgo lo pone el auditor.
 *
 * Se afirma sobre `ContenidoDocumento`, que es un objeto tipado, y no sobre el
 * HTML: así el test dice qué tiene que contener el documento y no cómo está
 * maquetado.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    // Catálogo real: lo que se prueba aquí es justamente que salen los 93
    // controles del Anexo A y ninguna cláusula.
    $importador = app(ImportadorCatalogo::class);
    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        $importador->importar($fichero);
    }

    $this->iso = Marco::query()->where('codigo', 'ISO27001-2022')->firstOrFail();

    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->iso)->create([
        'alcance_declarado' => 'Servicios de desarrollo y explotación de la plataforma.',
        'exclusiones_justificadas' => 'Queda fuera la red corporativa de oficinas.',
    ]);

    app(GeneradorImplantaciones::class)->generar($this->sistema);

    $this->documento = Documento::factory()->soa()->paraSistema($this->sistema->id)->create();
    $this->version = DocumentoVersion::factory()->delDocumento($this->documento->id)->create();

    $this->construir = fn (): ContenidoDocumento => app(DeclaracionAplicabilidadIso::class)
        ->construir($this->documento->fresh(), $this->version->fresh());
});

it('lista los 93 controles del Anexo A y ninguna cláusula del cuerpo de la norma', function (): void {
    $contenido = ($this->construir)();

    expect($contenido->filas)->toHaveCount(93);

    $codigos = array_map(static fn (FilaRequisito $f): string => $f->codigo, $contenido->filas);

    // Todos empiezan por «A.»: si se colara «6.1.2 Apreciación de riesgos» como
    // control, eso es un hallazgo de auditoría, no una fila de más.
    expect(array_filter($codigos, static fn (string $c): bool => ! str_starts_with($c, 'A.')))->toBe([]);

    expect($codigos)->toContain('A.5.1')->toContain('A.8.34');
});

it('agrupa los controles en los cuatro temas del Anexo A', function (): void {
    $grupos = array_keys(($this->construir)()->filasPorGrupo());

    expect($grupos)->toHaveCount(4);

    foreach (['A.5', 'A.6', 'A.7', 'A.8'] as $tema) {
        expect(implode(' ', $grupos))->toContain($tema);
    }
});

it('justifica la inclusión de cada control aplicable sin inventarse un riesgo', function (): void {
    $aplicables = array_filter(($this->construir)()->filas, static fn (FilaRequisito $f): bool => $f->aplica);

    expect($aplicables)->not->toBeEmpty();

    foreach ($aplicables as $fila) {
        // Telegráfico: la frase larga va una sola vez, en la introducción de la
        // sección. Lo que no puede faltar en ninguna fila es el origen.
        expect($fila->justificacionInclusion)->toStartWith('Anexo A');

        // El módulo de riesgos no existe: el documento no cita ninguno.
        expect($fila->justificacionInclusion)->not->toContain('riesgo R-');
    }
});

it('añade la exigencia legal del ENS cuando el mapeo cruzado la encuentra', function (): void {
    // Es el argumento del producto dentro del entregable: la misma medida
    // exigida por los dos marcos, y un requisito legal es justificación de
    // inclusión legítima para ISO.
    $conEns = array_filter(
        ($this->construir)()->filas,
        static fn (FilaRequisito $f): bool => $f->justificacionInclusion !== null
            && str_contains($f->justificacionInclusion, 'exigido por el ENS'),
    );

    // Sin sistema ENS en esta organización no hay implantaciones del otro marco,
    // así que aquí no debe aparecer ninguna: inventarla sería peor que no tenerla.
    expect($conEns)->toBeEmpty();
});

it('saca el control excluido a su propia sección, con el motivo', function (): void {
    $implantacion = Implantacion::query()
        ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
        ->where('requisitos.codigo', 'A.7.4')
        ->select('implantaciones.*')
        ->firstOrFail();

    app(CambiarAplicabilidad::class)->excluir(
        $implantacion,
        'No hay perímetro físico propio: toda la infraestructura es de terceros.',
    );

    $contenido = ($this->construir)();
    $excluidas = $contenido->excluidas();

    expect($excluidas)->toHaveCount(1);
    expect($excluidas[0]->codigo)->toBe('A.7.4');
    expect($excluidas[0]->justificacion)->toContain('perímetro físico propio');

    // Y quien está excluido no lleva justificación de inclusión: sería decir dos
    // cosas contrarias en la misma fila.
    expect($excluidas[0]->justificacionInclusion)->toBeNull();
});

it('cuadra las cifras del resumen con las filas de la tabla', function (): void {
    $implantacion = Implantacion::query()
        ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
        ->where('requisitos.codigo', 'A.5.1')
        ->select('implantaciones.*')
        ->firstOrFail();

    $implantacion->update(['estado' => EstadoImplantacion::Implantado->value]);

    $contenido = ($this->construir)();
    $r = $contenido->resumen;

    expect($r['total'])->toBe(count($contenido->filas));
    expect($r['aplicables'] + $r['excluidos'])->toBe($r['total']);
    expect($r['implantados'])->toBe(1);
    // Toda cifra con su denominador: el porcentaje se calcula sobre lo exigible.
    expect($r['porcentaje'])->toBe((int) round(1 * 100 / $r['aplicables']));
});

it('declara por escrito lo que todavía no puede afirmar', function (): void {
    $limitaciones = implode(' ', ($this->construir)()->limitaciones);

    expect($limitaciones)
        ->toContain('módulo de riesgos')
        // La redacción se precisó al volverse editable la narrativa: la
        // herramienta no implementa aprobación, y el texto de aprobación que
        // pueda figurar lo ha escrito la organización, no Statera.
        ->toContain('no implementa un flujo de aprobación')
        ->toContain('Statera no lo ha validado')
        ->toContain('derechos de autor');
});

it('conserva palabra por palabra los párrafos que estaban clavados en la plantilla', function (): void {
    /*
     * Estos dos párrafos vivían en `soa-iso.blade.php` y ahora son texto de
     * fábrica editable. Si alguien mejora la redacción, este test se pone rojo
     * y hay que actualizarlo a conciencia — que es justo lo que se quiere: un
     * documento de cumplimiento no cambia de texto por accidente.
     */
    $nota = ($this->construir)()->textos->markdown['nota_tabla'];

    expect($nota)
        ->toContain('Un control por fila, agrupados por los cuatro temas de ISO/IEC 27001:2022.')
        ->toContain('La columna «Correspondencia ENS» indica qué medidas del Real Decreto 311/2022 cubren lo mismo: la misma prueba vale para los dos marcos.')
        ->toContain('**Cómo leer «Origen de la inclusión».**')
        ->toContain('lo que se justifica es la **exclusión**, no la inclusión')
        ->toContain('la exigencia **legal** que obliga además a ese control');
});

it('cuenta los controles sin evidencia sobre lo exigible', function (): void {
    $resumen = ($this->construir)()->resumen;

    // Ninguna implantación tiene evidencia todavía, así que el hueco es total y
    // el documento lo dice en vez de dejar la columna en blanco.
    expect($resumen['sinEvidencia'])->toBe($resumen['aplicables']);
});
