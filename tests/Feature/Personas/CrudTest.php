<?php

declare(strict_types=1);

use App\Domain\Persona\Models\Persona;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El registro de personas por la interfaz
|--------------------------------------------------------------------------
|
| Lo que más importa aquí es que `personas` y `users` son cosas distintas: una
| persona se da de alta sin cuenta —que es el caso mayoritario— y el puente es
| opcional en los dos sentidos.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('lista las personas con sus indicadores y la cobertura del 5.3', function (): void {
    Persona::factory()->count(3)->create();

    $this->actingAs($this->usuario)
        ->get('/personas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('personas/Index')
            ->where('total', 3)
            ->has('alertas')
            ->has('pendientes')
            ->has('cobertura.designados')
            ->has('cobertura.exigibles')
            ->has('filas', 3));
});

it('propone el código de la siguiente persona, sin año', function (): void {
    Persona::factory()->create(['codigo' => 'PER-007']);

    $this->actingAs($this->usuario)
        ->get('/personas/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('personas/Formulario')
            ->where('sugerencia.codigo', 'PER-008'));
});

/*
 * El fallo que destapó el § 6.2 y que muerde a todos los generadores: sin
 * `?::int`, PDO manda el desplazamiento como texto, PostgreSQL lee la forma con
 * expresión regular y devuelve NULL. El test que sólo pide el primer código del
 * año pasa igual con el contador roto, así que aquí se siembran dos.
 */
it('numera correlativo con varias personas ya registradas', function (): void {
    Persona::factory()->create(['codigo' => 'PER-001']);
    Persona::factory()->create(['codigo' => 'PER-002']);

    $this->actingAs($this->usuario)
        ->get('/personas/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('sugerencia.codigo', 'PER-003'));
});

it('da de alta a una persona sin cuenta de Statera', function (): void {
    $this->actingAs($this->usuario)
        ->post('/personas', [
            'codigo' => 'PER-010',
            'nombre_pila' => 'Carla',
            'apellido1' => 'Ibáñez',
            'puesto' => 'Atención al cliente',
            'email' => null,
            'user_id' => null,
            'fecha_alta' => Carbon::today()->toDateString(),
            'fecha_baja' => null,
        ])
        ->assertRedirect();

    $persona = Persona::query()->where('codigo', 'PER-010')->sole();

    expect($persona->user_id)->toBeNull()
        ->and($persona->estaActiva())->toBeTrue()
        // El nombre completo lo arma PostgreSQL desde las partes.
        ->and($persona->nombre)->toBe('Carla Ibáñez');
});

it('no deja vincular la misma cuenta a dos personas', function (): void {
    Persona::factory()->create(['user_id' => $this->usuario->id]);

    $this->actingAs($this->usuario)
        ->post('/personas', [
            'codigo' => 'PER-011',
            'nombre_pila' => 'Otra',
            'apellido1' => 'Persona',
            'user_id' => $this->usuario->id,
            'fecha_alta' => Carbon::today()->toDateString(),
        ])
        ->assertSessionHasErrors('user_id');
});

it('rechaza una baja anterior al alta', function (): void {
    $this->actingAs($this->usuario)
        ->post('/personas', [
            'codigo' => 'PER-012',
            'nombre_pila' => 'Elena',
            'apellido1' => 'Prat',
            'fecha_alta' => Carbon::today()->toDateString(),
            'fecha_baja' => Carbon::today()->subMonth()->toDateString(),
        ])
        ->assertSessionHasErrors('fecha_baja');
});

/*
 * Los «cuatro bloques» son los cuatro registros que la ficha recibe —nombramientos,
 * formación, acuerdos y checklists—, no sus tarjetas: la ficha los agrupa en tres
 * tarjetas y un lateral, y esa maquetación no la fija ningún test.
 */
it('pinta la ficha con sus cuatro bloques', function (): void {
    $persona = Persona::factory()->create();

    $this->actingAs($this->usuario)
        ->get("/personas/{$persona->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('personas/Ficha')
            ->where('persona.codigo', $persona->codigo)
            ->has('designaciones')
            ->has('formacion')
            ->has('acuerdos')
            // Las dos checklists salen siempre, aunque estén vacías: una de
            // salida sin abrir es exactamente lo que hay que poder abrir el día
            // que alguien se va.
            ->has('pasos', 2)
            ->etc());
});

/**
 * «Activa» se deriva de `fecha_baja`, no se guarda. Reincorporar a alguien es
 * vaciar un campo y no acordarse de dos.
 */
it('deriva la situación de la fecha de baja y no de una columna', function (): void {
    $persona = Persona::factory()->create();

    expect($persona->estaActiva())->toBeTrue();

    $persona->update(['fecha_baja' => Carbon::today()]);

    expect($persona->fresh()?->estaActiva())->toBeFalse();
});
