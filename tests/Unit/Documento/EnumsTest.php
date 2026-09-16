<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\Enums\TipoDocumento;

it('sabe qué marco le corresponde a cada documento calculado', function (): void {
    // Una SoA de un sistema del ENS no es un documento raro: es imposible.
    expect(TipoDocumento::SoaIso->marcoEsperado())->toBe('ISO27001-2022');
    expect(TipoDocumento::DdaEns->marcoEsperado())->toBe('ENS-RD311-2022');
    expect(TipoDocumento::PlanAdecuacionEns->marcoEsperado())->toBe('ENS-RD311-2022');

    // Un documento redactado no declara conformidad con ningún marco, así que el
    // nulo significa «no hay nada que casar» y nunca «falta por rellenar».
    expect(TipoDocumento::Politica->marcoEsperado())->toBeNull();
});

it('sólo titula «declaración» lo que declara aplicabilidad', function (): void {
    // El título del apartado de limitaciones sale del enum porque lo preguntan
    // dos sitios: el esqueleto de fábrica y el resolutor que repone el bloque
    // cuando alguien lo borra.
    expect(TipoDocumento::SoaIso->tituloLimitaciones())->toBe('Limitaciones de esta declaración');
    expect(TipoDocumento::DdaEns->tituloLimitaciones())->toBe('Limitaciones de esta declaración');

    // Un plan lista lo que falta, y una política no declara aplicabilidad de nada.
    expect(TipoDocumento::PlanAdecuacionEns->tituloLimitaciones())->toBe('Limitaciones de este documento');
    expect(TipoDocumento::Politica->tituloLimitaciones())->toBe('Limitaciones de este documento');
});

it('se pinta con el único Blade que queda', function (): void {
    // Ya no hay una plantilla por tipo: el documento entero sale de
    // `RenderizadorCuerpo` y `documentos.layout` es sólo el `<head>`.
    expect(view()->exists('documentos.layout'))->toBeTrue()
        ->and(view()->exists('documentos.cabecera'))->toBeTrue()
        ->and(view()->exists('documentos.pie'))->toBeTrue();
});

it('distingue el trabajo vivo del terminado', function (): void {
    expect(EstadoGeneracion::Encolada->enCurso())->toBeTrue();
    expect(EstadoGeneracion::Generando->enCurso())->toBeTrue();

    // Y «generada» NO es «aprobada»: es el ciclo del job, no el del documento.
    expect(EstadoGeneracion::Generada->enCurso())->toBeFalse();
    expect(EstadoGeneracion::Fallida->enCurso())->toBeFalse();
});

it('usa tonos que el badge sabe pintar', function (): void {
    $conocidos = ['no_iniciado', 'planificado', 'en_progreso', 'implantado', 'no_aplica', 'caducada', 'marco'];

    foreach (EstadoGeneracion::cases() as $estado) {
        expect($conocidos)->toContain($estado->tono());
    }

    foreach (ClasificacionDocumental::cases() as $clasificacion) {
        expect($conocidos)->toContain($clasificacion->tono());
    }
});

it('estampa la clasificación en versales para el pie de página', function (): void {
    // `mp.info.2`: va impresa en cada página, y en versales para que se lea de
    // un vistazo sin competir con el contenido.
    expect(ClasificacionDocumental::UsoInterno->sello())->toBe('USO INTERNO');
    expect(ClasificacionDocumental::Confidencial->sello())->toBe('CONFIDENCIAL');
});
