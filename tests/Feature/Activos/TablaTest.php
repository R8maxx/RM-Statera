<?php

declare(strict_types=1);

use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\RegistrarDependencia;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Sistema\Models\Sistema;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La tabla del inventario
|--------------------------------------------------------------------------
|
| Lo que aquí se prueba y no en los otros recursos: que la columna «Valoración»
| enseñe la EFECTIVA —la que cuenta el grafo— y que el filtro por alcance no
| duplique filas, que es lo que pasaría resolviendo un N:M con un join.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $this->sistema = Sistema::factory()->de($this->organizacion)->create(['codigo' => 'SIS-01']);
    $this->otroSistema = Sistema::factory()->de($this->organizacion)->create(['codigo' => 'SGSI-01']);

    $this->servicio = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Servicios)->create([
        'codigo' => 'SRV-01',
        'nombre' => 'Sede electrónica',
        'valor_d' => NivelDimension::Alto->value,
    ]);

    $this->base = Activo::factory()->de($this->organizacion)->deTipo(TipoActivo::Datos)->create([
        'codigo' => 'BBDD-01',
        'nombre' => 'Base de datos de expedientes',
    ]);

    app(RegistrarDependencia::class)->vincular($this->servicio, $this->base);
});

it('ordena por código y devuelve el orden aplicado de verdad', function (): void {
    $this->actingAs($this->usuario)
        ->get('/activos')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('activos/Index')
            ->where('filas.0.codigo', 'BBDD-01')
            ->where('filas.1.codigo', 'SRV-01')
            ->where('meta.orden', 'codigo')
        );
});

it('enseña la valoración efectiva y no la que alguien tecleó', function (): void {
    $this->actingAs($this->usuario)
        ->get('/activos')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            // La base de datos no está valorada, pero sostiene un servicio de
            // disponibilidad alta: es el activo que una hoja de cálculo deja
            // desprotegido.
            ->where('filas.0.valoracion.etiqueta', 'Alto (heredado)')
            ->where('filas.0.valoracion.tono', 'alta')
            ->where('filas.0.heredada', true)
            ->where('filas.0.valoracion_propia.etiqueta', 'Sin valorar')
            ->where('filas.1.heredada', false)
        );
});

it('cuenta cuántos activos sostiene cada uno', function (): void {
    $this->actingAs($this->usuario)
        ->get('/activos')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('filas.0.dependientes', 1)
            ->where('filas.1.dependientes', 0)
        );
});

it('filtra por tipo y por estado', function (): void {
    $this->actingAs($this->usuario)
        ->get('/activos?filter[tipo]='.TipoActivo::Datos->value)
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 1)
            ->where('filas.0.codigo', 'BBDD-01')
            ->where('meta.filtros.tipo', TipoActivo::Datos->value)
        );

    $this->actingAs($this->usuario)
        ->get('/activos?filter[estado_ciclo_vida]='.EstadoCicloVida::Retirado->value)
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 0));
});

it('busca por código, nombre y ubicación a la vez', function (): void {
    $this->actingAs($this->usuario)
        ->get('/activos?filter[q]=expedientes')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 1)
            ->where('filas.0.codigo', 'BBDD-01')
        );
});

it('filtra por alcance sin duplicar la fila del activo que está en dos sistemas', function (): void {
    $this->servicio->sistemas()->sync([
        $this->sistema->id => ['organizacion_id' => $this->organizacion->id],
        $this->otroSistema->id => ['organizacion_id' => $this->organizacion->id],
    ]);

    $this->actingAs($this->usuario)
        ->get("/activos?filter[sistema_id]={$this->sistema->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            // Con un join saldría dos veces y el total diría 2.
            ->has('filas', 1)
            ->where('filas.0.codigo', 'SRV-01')
            ->where('filas.0.alcance', 'SIS-01, SGSI-01')
            ->where('meta.total', 1)
        );
});

it('ignora un filtro no declarado en vez de romper la petición', function (): void {
    // Una URL guardada no puede convertirse en un error porque se renombre algo.
    $this->actingAs($this->usuario)
        ->get('/activos?filter[inventado]=lo-que-sea&sort=tampoco-existe')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 2)
            ->where('meta.orden', 'codigo')
            ->where('meta.filtros', [])
        );
});

it('no enseña activos de otra organización', function (): void {
    $ajena = comoOrganizacion();
    Activo::factory()->de($ajena)->create(['codigo' => 'AJENO-01']);
    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->get('/activos')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));
});

it('declara el filtro de alcance bajo la columna que se ve', function (): void {
    $this->actingAs($this->usuario)
        ->get('/activos')
        ->assertInertia(function (AssertableInertia $pagina): void {
            expect(filtroDeclarado($pagina, 'sistema_id')['columna'])->toBe('alcance');
            expect(filtroDeclarado($pagina, 'propietario_id')['columna'])->toBe('propietario');
            expect(filtroDeclarado($pagina, 'custodio_id')['columna'])->toBe('custodio');
        });
});
