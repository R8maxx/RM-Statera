<?php

declare(strict_types=1);

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Enums\NivelMadurez;

it('no permite alcanzar no_aplica desde ningún estado', function (EstadoImplantacion $estado): void {
    // Se deriva de la valoración del sistema; no es una decisión de nadie.
    expect($estado->permite(EstadoImplantacion::NoAplica))->toBeFalse();
})->with(EstadoImplantacion::cases());

it('no_aplica no sale por sí solo a ningún estado', function (): void {
    expect(EstadoImplantacion::NoAplica->transicionesPermitidas())->toBeEmpty()
        ->and(EstadoImplantacion::NoAplica->esGestionablePorUsuario())->toBeFalse();
});

it('los otros cuatro estados sí los gestiona una persona', function (): void {
    $gestionables = array_filter(
        EstadoImplantacion::cases(),
        fn (EstadoImplantacion $estado): bool => $estado->esGestionablePorUsuario(),
    );

    expect($gestionables)->toHaveCount(4);
});

it('permite avanzar y retroceder por el ciclo de trabajo', function (): void {
    expect(EstadoImplantacion::NoIniciado->permite(EstadoImplantacion::Planificado))->toBeTrue()
        ->and(EstadoImplantacion::Planificado->permite(EstadoImplantacion::EnProgreso))->toBeTrue()
        ->and(EstadoImplantacion::EnProgreso->permite(EstadoImplantacion::Implantado))->toBeTrue()
        // La verificación de eficacia puede devolver algo dado por hecho.
        ->and(EstadoImplantacion::Implantado->permite(EstadoImplantacion::EnProgreso))->toBeTrue();
});

it('no deja fingir que algo implantado nunca se empezó', function (): void {
    expect(EstadoImplantacion::Implantado->permite(EstadoImplantacion::NoIniciado))->toBeFalse()
        ->and(EstadoImplantacion::EnProgreso->permite(EstadoImplantacion::NoIniciado))->toBeFalse();
});

it('ningún estado se permite a sí mismo', function (EstadoImplantacion $estado): void {
    expect($estado->permite($estado))->toBeFalse();
})->with(EstadoImplantacion::cases());

it('la escala de madurez es la L0-L5 del CCN', function (): void {
    expect(NivelMadurez::cases())->toHaveCount(6)
        ->and(NivelMadurez::L0->valor())->toBe(0)
        ->and(NivelMadurez::L5->valor())->toBe(5)
        ->and(NivelMadurez::L3->etiqueta())->toContain('Proceso definido');
});
