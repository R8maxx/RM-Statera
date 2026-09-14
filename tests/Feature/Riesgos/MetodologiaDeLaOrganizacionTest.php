<?php

declare(strict_types=1);

use App\Domain\Riesgo\Excepciones\EscalaInvalida;
use App\Domain\Riesgo\Excepciones\MetodologiaIncoherente;
use App\Domain\Riesgo\GuardarMetodologia;
use App\Domain\Riesgo\MetodologiaDeFabrica;
use App\Domain\Riesgo\MetodologiaVigente;
use App\Domain\Riesgo\Models\MetodologiaRiesgo;

/*
|--------------------------------------------------------------------------
| La metodología de la organización
|--------------------------------------------------------------------------
|
| Cadena de dos eslabones, y gana el primero que EXISTA:
|
|     metodologias_riesgo (fila de la organización)  →  MetodologiaDeFabrica
|
| Lo que sostiene este test es que **«no lo he tocado» y «no hay fila» son lo
| mismo**. Sin eso bastaría con abrir la pantalla y darle a guardar para que la
| metodología de esa organización quedara congelada y dejara de recibir cualquier
| mejora futura, sin haberlo decidido y sin enterarse.
|
*/

/** @return array<string, mixed> */
function datosDeFabrica(array $cambios = []): array
{
    return [
        'nombre' => MetodologiaDeFabrica::NOMBRE,
        'referencia' => MetodologiaDeFabrica::REFERENCIA,
        'escala_probabilidad' => MetodologiaDeFabrica::probabilidad()->aArray(),
        'escala_impacto' => MetodologiaDeFabrica::impacto()->aArray(),
        'umbral_aceptacion' => MetodologiaDeFabrica::UMBRAL_ACEPTACION,
        'umbral_critico' => MetodologiaDeFabrica::UMBRAL_CRITICO,
        'periodicidad_revision_meses' => MetodologiaDeFabrica::PERIODICIDAD_MESES,
        ...$cambios,
    ];
}

it('una organización sin fila resuelve hasta la de fábrica', function (): void {
    comoOrganizacion();

    $metodologia = app(MetodologiaVigente::class)->para();

    expect($metodologia->esDeFabrica)->toBeTrue()
        ->and($metodologia->umbralAceptacion)->toBe(MetodologiaDeFabrica::UMBRAL_ACEPTACION)
        ->and(MetodologiaRiesgo::query()->count())->toBe(0);
});

it('guardar sin tocar nada no deja fila', function (): void {
    // El test que impide la trampa: entrar en la pantalla y pulsar Guardar no
    // puede congelar la metodología de esa organización.
    comoOrganizacion();

    $fila = app(GuardarMetodologia::class)(datosDeFabrica());

    expect($fila)->toBeNull()
        ->and(MetodologiaRiesgo::query()->count())->toBe(0)
        ->and(app(MetodologiaVigente::class)->para()->esDeFabrica)->toBeTrue();
});

it('volver a la de fábrica borra la fila que hubiera', function (): void {
    comoOrganizacion();

    $guardar = app(GuardarMetodologia::class);

    $guardar(datosDeFabrica(['umbral_aceptacion' => 5, 'umbral_critico' => 12]));
    expect(MetodologiaRiesgo::query()->count())->toBe(1);

    $guardar(datosDeFabrica());

    expect(MetodologiaRiesgo::query()->count())->toBe(0)
        ->and(app(MetodologiaVigente::class)->para()->esDeFabrica)->toBeTrue();
});

it('guardar algo distinto sí deja fila, y manda sobre la de fábrica', function (): void {
    comoOrganizacion();

    $fila = app(GuardarMetodologia::class)(datosDeFabrica([
        'nombre' => 'La nuestra',
        'umbral_aceptacion' => 5,
        'umbral_critico' => 12,
    ]));

    expect($fila)->not->toBeNull();

    $vigente = app(MetodologiaVigente::class)->para();

    expect($vigente->esDeFabrica)->toBeFalse()
        ->and($vigente->nombre)->toBe('La nuestra')
        ->and($vigente->umbralAceptacion)->toBe(5);
});

it('una escala con un hueco no llega a la base', function (): void {
    // Lo rechaza el value object, no el FormRequest: la regla vale igual para un
    // seeder o para un importador.
    comoOrganizacion();

    expect(fn () => app(GuardarMetodologia::class)(datosDeFabrica([
        'escala_impacto' => [
            ['valor' => 1, 'etiqueta' => 'Bajo'],
            ['valor' => 3, 'etiqueta' => 'Alto'],
        ],
    ])))->toThrow(EscalaInvalida::class);

    expect(MetodologiaRiesgo::query()->count())->toBe(0);
});

it('un umbral que no se puede cruzar tampoco', function (): void {
    comoOrganizacion();

    expect(fn () => app(GuardarMetodologia::class)(datosDeFabrica(['umbral_critico' => 99])))
        ->toThrow(MetodologiaIncoherente::class);

    expect(MetodologiaRiesgo::query()->count())->toBe(0);
});

it('firmarla deja la fila aunque coincida con la de fábrica', function (): void {
    /*
     * La excepción al borrado, y es deliberada: una fila aprobada ya no dice «no
     * lo he tocado», dice «lo he mirado y lo firmo». Esa firma con su fecha es
     * justo lo que pide el auditor, y perderla por parecerse a la de fábrica sería
     * el peor borrado posible.
     */
    comoOrganizacion();
    $usuario = usuarioCon();

    $fila = app(GuardarMetodologia::class)(datosDeFabrica(), $usuario);

    expect($fila)->not->toBeNull()
        ->and(MetodologiaRiesgo::query()->count())->toBe(1);

    $vigente = app(MetodologiaVigente::class)->para();

    expect($vigente->estaAprobada())->toBeTrue()
        ->and($vigente->esProvisional())->toBeFalse();
});

it('tocar la metodología invalida la firma anterior', function (): void {
    // La dirección firmó unas escalas y unos umbrales concretos, no un formulario.
    comoOrganizacion();
    $usuario = usuarioCon();
    $guardar = app(GuardarMetodologia::class);

    $guardar(datosDeFabrica(['nombre' => 'La nuestra']), $usuario);
    expect(app(MetodologiaVigente::class)->para()->estaAprobada())->toBeTrue();

    $guardar(datosDeFabrica(['nombre' => 'La nuestra', 'umbral_aceptacion' => 6, 'umbral_critico' => 12]));

    $vigente = app(MetodologiaVigente::class)->para();

    expect($vigente->estaAprobada())->toBeFalse()
        ->and($vigente->esProvisional())->toBeTrue()
        ->and($vigente->umbralAceptacion)->toBe(6);
});

it('la de fábrica es provisional mientras nadie la firme', function (): void {
    // Es lo que los documentos tendrán que declarar como limitación: un análisis
    // medido con una escala que nadie aprobó no es un hallazgo si se dice.
    comoOrganizacion();

    expect(app(MetodologiaVigente::class)->para()->esProvisional())->toBeTrue();
});
