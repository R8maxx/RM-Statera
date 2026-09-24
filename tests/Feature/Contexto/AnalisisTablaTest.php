<?php

declare(strict_types=1);

use App\Domain\Contexto\Models\AnalisisContexto;

/**
 * El historial de revisiones del contexto, ahora sobre `AnalisisContextoRecurso`.
 *
 * Hasta que pasó a tabla la pantalla no tenía test propio: sólo se probaba la
 * aprobación. Lo que se clava es lo que la conversión podía romper sin que se
 * viera — el orden, con el borrador delante porque es el único sin número, y el
 * aislamiento, que no se hereda de la pantalla anterior sino de la consulta.
 */
it('lista las revisiones con el borrador delante y las altas contadas', function (): void {
    comoOrganizacion();
    AnalisisContexto::factory()->aprobado(1)->create();
    AnalisisContexto::factory()->create();

    $this->actingAs(usuarioCon())
        ->get('/contexto/analisis')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('contexto/Analisis')
            ->has('recurso')
            ->has('filas', 2)
            ->where('filas.0.numero', 'Borrador')
            ->where('filas.1.numero', 'Análisis n.º 1')
            ->where('filas.0.altas', '0')
            ->where('hayBorrador', true)
            ->where('meta.total', 2));
});

it('no enseña las revisiones de otra organización', function (): void {
    comoOrganizacion();
    AnalisisContexto::factory()->aprobado(1)->create();

    comoOrganizacion();
    $usuario = usuarioCon();

    $this->actingAs($usuario)
        ->get('/contexto/analisis')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->has('filas', 0)
            ->where('hayBorrador', false));
});
