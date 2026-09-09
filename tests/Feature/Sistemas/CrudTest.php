<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Sistemas de punta a punta
|--------------------------------------------------------------------------
|
| El recurso de validación de la capa: formulario, FormRequest y CRUD. Lo que
| aquí se rompa se rompería igual en los dieciocho módulos siguientes.
|
*/

/**
 * @return array{usuario: User, organizacion: Organizacion, marco: Marco}
 */
function escenarioDeSistemas(): array
{
    $organizacion = comoOrganizacion();

    return [
        'usuario' => usuarioCon(),
        'organizacion' => $organizacion,
        'marco' => Marco::factory()->create(),
    ];
}

it('sirve el formulario de alta con las opciones resueltas', function (): void {
    $escenario = escenarioDeSistemas();

    $this->actingAs($escenario['usuario'])
        ->get('/sistemas/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('sistemas/Formulario')
            ->where('sistema', null)
            ->has('marcos', 1)
            ->has('estados', 3)
        );
});

it('crea un sistema y le pone la organización activa sin que nadie la envíe', function (): void {
    $escenario = escenarioDeSistemas();

    $this->actingAs($escenario['usuario'])
        ->post('/sistemas', [
            'codigo' => 'SIS-99',
            'nombre' => 'Plataforma de pruebas',
            'marco_id' => $escenario['marco']->id,
            'estado' => 'activo',
        ])
        ->assertRedirect('/sistemas');

    $this->actingAs($escenario['usuario'])
        ->get('/sistemas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->hasFlash('exito'));

    $sistema = Sistema::query()->where('codigo', 'SIS-99')->firstOrFail();

    expect($sistema->organizacion_id)->toBe($escenario['organizacion']->id)
        ->and($sistema->nombre)->toBe('Plataforma de pruebas');
});

it('rechaza el alta sin los campos obligatorios', function (): void {
    $escenario = escenarioDeSistemas();

    $this->actingAs($escenario['usuario'])
        ->post('/sistemas', [])
        ->assertSessionHasErrors(['codigo', 'nombre', 'marco_id', 'estado']);

    expect(Sistema::query()->count())->toBe(0);
});

it('rechaza un estado que no existe en el enum', function (): void {
    $escenario = escenarioDeSistemas();

    $this->actingAs($escenario['usuario'])
        ->post('/sistemas', [
            'codigo' => 'SIS-98',
            'nombre' => 'Sistema',
            'marco_id' => $escenario['marco']->id,
            'estado' => 'inventado',
        ])
        ->assertSessionHasErrors('estado');
});

it('el código es único dentro de la organización, no entre organizaciones', function (): void {
    $escenario = escenarioDeSistemas();

    Sistema::factory()->de($escenario['organizacion'])->conMarco($escenario['marco'])
        ->create(['codigo' => 'SIS-01']);

    $this->actingAs($escenario['usuario'])
        ->post('/sistemas', [
            'codigo' => 'SIS-01',
            'nombre' => 'Duplicado',
            'marco_id' => $escenario['marco']->id,
            'estado' => 'activo',
        ])
        ->assertSessionHasErrors('codigo');

    // La misma clave en otra organización es perfectamente válida.
    $otra = Organizacion::factory()->create();
    comoOrganizacion($otra);
    $usuarioAjeno = usuarioCon(organizacion: $otra);

    $this->actingAs($usuarioAjeno)
        ->post('/sistemas', [
            'codigo' => 'SIS-01',
            'nombre' => 'Mismo código, otra organización',
            'marco_id' => $escenario['marco']->id,
            'estado' => 'activo',
        ])
        ->assertRedirect('/sistemas')
        ->assertSessionHasNoErrors();
});

it('edita un sistema y no se queja del código que ya tenía', function (): void {
    $escenario = escenarioDeSistemas();

    $sistema = Sistema::factory()->de($escenario['organizacion'])->conMarco($escenario['marco'])
        ->create(['codigo' => 'SIS-07', 'nombre' => 'Antes']);

    $this->actingAs($escenario['usuario'])
        ->get("/sistemas/{$sistema->id}/editar")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('sistemas/Formulario')
            ->where('sistema.codigo', 'SIS-07')
        );

    $this->actingAs($escenario['usuario'])
        ->put("/sistemas/{$sistema->id}", [
            'codigo' => 'SIS-07',
            'nombre' => 'Después',
            'marco_id' => $escenario['marco']->id,
            'estado' => 'archivado',
        ])
        ->assertRedirect('/sistemas')
        ->assertSessionHasNoErrors();

    expect($sistema->fresh()->nombre)->toBe('Después')
        ->and($sistema->fresh()->estado->value)->toBe('archivado');
});

it('borra un sistema', function (): void {
    $escenario = escenarioDeSistemas();

    $sistema = Sistema::factory()->de($escenario['organizacion'])->conMarco($escenario['marco'])->create();

    $this->actingAs($escenario['usuario'])
        ->delete("/sistemas/{$sistema->id}")
        ->assertRedirect('/sistemas');

    expect(Sistema::query()->count())->toBe(0);
});

it('resuelve el modelo de la ruta con el contexto que fija el middleware', function (): void {
    $escenario = escenarioDeSistemas();
    $sistema = Sistema::factory()->de($escenario['organizacion'])->conMarco($escenario['marco'])->create();

    // Sin esto el contexto seguiría puesto en el contenedor desde el montaje y
    // el test pasaría sin que el middleware hiciera nada. El caso real es una
    // petición que llega sin contexto: si `SubstituteBindings` corriera antes,
    // el scope no encontraría la fila y toda ruta con `{sistema}` daría 404,
    // incluida la propia.
    sinOrganizacion();

    $this->actingAs($escenario['usuario'])
        ->get("/sistemas/{$sistema->id}/editar")
        ->assertOk();

    sinOrganizacion();

    $this->actingAs($escenario['usuario'])
        ->delete("/sistemas/{$sistema->id}")
        ->assertRedirect('/sistemas');
});

it('exige sesión iniciada', function (): void {
    $this->get('/sistemas')->assertRedirect('/login');
    $this->post('/sistemas', [])->assertRedirect('/login');
});
