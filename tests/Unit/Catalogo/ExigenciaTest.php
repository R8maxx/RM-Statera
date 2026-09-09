<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\Exigencia;
use App\Domain\Categorizacion\Enums\OrigenExigencia;

/*
|--------------------------------------------------------------------------
| Cómo se leen la exigencia y su origen
|--------------------------------------------------------------------------
|
| La tabla la lee gente que no se sabe el Anexo II de memoria. `R2` y
| `modulacion_dimension` son identificadores, no explicaciones.
|
*/

it('nombra los refuerzos por su nivel', function (): void {
    expect(Exigencia::refuerzo(2)->etiqueta())->toBe('Refuerzo 2')
        ->and(Exigencia::refuerzo(11)->etiqueta())->toBe('Refuerzo 11')
        ->and(Exigencia::aplica()->etiqueta())->toBe('Aplica')
        ->and(Exigencia::noAplica()->etiqueta())->toBe('No aplica');
});

it('colapsa todos los refuerzos en un solo tono', function (): void {
    // Su número no está acotado por el marco —ese es el motivo de que esto no
    // sea un enum—, así que un tono por nivel obligaría a tocar la interfaz
    // cada vez que el catálogo incorpora un `R4`.
    expect(Exigencia::refuerzo(1)->tono())->toBe('reforzado')
        ->and(Exigencia::refuerzo(4)->tono())->toBe('reforzado')
        ->and(Exigencia::aplica()->tono())->toBe('exigible')
        ->and(Exigencia::noAplica()->tono())->toBe('no_aplica');
});

it('explica de dónde sale cada exigencia', function (): void {
    expect(OrigenExigencia::Categoria->etiqueta())->toBe('Categoría del sistema')
        ->and(OrigenExigencia::ModulacionDimension->etiqueta())->toBe('Modulación por dimensión')
        ->and(OrigenExigencia::Perfil->etiqueta())->toBe('Perfil de cumplimiento')
        ->and(OrigenExigencia::Catalogo->etiqueta())->toBe('El propio marco');
});
