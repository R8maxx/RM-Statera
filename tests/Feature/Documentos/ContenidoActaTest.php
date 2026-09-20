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
     * El documento y su borrador se reutilizan entre llamadas: `documentos` tiene
     * índice único por código y `documento_versiones` un índice único parcial que
     * deja **un solo borrador** por documento. Un test que genere dos veces
     * —porque compara el antes y el después— chocaría con los dos.
     */
    $this->contenido = function (): ContenidoDocumento {
        $documento = Documento::query()->where('codigo', 'ACT-REV-01')->first()
            ?? Documento::factory()->actaRevision()->create();

        $version = $documento->versiones()->whereNull('numero')->first()
            ?? DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(ActaRevisionDireccion::class)->construir($documento->fresh(), $version);
    };
});

it('no se genera sin una revisión aprobada', function (): void {
    RevisionDireccion::factory()->enCurso()->create();

    expect(fn () => ($this->contenido)())->toThrow(DocumentoNoGenerable::class, 'acta aprobada');
});

it('imprime la ficha de la reunión con su periodo revisado', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create([
        'asistentes' => 'Dirección general, responsable de seguridad y jefatura de sistemas.',
    ]);
    app(AprobarRevision::class)($revision, $this->usuario);

    $extras = ($this->contenido)()->extras;

    expect($extras['revision']['codigo'])->toBe($revision->codigo)
        // El dato que no se deduce de ninguna otra parte: de qué habla el acta.
        ->and($extras['revision']['periodo'])->toContain('del ')
        ->and($extras['revision']['asistentes'])->toContain('Dirección general')
        ->and($extras['revision']['aprobadaPor'])->toBe($this->usuario->name);
});

it('imprime las siete entradas de la cláusula 9.3.2', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    expect(array_keys(($this->contenido)()->extras['entradas']))->toContain(
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

    $entradas = ($this->contenido)()->extras['entradas'];

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

    expect(($this->contenido)()->extras['decisiones'])->toHaveCount(0);

    $tarea = Tarea::factory()->create(['titulo' => 'Revisar el contrato de copias']);
    app(VincularDecision::class)->vincular($revision->refresh(), $tarea, $this->usuario);

    $decisiones = ($this->contenido)()->extras['decisiones'];

    expect($decisiones)->toHaveCount(1)
        ->and($decisiones[0]['titulo'])->toBe('Revisar el contrato de copias');
});

it('declara por escrito lo que la herramienta no puede afirmar', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    $limitaciones = implode(' ', ($this->contenido)()->limitaciones);

    // Las cuatro que el módulo declara, y la del medio es la que más importa:
    // la 9.3.2 e) se aporta fuera de la herramienta.
    expect($limitaciones)->toContain('congelado')
        ->and($limitaciones)->toContain('retroalimentación')
        ->and($limitaciones)->toContain('potestad')
        ->and($limitaciones)->toContain('periodicidad comprometida');
});

it('imprime la revisión aprobada más reciente', function (): void {
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

    // Un documento es una serie: cada generación entrega la foto más reciente, y
    // el histórico de lo anterior vive en `documento_versiones`.
    expect(($this->contenido)()->extras['revision']['codigo'])->toBe('RD-NUEVA');
});
