<?php

declare(strict_types=1);

use App\Domain\Documento\Contenido\ActaRevisionDireccion;
use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Excepciones\DocumentoNoGenerable;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\RevisionDireccion\AprobarRevision;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\RevisionDireccion\VincularDecision;
use App\Domain\Tarea\Models\Tarea;

/**
 * Qué dice exactamente el acta de la revisión por la dirección.
 *
 * Lo que se clava aquí es lo mismo que en el documento del contexto y por el
 * mismo motivo: **sale de la instantánea y no de las tablas**. Si saliera de las
 * tablas, el PDF de la revisión de marzo enseñaría las no conformidades de
 * octubre bajo la fecha y la firma de marzo, que es la definición de un documento
 * que miente.
 *
 * Y una cosa que sí sale en vivo: **las decisiones**. Son las salidas del acta y
 * pueden crecer después de firmarla —una decisión se ejecuta en las semanas
 * siguientes—, así que lo congelado es lo que la dirección tuvo delante y no lo
 * que mandó hacer.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    /*
     * El acta de la revisión y su borrador se reutilizan entre llamadas:
     * `documentos` tiene índice único por revisión y `documento_versiones` un
     * índice único parcial que deja **un solo borrador** por documento. Un test
     * que genere dos veces —porque compara el antes y el después— chocaría con
     * los dos.
     */
    $this->contenido = function (RevisionDireccion $revision): ContenidoDocumento {
        $documento = Documento::query()->where('revision_direccion_id', $revision->id)->first()
            ?? Documento::factory()->actaRevision($revision)->create();

        $version = $documento->versiones()->whereNull('numero')->first()
            ?? DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(ActaRevisionDireccion::class)->construir($documento->fresh(), $version);
    };
});

it('no se genera con su revisión sin aprobar', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();

    expect(fn () => ($this->contenido)($revision))->toThrow(DocumentoNoGenerable::class, 'no está aprobada');
});

it('imprime la ficha de la reunión con su periodo revisado', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create([
        'asistentes' => 'Dirección general, responsable de seguridad y jefatura de sistemas.',
    ]);
    app(AprobarRevision::class)($revision, $this->usuario);

    $extras = ($this->contenido)($revision)->extras;

    expect($extras['revision']['codigo'])->toBe($revision->codigo)
        // El dato que no se deduce de ninguna otra parte: de qué habla el acta.
        ->and($extras['revision']['periodo'])->toContain('del ')
        ->and($extras['revision']['asistentes'])->toContain('Dirección general')
        ->and($extras['revision']['aprobadaPor'])->toBe($this->usuario->name);
});

it('imprime las siete entradas de la cláusula 9.3.2', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    expect(array_keys(($this->contenido)($revision)->extras['entradas']))->toContain(
        'accionesPrevias',
        'contexto',
        'partesInteresadas',
        'desempeno',
        'riesgos',
        'mejoras',
    );
});

/*
 * El fallo más caro que este documento podía tener, y el mismo que ya está
 * documentado para el del contexto y para el `.docx`.
 */
it('se construye desde la instantánea y no de una consulta nueva', function (): void {
    NoConformidad::factory()->create();
    Mejora::factory()->create();

    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    // Pasa la vida después de firmar el acta.
    NoConformidad::factory()->count(3)->create();
    Mejora::factory()->count(2)->create();

    $entradas = ($this->contenido)($revision)->extras['entradas'];

    expect($entradas['desempeno']['noConformidades']['abiertas'])->toBe(1)
        ->and($entradas['mejoras']['total'])->toBe(1);

    // Y el registro sí se ha movido: lo que no se mueve es el acta.
    expect(NoConformidad::query()->abiertas()->count())->toBe(4)
        ->and(Mejora::query()->count())->toBe(3);
});

/*
 * La excepción deliberada: las decisiones se leen en vivo porque son las salidas
 * y pueden crecer después de firmar.
 */
it('las decisiones se leen en vivo, a diferencia de las entradas', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    expect(($this->contenido)($revision)->extras['decisiones'])->toHaveCount(0);

    $tarea = Tarea::factory()->create(['titulo' => 'Revisar el contrato de copias']);
    app(VincularDecision::class)->vincular($revision->refresh(), $tarea, $this->usuario);

    $decisiones = ($this->contenido)($revision)->extras['decisiones'];

    expect($decisiones)->toHaveCount(1)
        ->and($decisiones[0]['titulo'])->toBe('Revisar el contrato de copias');
});

it('declara por escrito lo que la herramienta no puede afirmar', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    $limitaciones = implode(' ', ($this->contenido)($revision)->limitaciones);

    // Las cuatro que el módulo declara. La de la 9.3.2 e) cambió con la 7.4: ya
    // no dice que se aporta fuera, dice que sólo cuenta lo que se registró.
    expect($limitaciones)->toContain('congelado')
        ->and($limitaciones)->toContain('retroalimentación')
        ->and($limitaciones)->toContain('potestad')
        ->and($limitaciones)->toContain('periodicidad comprometida');
});

it('imprime su revisión y no la aprobada más reciente', function (): void {
    $vieja = RevisionDireccion::factory()
        ->delPeriodo(now()->subYear(), now()->subMonths(6))
        ->enCurso()
        ->create(['codigo' => 'RD-VIEJA']);
    app(AprobarRevision::class)($vieja, $this->usuario);

    $nueva = RevisionDireccion::factory()
        ->delPeriodo(now()->subMonths(5), now())
        ->enCurso()
        ->create(['codigo' => 'RD-NUEVA']);
    app(AprobarRevision::class)($nueva, $this->usuario);

    // Cada revisión es un acto con su fecha, y cada una tiene su acta: la de la
    // reunión del año pasado sigue imprimiendo la del año pasado.
    expect(($this->contenido)($vieja)->extras['revision']['codigo'])->toBe('RD-VIEJA')
        ->and(($this->contenido)($nueva)->extras['revision']['codigo'])->toBe('RD-NUEVA');
});
