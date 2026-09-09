<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La capa de recursos genérica
|--------------------------------------------------------------------------
|
| Lo que se comprueba aquí es el contrato: que la definición que viaja al
| frontend es la que declara el `Recurso`, y que filtros y ordenación se
| aplican SOLO si están declarados. Un parámetro de la query string que nadie
| declaró no puede tocar la consulta.
|
*/

function usuarioConSistemas(int $cuantos = 3): User
{
    $organizacion = comoOrganizacion();
    $marco = Marco::factory()->create();

    foreach (range(1, $cuantos) as $numero) {
        Sistema::factory()->de($organizacion)->conMarco($marco)->create([
            'codigo' => sprintf('SIS-%02d', $numero),
            'nombre' => "Sistema {$numero}",
        ]);
    }

    return User::factory()->create(['organizacion_id' => $organizacion->id]);
}

it('expone la definición del recurso que declara el Resource', function (): void {
    $usuario = usuarioConSistemas();

    $this->actingAs($usuario)
        ->get('/sistemas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('sistemas/Index')
            ->where('recurso.clave', 'sistemas')
            ->where('recurso.etiquetas.plural', 'Sistemas')
            ->where('recurso.ordenPorDefecto', 'codigo')
            ->has('recurso.columnas', 7)
            ->where('recurso.columnas.0.clave', 'codigo')
            ->where('recurso.columnas.0.ordenable', true)
            ->where('recurso.columnas.0.anclada', true)
            ->has('recurso.filtros', 6)
            ->has('filas', 3)
            ->where('meta.total', 3)
        );
});

it('marca como ocultas por defecto las columnas que el recurso declara ocultas', function (): void {
    $usuario = usuarioConSistemas(1);

    $this->actingAs($usuario)
        ->get('/sistemas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('recurso.columnas.6.clave', 'created_at')
            ->where('recurso.columnas.6.ocultaPorDefecto', true)
            ->where('recurso.columnas.0.ocultaPorDefecto', false)
        );
});

it('resuelve en el servidor las opciones de los filtros de selección', function (): void {
    $usuario = usuarioConSistemas(1);

    $this->actingAs($usuario)
        ->get('/sistemas')
        ->assertInertia(function (AssertableInertia $pagina): void {
            expect(filtroDeclarado($pagina, 'marco_id')['opciones'])->toHaveCount(1)
                ->and(filtroDeclarado($pagina, 'estado')['opciones'])->toHaveCount(count(EstadoSistema::cases()));
        });
});

it('dice bajo qué columna se pinta cada filtro, y cuál no cuelga de ninguna', function (): void {
    $usuario = usuarioConSistemas(1);

    $this->actingAs($usuario)
        ->get('/sistemas')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $busqueda = filtroDeclarado($pagina, 'q');
            $nombre = filtroDeclarado($pagina, 'nombre');
            $marco = filtroDeclarado($pagina, 'marco_id');

            // La búsqueda cruza varios campos: no es de ninguna columna, y
            // declara aparte en cuáles se resalta la coincidencia.
            expect($busqueda['tipo'])->toBe('busqueda')
                ->and($busqueda['columna'])->toBeNull()
                ->and($busqueda['resaltaEn'])->toBe(['codigo', 'nombre'])
                // Por defecto, la columna que se llama como la clave.
                ->and($nombre['columna'])->toBe('nombre')
                ->and($nombre['resaltaEn'])->toBe([])
                // Y cuando no coinciden, lo dice el recurso.
                ->and($marco['columna'])->toBe('marco');
        });
});

it('la búsqueda cruza los campos declarados y ninguno más', function (): void {
    $usuario = usuarioConSistemas(3);

    // Por nombre.
    $this->actingAs($usuario)
        ->get('/sistemas?filter[q]=Sistema 2')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 1)
            ->where('filas.0.codigo', 'SIS-02')
        );

    // Y por código, con el mismo cuadro.
    $this->actingAs($usuario)
        ->get('/sistemas?filter[q]=sis-03')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 1)
            ->where('filas.0.codigo', 'SIS-03')
        );
});

it('ignora un extremo de fecha que no es una fecha, en vez de reventar', function (): void {
    $usuario = usuarioConSistemas(3);

    // La query string es del usuario y estas URL se guardan y se comparten.
    // PostgreSQL responde a `>= '2026'` con un error de sintaxis: sin la
    // comprobación previa, eso era un 500.
    $this->actingAs($usuario)
        ->get('/sistemas?filter[created_at]=2026,')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 3));

    // Y una fecha de verdad sí estrecha.
    $this->actingAs($usuario)
        ->get('/sistemas?filter[created_at]=2000-01-01,2000-01-02')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 0));
});

it('trata los comodines del término de búsqueda como texto', function (): void {
    $usuario = usuarioConSistemas(3);

    // Sin escapar, `%` devolvería la tabla entera en lugar de nada.
    $this->actingAs($usuario)
        ->get('/sistemas?filter[q]=%')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 0));
});

it('aplica un filtro declarado', function (): void {
    $usuario = usuarioConSistemas(3);

    $this->actingAs($usuario)
        ->get('/sistemas?filter[nombre]=Sistema 2')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 1)
            ->where('filas.0.codigo', 'SIS-02')
            ->where('meta.filtros.nombre', 'Sistema 2')
        );
});

it('ignora un filtro que el recurso no declara', function (): void {
    $usuario = usuarioConSistemas(3);

    // `aplicables` es columna pero no filtro: no puede colarse por la query
    // string, ni siquiera llamándose como uno que sí existe en otro recurso.
    $this->actingAs($usuario)
        ->get('/sistemas?filter[aplicables]=3')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 3)
            ->where('meta.filtros', [])
        );
});

it('ordena por una columna declarada como ordenable', function (): void {
    $usuario = usuarioConSistemas(3);

    $this->actingAs($usuario)
        ->get('/sistemas?sort=-codigo')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.codigo', 'SIS-03')
            ->where('meta.orden', '-codigo')
        );
});

it('rechaza ordenar por una columna que no se declaró ordenable', function (): void {
    $usuario = usuarioConSistemas(3);

    $this->actingAs($usuario)
        ->get('/sistemas?sort=marco')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            // Cae al orden por defecto y lo dice en la meta, no simula haberlo aplicado.
            ->where('meta.orden', 'codigo')
            ->where('filas.0.codigo', 'SIS-01')
        );
});

it('sólo acepta los tamaños de página que el recurso declara', function (): void {
    $usuario = usuarioConSistemas(3);

    $this->actingAs($usuario)
        ->get('/sistemas?por_pagina=100000')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('meta.porPagina', 25));

    $this->actingAs($usuario)
        ->get('/sistemas?por_pagina=10')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('meta.porPagina', 10));
});

it('pagina', function (): void {
    $usuario = usuarioConSistemas(3);

    $this->actingAs($usuario)
        ->get('/sistemas?por_pagina=10&page=2')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('meta.pagina', 2)
            ->where('meta.ultimaPagina', 1)
            ->has('filas', 0)
        );
});

it('serializa los estados como valor, etiqueta y tono, no como cadena suelta', function (): void {
    $usuario = usuarioConSistemas(1);

    $this->actingAs($usuario)
        ->get('/sistemas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.estado.valor', 'activo')
            ->where('filas.0.estado.etiqueta', 'Activo')
            ->where('filas.0.estado.tono', 'activo')
        );
});
