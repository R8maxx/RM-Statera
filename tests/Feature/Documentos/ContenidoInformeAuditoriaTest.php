<?php

declare(strict_types=1);

use App\Domain\Auditoria\CerrarAuditoria;
use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\InformeAuditoriaInterna;
use App\Domain\Documento\Cuerpo\CuerpoDeFabrica;
use App\Domain\Documento\Cuerpo\HtmlDocumento;
use App\Domain\Documento\Cuerpo\MaterializarCuerpo;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Excepciones\DocumentoNoGenerable;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\NoConformidad\Models\NoConformidad;

/**
 * Qué dice exactamente el informe de auditoría interna (§ 4.18, 9.2.2).
 *
 * Lo que se clava es lo mismo que en el acta y en la Declaración de Conformidad:
 * **sale de lo congelado**. La checklist es la del cierre, y cada punto imprime la
 * exigencia y el estado que tenía ese día, no los de la implantación hoy.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->sistema = sistemaEns($this->organizacion);

    $this->contenido = function (Auditoria $auditoria): ContenidoDocumento {
        $documento = Documento::query()->where('auditoria_id', $auditoria->id)->first()
            ?? Documento::factory()->informeAuditoria($auditoria)->create();

        $version = $documento->versiones()->whereNull('numero')->first()
            ?? DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(InformeAuditoriaInterna::class)->construir($documento->fresh(), $version);
    };
});

it('no se genera si la auditoría se ha reabierto', function (): void {
    $auditoria = autoevaluacion($this->sistema);
    Documento::factory()->informeAuditoria($auditoria)->create();

    app(CerrarAuditoria::class)->reabrir($auditoria);

    expect(fn () => ($this->contenido)($auditoria->fresh()))
        ->toThrow(DocumentoNoGenerable::class, 'está abierta');
});

it('cuenta la checklist con su denominador, sin sumar lo no revisado a conforme', function (): void {
    $auditoria = autoevaluacion($this->sistema, [
        ResultadoPunto::Conforme,
        ResultadoPunto::Conforme,
        ResultadoPunto::NoConforme,
        ResultadoPunto::FueraDeMuestra,
        ResultadoPunto::Pendiente,
    ]);

    $resultado = ($this->contenido)($auditoria)->extras['resultado'];
    $puntos = collect($resultado['puntos'])->keyBy('clave');

    expect($resultado['total'])->toBe(5)
        ->and($puntos['conforme']['total'])->toBe(2)
        ->and($puntos['no_conforme']['total'])->toBe(1)
        ->and($puntos['fuera_de_muestra']['total'])->toBe(1)
        ->and($puntos['pendiente']['total'])->toBe(1);
});

it('imprime el estado congelado al cerrar, no el de la implantación hoy', function (): void {
    $auditoria = autoevaluacion($this->sistema, [ResultadoPunto::NoConforme], antes: function (Auditoria $auditoria): void {
        $auditoria->puntos()->sole()->implantacion
            ->forceFill(['estado' => EstadoImplantacion::EnProgreso->value])->saveQuietly();
    });

    /** @var AuditoriaPunto $punto */
    $punto = $auditoria->puntos()->with('implantacion')->sole();

    expect($punto->estado_congelado)->toBe(EstadoImplantacion::EnProgreso);

    // Después del cierre, alguien marca la medida como implantada.
    $punto->implantacion->forceFill(['estado' => EstadoImplantacion::Implantado->value])->saveQuietly();

    $filas = ($this->contenido)($auditoria)->extras['puntos'];

    expect($filas)->toHaveCount(1)
        ->and($filas[0]['resultado'])->toBe(ResultadoPunto::NoConforme->etiqueta())
        ->and($filas[0]['estado'])->toBe(EstadoImplantacion::EnProgreso->etiqueta());
});

it('lista sólo los puntos no conformes y con observación', function (): void {
    $auditoria = autoevaluacion($this->sistema, [
        ResultadoPunto::Conforme,
        ResultadoPunto::Observacion,
        ResultadoPunto::NoConforme,
        ResultadoPunto::FueraDeMuestra,
    ]);

    $resultados = collect(($this->contenido)($auditoria)->extras['puntos'])->pluck('resultado')->all();

    expect($resultados)->toEqualCanonicalizing([
        ResultadoPunto::Observacion->etiqueta(),
        ResultadoPunto::NoConforme->etiqueta(),
    ]);
});

