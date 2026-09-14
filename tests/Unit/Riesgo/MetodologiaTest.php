<?php

declare(strict_types=1);

use App\Domain\Riesgo\EscalaRiesgo;
use App\Domain\Riesgo\Excepciones\MetodologiaIncoherente;
use App\Domain\Riesgo\Metodologia;
use App\Domain\Riesgo\MetodologiaDeFabrica;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| La metodología
|--------------------------------------------------------------------------
|
| Las dos escalas pueden ser válidas por separado y los umbrales seguir sin tener
| sentido. Un umbral que no se puede cruzar deja el indicador de «riesgos por
| encima del umbral» en cero para siempre, y nadie sabría por qué: no hay error,
| no hay aviso, simplemente nunca salta.
|
*/

/** @param array<string, mixed> $cambios */
function metodologia(array $cambios = []): Metodologia
{
    $escalones = [
        ['valor' => 1, 'etiqueta' => 'Baja'],
        ['valor' => 2, 'etiqueta' => 'Media'],
        ['valor' => 3, 'etiqueta' => 'Alta'],
    ];

    return new Metodologia(
        nombre: $cambios['nombre'] ?? 'De prueba',
        referencia: $cambios['referencia'] ?? null,
        probabilidad: $cambios['probabilidad'] ?? EscalaRiesgo::desdeArray($escalones),
        impacto: $cambios['impacto'] ?? EscalaRiesgo::desdeArray($escalones),
        umbralAceptacion: $cambios['umbralAceptacion'] ?? 4,
        umbralCritico: $cambios['umbralCritico'] ?? 7,
        periodicidadRevisionMeses: $cambios['periodicidadRevisionMeses'] ?? 12,
        esDeFabrica: $cambios['esDeFabrica'] ?? false,
        aprobadaPor: $cambios['aprobadaPor'] ?? null,
        aprobadaEn: $cambios['aprobadaEn'] ?? null,
    );
}

it('rechaza un umbral crítico por debajo del de aceptación', function (): void {
    // Dejaría una franja donde el riesgo es tolerable e inasumible a la vez.
    expect(fn () => metodologia(['umbralAceptacion' => 6, 'umbralCritico' => 4]))
        ->toThrow(MetodologiaIncoherente::class, 'no puede estar por debajo');
});

it('rechaza un umbral que no se puede cruzar', function (): void {
    // Escala 3×3: el riesgo máximo es 9. Un umbral de 12 nunca salta.
    expect(fn () => metodologia(['umbralCritico' => 12]))
        ->toThrow(MetodologiaIncoherente::class, 'una línea que no se puede cruzar');
});

it('rechaza un umbral de aceptación que deja todo por encima', function (): void {
    // Con 1, todo riesgo está sobre el umbral y el umbral deja de separar nada.
    expect(fn () => metodologia(['umbralAceptacion' => 1, 'umbralCritico' => 5]))
        ->toThrow(MetodologiaIncoherente::class, 'deja de separar nada');
});

it('rechaza una periodicidad que no es una periodicidad', function (int $meses): void {
    expect(fn () => metodologia(['periodicidadRevisionMeses' => $meses]))
        ->toThrow(MetodologiaIncoherente::class, 'no es una periodicidad');
})->with(['sin revisión' => 0, 'negativa' => -3, 'de seis años' => 61]);

it('admite los umbrales pegados al techo de la matriz', function (): void {
    $metodologia = metodologia(['umbralAceptacion' => 9, 'umbralCritico' => 9]);

    expect($metodologia->riesgoMaximo())->toBe(9)
        ->and($metodologia->umbralCritico)->toBe(9);
});

it('sólo está aprobada con firma y fecha', function (): void {
    expect(metodologia()->estaAprobada())->toBeFalse()
        ->and(metodologia(['aprobadaPor' => 'La dirección'])->estaAprobada())->toBeFalse()
        ->and(metodologia(['aprobadaEn' => Carbon::parse('2026-03-01')])->estaAprobada())->toBeFalse()
        ->and(metodologia([
            'aprobadaPor' => 'La dirección',
            'aprobadaEn' => Carbon::parse('2026-03-01'),
        ])->estaAprobada())->toBeTrue();
});

it('es provisional mientras sea la de fábrica o no esté aprobada', function (): void {
    // Es lo que los documentos tendrán que declarar como limitación: un análisis
    // medido con una escala que nadie aprobó no es un hallazgo si se dice.
    expect(MetodologiaDeFabrica::metodologia()->esProvisional())->toBeTrue()
        ->and(metodologia()->esProvisional())->toBeTrue()
        ->and(metodologia([
            'aprobadaPor' => 'La dirección',
            'aprobadaEn' => Carbon::parse('2026-03-01'),
        ])->esProvisional())->toBeFalse();
});

it('la de fábrica es coherente consigo misma', function (): void {
    $fabrica = MetodologiaDeFabrica::metodologia();

    expect($fabrica->esDeFabrica)->toBeTrue()
        ->and($fabrica->probabilidad->maximo())->toBe(5)
        ->and($fabrica->impacto->maximo())->toBe(5)
        ->and($fabrica->riesgoMaximo())->toBe(25)
        ->and($fabrica->umbralAceptacion)->toBeLessThan($fabrica->umbralCritico)
        ->and($fabrica->umbralCritico)->toBeLessThanOrEqual($fabrica->riesgoMaximo());
});

it('cada escalón de la fábrica se explica con palabras', function (): void {
    // Un «3» sin descripción lo interpreta cada persona a su manera, y a la
    // tercera valoración el análisis deja de ser comparable consigo mismo.
    foreach ([MetodologiaDeFabrica::probabilidad(), MetodologiaDeFabrica::impacto()] as $escala) {
        foreach ($escala->niveles as $nivel) {
            expect($nivel->etiqueta)->not->toBe('');

            expect($nivel->descripcion !== null)->toBeTrue(
                "El escalón {$nivel->valor} de la fábrica no se explica con palabras.",
            );
        }
    }
});

it('sobrevive a la ida y vuelta que se congela en cada valoración', function (): void {
    // Se comprueba lo que hace falta para releer un número viejo: las dos
    // escalas y las dos líneas. La periodicidad no viaja, porque es de la
    // metodología y no de la medición, así que aquí no se compara con
    // `equivale()` — lo haría pasar por casualidad.
    $original = MetodologiaDeFabrica::metodologia();
    $recuperada = Metodologia::desdeArray($original->aArray());

    expect($recuperada->probabilidad->equivale($original->probabilidad))->toBeTrue()
        ->and($recuperada->impacto->equivale($original->impacto))->toBeTrue()
        ->and($recuperada->umbralAceptacion)->toBe($original->umbralAceptacion)
        ->and($recuperada->umbralCritico)->toBe($original->umbralCritico)
        ->and($recuperada->nombre)->toBe($original->nombre)
        ->and($recuperada->impacto->nivelDe(4)->etiqueta)->toBe('Alto');
});

it('dos metodologías con distinta escala no se consideran iguales', function (): void {
    $tres = metodologia();
    $cinco = metodologia([
        'probabilidad' => MetodologiaDeFabrica::probabilidad(),
        'impacto' => MetodologiaDeFabrica::impacto(),
        'umbralAceptacion' => 8,
        'umbralCritico' => 15,
    ]);

    expect($tres->equivale($cinco))->toBeFalse()
        ->and($tres->equivale(metodologia()))->toBeTrue();
});
