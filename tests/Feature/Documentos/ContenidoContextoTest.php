<?php

declare(strict_types=1);

use App\Domain\Contexto\AnalisisEnCurso;
use App\Domain\Contexto\AprobarAnalisis;
use App\Domain\Contexto\Enums\NaturalezaRequisito;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\Models\RequisitoInteresado;
use App\Domain\Contexto\RegistrarCuestion;
use App\Domain\Documento\Contenido\AnalisisDelContexto;
use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Excepciones\DocumentoNoGenerable;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;

/**
 * Qué dice exactamente el documento del análisis del contexto.
 *
 * Lo que se clava aquí son las dos cosas que lo separan del resto: **sale de la
 * instantánea y no de las tablas** —si saliera de las tablas, el PDF de marzo
 * enseñaría el DAFO de octubre bajo la fecha de marzo— y **no cuelga de ningún
 * sistema**, aunque sí recoge el alcance declarado de todos.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->sistema = Sistema::factory()->de($this->organizacion)->create([
        'estado' => EstadoSistema::Activo->value,
        'alcance_declarado' => 'Sede electrónica y su plataforma de tramitación.',
        'exclusiones_justificadas' => 'Queda fuera la red corporativa de oficinas.',
    ]);

    $this->sembrar = function (): void {
        app(RegistrarCuestion::class)([
            'codigo' => 'CTX-01',
            'tipo' => TipoCuestion::Amenaza->value,
            'materia' => 'ambiental',
            'titulo' => 'Olas de calor y continuidad del servicio',
            'es_climatica' => true,
        ], $this->usuario);

        $parte = ParteInteresada::factory()->create(['codigo' => 'PI-01', 'nombre' => 'Regulador de prueba']);
        RequisitoInteresado::factory()->for($parte, 'parteInteresada')->legal()->create([
            'descripcion' => 'Cumplir el ENS en la categoría que fije cada pliego.',
        ]);
    };

    $this->aprobar = function (): void {
        $borrador = app(AnalisisEnCurso::class)->borradorObligatorio($this->usuario);
        $borrador->update([
            'clima_pertinente' => true,
            'clima_justificacion' => 'Las olas de calor afectan a la refrigeración del centro de proceso de datos.',
        ]);

        app(AprobarAnalisis::class)($borrador->refresh(), $this->usuario);
    };

    $this->contenido = function (): ContenidoDocumento {
        $documento = Documento::factory()->analisisContexto()->create();
        $version = DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(AnalisisDelContexto::class)->construir($documento->fresh(), $version);
    };
});

it('no se genera sin un análisis aprobado', function (): void {
    ($this->sembrar)();

    expect(fn () => ($this->contenido)())->toThrow(DocumentoNoGenerable::class);
});

it('imprime los cuatro cuadrantes, vacíos incluidos', function (): void {
    ($this->sembrar)();
    ($this->aprobar)();

    $contenido = ($this->contenido)();
    $cuadrantes = $contenido->extras['dafo'];

    expect($cuadrantes)->toHaveCount(4)
        ->and(array_column($cuadrantes, 'tipo'))->toBe(array_map(
            static fn (TipoCuestion $tipo): string => $tipo->value,
            TipoCuestion::enOrdenDeMatriz(),
        ));

    $amenazas = collect($cuadrantes)->firstWhere('tipo', TipoCuestion::Amenaza->value);

    expect($amenazas['cuestiones'])->toHaveCount(1)
        ->and($amenazas['ambito'])->toBe('Externo')
        ->and($amenazas['signo'])->toBe('En contra');
});

it('imprime la declaración del cambio climático con su razonamiento', function (): void {
    ($this->sembrar)();
    ($this->aprobar)();

    $clima = ($this->contenido)()->extras['clima'];

    expect($clima['pertinente'])->toBeTrue()
        ->and($clima['justificacion'])->toContain('refrigeración');
});

it('recoge el alcance declarado de cada sistema activo', function (): void {
    ($this->sembrar)();
    ($this->aprobar)();

    $alcance = ($this->contenido)()->extras['alcance'];

    expect($alcance)->toHaveCount(1)
        ->and($alcance[0]['alcanceDeclarado'])->toContain('Sede electrónica')
        ->and($alcance[0]['exclusiones'])->toContain('red corporativa');
});

/*
 * El fallo más caro que este documento podía tener, y el mismo que ya está
 * documentado para el `.docx`: las cuestiones se siguen editando entre
 * revisiones, así que un documento que consultara las tablas enseñaría el DAFO de
 * hoy bajo la fecha de la aprobación.
 */
it('enseña el contexto congelado y no el de hoy', function (): void {
    ($this->sembrar)();
    ($this->aprobar)();

    CuestionContexto::query()->where('codigo', 'CTX-01')->firstOrFail()
        ->update(['titulo' => 'Reescrito después de aprobar']);

    ParteInteresada::query()->where('codigo', 'PI-01')->firstOrFail()
        ->update(['nombre' => 'Otro nombre completamente distinto']);

    $contenido = ($this->contenido)();
    $amenazas = collect($contenido->extras['dafo'])->firstWhere('tipo', TipoCuestion::Amenaza->value);

    expect($amenazas['cuestiones'][0]['titulo'])->toBe('Olas de calor y continuidad del servicio')
        ->and($contenido->extras['partes'][0]['nombre'])->toBe('Regulador de prueba');
});

it('no tiene filas de requisitos ni cifras de implantación', function (): void {
    ($this->sembrar)();
    ($this->aprobar)();

    $contenido = ($this->contenido)();

    expect($contenido->filas)->toBe([])
        ->and($contenido->resumen)->toBe([])
        ->and($contenido->portada['alcance'])->toBeNull()
        ->and($contenido->portada['sistemaCodigo'])->toBeNull();
});

it('declara por escrito lo que no puede afirmar', function (): void {
    ($this->sembrar)();
    ($this->aprobar)();

    $limitaciones = implode(' ', ($this->contenido)()->limitaciones);

    expect($limitaciones)
        ->toContain('tal como se aprobó')
        ->toContain('no comprueba que el análisis esté completo')
        ->toContain('se copian de su ficha');
});

it('los requisitos de las partes llegan con su naturaleza', function (): void {
    ($this->sembrar)();
    ($this->aprobar)();

    $partes = ($this->contenido)()->extras['partes'];

    expect($partes)->toHaveCount(1)
        ->and($partes[0]['requisitos'])->toHaveCount(1)
        ->and($partes[0]['requisitos'][0]['naturaleza'])->toBe(NaturalezaRequisito::Legal->value)
        ->and($partes[0]['requisitos'][0]['obliga'])->toBeTrue();
});