it('imprime cada hallazgo con su tratamiento, y dice cuando falta', function (): void {
    $auditoria = autoevaluacion($this->sistema, [ResultadoPunto::NoConforme], antes: function (Auditoria $auditoria): void {
        $tratado = Hallazgo::factory()->deTipo(TipoHallazgo::NcMenor)->create([
            'auditoria_id' => $auditoria->id,
            'descripcion' => 'Copias sin verificar.',
        ]);
        NoConformidad::factory()->deHallazgo($tratado->id)->create(['codigo' => 'NC-2026-07']);

        Hallazgo::factory()->deTipo(TipoHallazgo::NcMayor)->create([
            'auditoria_id' => $auditoria->id,
            'descripcion' => 'El programa de auditoría no está definido.',
        ]);
    });

    $hallazgos = collect(($this->contenido)($auditoria)->extras['hallazgos'])->keyBy('descripcion');

    expect($hallazgos['Copias sin verificar.']['tratamiento'])->toStartWith('NC-2026-07')
        ->and($hallazgos['El programa de auditoría no está definido.']['tratamiento'])->toBe('Sin tratamiento abierto')
        ->and($hallazgos['El programa de auditoría no está definido.']['medida'])->toBeNull();
});

it('lleva en la ficha lo que pide la 9.2.2: alcance, criterios, método y equipo', function (): void {
    $auditoria = autoevaluacion($this->sistema, antes: function (Auditoria $auditoria): void {
        $auditoria->update([
            'alcance' => 'Servicios de sede electrónica.',
            'criterios' => 'Anexo II del RD 311/2022.',
            'metodo' => 'Entrevistas y revisión documental.',
            'equipo' => 'Persona auditora de apoyo.',
        ]);
    });

    $ficha = ($this->contenido)($auditoria)->extras['auditoria'];

    expect($ficha['alcance'])->toBe('Servicios de sede electrónica.')
        ->and($ficha['criterios'])->toBe('Anexo II del RD 311/2022.')
        ->and($ficha['metodo'])->toBe('Entrevistas y revisión documental.')
        ->and($ficha['equipo'])->toBe('Persona auditora de apoyo.')
        ->and($ficha['fechaCierre'])->not->toBeNull();
});

it('declara lo que no puede afirmar: la cobertura, el programa, la traza y el tratamiento', function (): void {
    $auditoria = autoevaluacion($this->sistema);

    $limitaciones = implode("\n", ($this->contenido)($auditoria)->limitaciones);

    expect($limitaciones)->toContain('no comprueba que el alcance auditado cubra lo exigible')
        ->and($limitaciones)->toContain('no mantiene un programa anual de auditoría')
        ->and($limitaciones)->toContain('quién lo marcó')
        ->and($limitaciones)->toContain('fecha de extracción');
});

it('el cuerpo materializado lleva la ficha, el resultado y las conclusiones', function (): void {
    $auditoria = autoevaluacion($this->sistema, [ResultadoPunto::Conforme, ResultadoPunto::NoConforme]);

    $cuerpo = app(MaterializarCuerpo::class)(
        CuerpoDeFabrica::para(TipoDocumento::InformeAuditoria),
        ($this->contenido)($auditoria),
        TipoDocumento::InformeAuditoria,
    );

    $texto = json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($texto)->toContain($auditoria->codigo)
        ->and($texto)->toContain('Total de medidas de la checklist')
        ->and($texto)->toContain('Sin incidencias.')
        ->and($texto)->toContain('Conclusiones del auditor');
});

it('el HTML del documento se pinta entero, con la ficha y las tablas', function (): void {
    $auditoria = autoevaluacion($this->sistema, [ResultadoPunto::Conforme, ResultadoPunto::NoConforme], antes: function (Auditoria $auditoria): void {
        Hallazgo::factory()->deTipo(TipoHallazgo::Observacion)->create([
            'auditoria_id' => $auditoria->id,
            'descripcion' => 'El programa de auditoría no está definido.',
        ]);
    });

    $contenido = ($this->contenido)($auditoria);
    $html = app(HtmlDocumento::class)(Documento::query()->where('auditoria_id', $auditoria->id)->sole(), $contenido);

    expect($html)->toContain('La auditoría')
        ->and($html)->toContain('Medidas no conformes y con observación')
        ->and($html)->toContain('El programa de auditoría no está definido.')
        ->and($html)->toContain('Limitaciones de este informe');
});
