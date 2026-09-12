<?php

declare(strict_types=1);

use App\Domain\Tarea\Enums\EstadoTarea;

it('deja reabrir lo cerrado', function (): void {
    expect(EstadoTarea::Hecha->permite(EstadoTarea::EnCurso))->toBeTrue()
        ->and(EstadoTarea::Descartada->permite(EstadoTarea::Pendiente))->toBeTrue();
});

it('no deja saltar de hecha a descartada', function (): void {
    // Son dos cierres distintos: para cambiar de uno a otro hay que reabrir, y
    // esa reapertura queda en el histórico.
    expect(EstadoTarea::Hecha->permite(EstadoTarea::Descartada))->toBeFalse()
        ->and(EstadoTarea::Descartada->permite(EstadoTarea::Hecha))->toBeFalse();
});

it('cuenta como cerradas las dos formas de cerrar', function (): void {
    expect(EstadoTarea::Hecha->esCerrada())->toBeTrue()
        ->and(EstadoTarea::Descartada->esCerrada())->toBeTrue()
        ->and(EstadoTarea::Bloqueada->esCerrada())->toBeFalse();
});

it('ningún estado gasta el rojo, que es del plazo', function (): void {
    foreach (EstadoTarea::cases() as $estado) {
        expect($estado->tono())->not->toBe('caducada');
    }
});

it('todas las transiciones declaradas van a un estado distinto', function (): void {
    foreach (EstadoTarea::cases() as $estado) {
        expect($estado->transicionesPermitidas())->not->toContain($estado);
    }
});
