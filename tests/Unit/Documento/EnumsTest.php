<?php

declare(strict_types=1);

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\Enums\TipoDocumento;

it('sabe qué marco le corresponde a cada declaración', function (): void {
    // Una SoA de un sistema del ENS no es un documento raro: es imposible.
    expect(TipoDocumento::SoaIso->marcoEsperado())->toBe('ISO27001-2022');
    expect(TipoDocumento::DdaEns->marcoEsperado())->toBe('ENS-RD311-2022');
});

it('apunta a una plantilla que existe', function (TipoDocumento $tipo): void {
    expect(view()->exists($tipo->plantilla()))->toBeTrue();
})->with(TipoDocumento::cases());

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
