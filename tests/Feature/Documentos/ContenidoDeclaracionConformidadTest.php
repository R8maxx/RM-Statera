<?php

declare(strict_types=1);

use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Conformidad\IniciarDeclaracion;
use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\DeclaracionConformidadEns;
use App\Domain\Documento\Cuerpo\CuerpoDeFabrica;
use App\Domain\Documento\Cuerpo\MaterializarCuerpo;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Excepciones\DocumentoNoGenerable;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Sistema\AplicarValoracion;

/**
 * Qué dice exactamente la Declaración de Conformidad del ENS (§ 4.17).
 *
 * Lo que se clava es lo mismo que en el acta y por el mismo motivo: **sale de lo
 * congelado**. La categoría es la de la conformidad iniciada, no la del sistema
 * hoy, y el resultado es el de la checklist cerrada.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->sistema = sistemaEns($this->organizacion);

    $this->contenido = function (): ContenidoDocumento {
        $documento = Documento::query()->where('sistema_id', $this->sistema->id)->first()
            ?? Documento::factory()->declaracionConformidad()->paraSistema($this->sistema->id)->create();

        $version = $documento->versiones()->whereNull('numero')->first()
            ?? DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(DeclaracionConformidadEns::class)->construir($documento->fresh(), $version);
    };
});

it('no se genera sin una declaración iniciada', function (): void {
    autoevaluacion($this->sistema);

    expect(fn () => ($this->contenido)())->toThrow(DocumentoNoGenerable::class, 'declaración de conformidad iniciada');
});

it('declara la categoría congelada y la autoevaluación que la respalda', function (): void {
    $autoevaluacion = autoevaluacion($this->sistema, [
        ResultadoPunto::Conforme,
        ResultadoPunto::Conforme,
        ResultadoPunto::Observacion,
        ResultadoPunto::FueraDeMuestra,
    ], antes: function (Auditoria $auditoria): void {
        Hallazgo::factory()->deTipo(TipoHallazgo::Observacion)->create(['auditoria_id' => $auditoria->id]);
    });
    app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    $contenido = ($this->contenido)();
    $declaracion = $contenido->extras['declaracion'];
    $resultado = $contenido->extras['resultado'];
    $puntos = collect($resultado['puntos'])->keyBy('clave');

    expect($declaracion['categoria'])->toBe('Básica')
        ->and($declaracion['autoevaluacion'])->toBe($autoevaluacion->codigo)
        ->and($contenido->portada['categoria'])->toBe('Básica')
        // El denominador, impreso: todas las medidas de la checklist.
        ->and($resultado['total'])->toBe(4)
        ->and($puntos['conforme']['total'])->toBe(2)
        ->and($puntos['fuera_de_muestra']['total'])->toBe(1)
        ->and(collect($resultado['hallazgos'])->firstWhere('clave', 'observacion')['total'])->toBe(1);
});

it('avisa si el sistema ya no es de la categoría declarada, y no la cambia', function (): void {
    autoevaluacion($this->sistema);
    app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    app(AplicarValoracion::class)->aplicar($this->sistema->fresh(), uniforme(NivelDimension::Medio), []);

    $contenido = ($this->contenido)();

    expect($contenido->extras['declaracion']['categoria'])->toBe('Básica')
        ->and($contenido->limitaciones[0])->toContain('ya no es de categoría Básica')
        ->and($contenido->limitaciones[0])->toContain('Media');
});

it('declara lo que no puede afirmar: la independencia, lo no muestreado y el distintivo', function (): void {
    autoevaluacion($this->sistema);
    app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    $limitaciones = implode("\n", ($this->contenido)()->limitaciones);

    expect($limitaciones)->toContain('no comprueba la independencia')
        ->and($limitaciones)->toContain('fuera de muestra')
        ->and($limitaciones)->toContain('no sirve ninguna página')
        ->and($limitaciones)->toContain('pendiente de contrastar con el BOE');
});

it('el cuerpo materializado lleva la frase de la declaración con el sistema y la categoría', function (): void {
    autoevaluacion($this->sistema);
    app(IniciarDeclaracion::class)($this->sistema, $this->usuario);

    $cuerpo = app(MaterializarCuerpo::class)(
        CuerpoDeFabrica::para(TipoDocumento::DeclaracionConformidadEns),
        ($this->contenido)(),
        TipoDocumento::DeclaracionConformidadEns,
    );

    $texto = json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($texto)->toContain('declara que el sistema de información')
        ->and($texto)->toContain($this->sistema->codigo)
        ->and($texto)->toContain('BÁSICA')
        ->and($texto)->toContain('Real Decreto 311/2022');
});
