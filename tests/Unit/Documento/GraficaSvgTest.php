<?php

declare(strict_types=1);

use App\Domain\Documento\Render\GraficaSvg;
use App\Http\Resources\Panel\SegmentoEstado;

/**
 * La gráfica del documento.
 *
 * Se genera en el servidor y sin JavaScript, porque en un PDF/A un canvas entra
 * como mapa de bits y se lleva por delante el texto seleccionable.
 */
function segmentos(int ...$valores): array
{
    $claves = ['implantado', 'planificado', 'en_progreso', 'no_iniciado'];

    return array_map(
        static fn (string $clave, int $valor): SegmentoEstado => new SegmentoEstado($clave, ucfirst($clave), $valor),
        array_slice($claves, 0, count($valores)),
        $valores,
    );
}

it('lee los colores de los tokens y no los escribe a mano', function (): void {
    $svg = (new GraficaSvg)->barraPorEstado(segmentos(4, 2));

    expect($svg)->toContain('var(--estado-implantado)')
        ->and($svg)->toContain('var(--estado-planificado)')
        // Un hex suelto aquí sería una paleta que vive en dos sitios.
        ->and($svg)->not->toMatch('/#[0-9A-Fa-f]{6}/');
});

it('lleva alternativa textual con la cifra y su denominador', function (): void {
    $svg = (new GraficaSvg)->barraPorEstado(segmentos(4, 2, 1, 3));

    expect($svg)->toContain('role="img"')
        ->and($svg)->toContain('<title>')
        // «4 implantado» no dice nada; «4 de 10» sí.
        ->and($svg)->toContain('Implantado: 4 de 10');
});

it('no pinta tramos vacíos, que se leerían como una raya sin sentido', function (): void {
    $svg = (new GraficaSvg)->barraPorEstado(segmentos(5, 0, 0, 0));

    expect(substr_count($svg, '<rect'))->toBe(1);
    expect($svg)->not->toContain('var(--estado-planificado)');
});

it('devuelve nada cuando no hay nada que medir', function (): void {
    // Cero de cero no es una barra al cero por ciento: es que no hay barra.
    expect((new GraficaSvg)->barraPorEstado(segmentos(0, 0)))->toBe('');
    expect((new GraficaSvg)->barraPorEstado([]))->toBe('');
});

it('respeta el orden que le llega y no reordena por tamaño', function (): void {
    // El orden lo fija `ResumenCumplimiento::porEstado()` para que el azul quede
    // entre el verde y el ámbar: con protanopia son ΔE 5.7 y así suben a 14.0.
    $svg = (new GraficaSvg)->barraPorEstado(segmentos(1, 9));

    $implantado = strpos($svg, 'var(--estado-implantado)');
    $planificado = strpos($svg, 'var(--estado-planificado)');

    expect($implantado)->toBeLessThan($planificado);
});
