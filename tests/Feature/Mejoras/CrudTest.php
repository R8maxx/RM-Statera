<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\Mejora\RegistroMejoras;
use App\Http\Resources\Panel\Indicador;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El registro de oportunidades de mejora por la interfaz
|--------------------------------------------------------------------------
|
| Lo que se fija aquí es lo que separa este registro del de al lado: **no manda
| alertas** —ninguna cifra de aquí va mal de verdad— y **no exige fecha nunca**,
| porque nadie se compromete a una mejora. Lo que sí hace es no dejar que se
| pierda: «sin empezar» es la cifra honesta de un buzón de ideas.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('lista las mejoras sin mandar ninguna alerta', function (): void {
    Mejora::factory()->count(3)->create();

    $this->actingAs($this->usuario)
        ->get('/mejoras')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('mejoras/Index')
            ->where('total', 3)
            ->has('pendientes')
            // El único registro del producto sin `alertas`, y es deliberado.
            ->missing('alertas')
            ->has('filas', 3));
});

it('propone el código siguiente y no repite el primero del año', function (): void {
    $anio = Carbon::today()->year;

    Mejora::factory()->create(['codigo' => "OM-{$anio}-01"]);
    Mejora::factory()->create(['codigo' => "OM-{$anio}-04"]);

    $this->actingAs($this->usuario)
        ->get('/mejoras/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('mejoras/Formulario')
            ->where('sugerencia.codigo', "OM-{$anio}-05"));
});

it('apunta una mejora sin fecha prevista y la deja propuesta', function (): void {
    $this->actingAs($this->usuario)
        ->post('/mejoras', [
            'codigo' => 'OM-2026-09',
            'origen' => OrigenMejora::Indicador->value,
            'titulo' => 'Automatizar el inventario de software de los puestos',
            'fecha_deteccion' => Carbon::today()->toDateString(),
            'fecha_prevista' => null,
        ])
        ->assertRedirect();

    $mejora = Mejora::query()->where('codigo', 'OM-2026-09')->firstOrFail();

    expect($mejora->estado)->toBe(EstadoMejora::Propuesta)
        ->and($mejora->origen)->toBe(OrigenMejora::Indicador)
        ->and($mejora->fecha_prevista)->toBeNull()
        // El alta deja su fila de histórico, con `estado_anterior` nulo.
        ->and($mejora->transiciones()->count())->toBe(1);
});

it('el estado no se mueve desde el formulario de edición', function (): void {
    $mejora = Mejora::factory()->create();

    $this->actingAs($this->usuario)
        ->put("/mejoras/{$mejora->id}", [
            'codigo' => $mejora->codigo,
            'origen' => $mejora->origen->value,
            'titulo' => $mejora->titulo,
            'fecha_deteccion' => $mejora->fecha_deteccion->toDateString(),
            'estado' => EstadoMejora::Implantada->value,
        ])
        ->assertRedirect();

    // Cerrar es un gesto con fecha e histórico: no cabe en un `update`.
    expect($mejora->refresh()->estado)->toBe(EstadoMejora::Propuesta)
        ->and($mejora->fecha_cierre)->toBeNull();
});

it('el código es único dentro de la organización y no del mundo', function (): void {
    Mejora::factory()->create(['codigo' => 'OM-2026-01']);

    $this->actingAs($this->usuario)
        ->post('/mejoras', [
            'codigo' => 'OM-2026-01',
            'origen' => OrigenMejora::Propia->value,
            'titulo' => 'Otra mejora',
            'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertSessionHasErrors('codigo');

    $otra = comoOrganizacion();
    $suUsuario = usuarioCon(Rol::ResponsableSeguridad, $otra);

    $this->actingAs($suUsuario)
        ->post('/mejoras', [
            'codigo' => 'OM-2026-01',
            'origen' => OrigenMejora::Propia->value,
            'titulo' => 'La primera de la otra organización',
            'fecha_deteccion' => Carbon::today()->toDateString(),
        ])
        ->assertRedirect();
});

it('cada indicador del registro cuenta lo mismo que su filtro', function (): void {
    // Una sin empezar y sin nada en marcha.
    Mejora::factory()->create();
    // Otra en curso: abierta, pero ya no «sin empezar».
    Mejora::factory()->enEstado(EstadoMejora::EnCurso)->create();
    // Y una cerrada, que no cuenta en ninguna de las dos.
    Mejora::factory()->enEstado(EstadoMejora::Implantada)->create();

    $registro = app(RegistroMejoras::class);

    $valor = static fn (string $clave): ?int => collect($registro->pendientes())
        ->first(fn (Indicador $uno): bool => $uno->clave === $clave)?->valor;

    expect($valor('abiertas'))->toBe(2)
        ->and($valor('sin_empezar'))->toBe(1);

    // Y las listas que abren esas cifras enseñan exactamente esas cifras.
    $this->actingAs($this->usuario)
        ->get('/mejoras?filter[abiertas]=1')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 2));

    $this->actingAs($this->usuario)
        ->get('/mejoras?filter[sin_empezar]=1')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 1));
});

it('el reparto por estado incluye los cerrados', function (): void {
    Mejora::factory()->create();
    Mejora::factory()->enEstado(EstadoMejora::Implantada)->create();
    Mejora::factory()->enEstado(EstadoMejora::Descartada)->create();

    /*
     * La pregunta de este registro es «de las que hemos apuntado, cuántas hemos
     * llegado a hacer», que es lo que la revisión por la dirección lee de la
     * 10.1. Sin los cerrados en el reparto esa pregunta no tiene respuesta.
     */
    expect(app(RegistroMejoras::class)->porEstado())->toBe([
        'propuesta' => 1,
        'en_curso' => 0,
        'implantada' => 1,
        'descartada' => 1,
    ]);
});
