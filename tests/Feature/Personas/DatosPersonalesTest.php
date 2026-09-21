<?php

declare(strict_types=1);

use App\Domain\Persona\Models\Persona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Los datos de la persona: el nombre derivado y el documento
|--------------------------------------------------------------------------
|
| `personas.nombre` es una columna GENERADA de PostgreSQL. Eso tiene dos
| consecuencias que no se ven leyendo el modelo y que este fichero clava:
|
| 1. Nadie puede escribirla —ni por asignación masiva, ni a mano—, porque
|    entonces el nombre completo y sus partes podrían discrepar.
| 2. Después de un `INSERT` hay que releer la fila, porque Eloquent sólo
|    recupera el `id`. Sin eso `$persona->nombre` es `null` y revienta lejos de
|    la causa.
|
| Y el NIF es un dato personal en una herramienta que está en el alcance de su
| propio SGSI: no se busca por él y no viaja a la tabla salvo que se pida.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('arma el nombre completo desde sus partes', function (): void {
    $persona = Persona::factory()->create([
        'nombre_pila' => 'María',
        'apellido1' => 'de la Fuente',
        'apellido2' => 'Gómez',
    ]);

    expect($persona->nombre)->toBe('María de la Fuente Gómez');
});

/**
 * El hueco doble es el caso que se escapa, y es el motivo por el que la
 * expresión de la columna lleva `regexp_replace` y no un `||` a secas: sin él,
 * un apellido vacío deja «Ana  Prat», que es lo que `concat_ws` evitaba
 * saltándose los nulos — y `concat_ws` no se puede usar porque no es IMMUTABLE.
 */
it('no deja huecos dobles cuando falta un apellido', function (): void {
    $sinSegundo = Persona::factory()->create([
        'nombre_pila' => 'Ana',
        'apellido1' => 'Prat',
        'apellido2' => null,
    ]);

    $sinNinguno = Persona::factory()->create([
        'nombre_pila' => 'Bruno',
        'apellido1' => null,
        'apellido2' => null,
    ]);

    $conEspacios = Persona::factory()->create([
        'nombre_pila' => '  Carla  ',
        'apellido1' => 'Ibáñez',
        'apellido2' => null,
    ]);

    expect($sinSegundo->nombre)->toBe('Ana Prat')
        ->and($sinNinguno->nombre)->toBe('Bruno')
        ->and($conEspacios->nombre)->toBe('Carla Ibáñez');
});

/**
 * El nombre recién creado llega relleno.
 *
 * Es el fallo que destapó esta migración: `Persona::factory()->create()`
 * devolvía el modelo sin `nombre`, y `DesignarRol` moría con un `TypeError` que
 * no menciona ni la columna ni la palabra «generada».
 */
it('trae el nombre ya calculado justo después de crear la fila', function (): void {
    $persona = Persona::query()->create([
        'codigo' => 'PER-100',
        'nombre_pila' => 'Elena',
        'apellido1' => 'Prat',
        'fecha_alta' => Carbon::today(),
    ]);

    expect($persona->nombre)->toBe('Elena Prat');
});

it('se niega a que nadie escriba el nombre completo a mano', function (): void {
    $persona = Persona::factory()->create();

    expect(function () use ($persona): void {
        $persona->nombre = 'Lo que sea';
    })->toThrow(LogicException::class);
});

/**
 * El nombre se recalcula solo al cambiar una parte: es lo que garantiza que no
 * puedan discrepar, y lo que hace innecesario acordarse de nada al editar.
 */
it('recalcula el nombre al cambiar un apellido', function (): void {
    $persona = Persona::factory()->create([
        'nombre_pila' => 'Ana',
        'apellido1' => 'Ruiz',
        'apellido2' => null,
    ]);

    $persona->update(['apellido2' => 'Beltrán']);

    expect($persona->refresh()->nombre)->toBe('Ana Ruiz Beltrán');
});

it('da de alta con documento, teléfonos y dirección', function (): void {
    $this->actingAs($this->usuario)
        ->post('/personas', [
            'codigo' => 'PER-200',
            'nombre_pila' => 'Diego',
            'apellido1' => 'Ferrer',
            'nif' => '12345678z',
            'telefono' => '+34 600 111 222',
            'telefono_fijo' => '960 111 222',
            'direccion' => "Calle Mayor 1\n46001 València",
            'fecha_nacimiento' => '1985-03-14',
            'fecha_alta' => Carbon::today()->toDateString(),
        ])
        ->assertRedirect();

    $persona = Persona::query()->where('codigo', 'PER-200')->sole();

    // Normalizado: en mayúsculas y sin separadores, o «12345678z» y
    // «12345678-Z» serían dos documentos distintos para el índice único.
    expect($persona->nif)->toBe('12345678Z')
        ->and($persona->telefono)->toBe('+34 600 111 222')
        ->and($persona->fecha_nacimiento?->toDateString())->toBe('1985-03-14');
});

it('rechaza dos personas con el mismo documento en la organización', function (): void {
    Persona::factory()->create(['nif' => '11111111H']);

    $this->actingAs($this->usuario)
        ->post('/personas', [
            'codigo' => 'PER-201',
            'nombre_pila' => 'Otra',
            'nif' => '11111111-h',
            'fecha_alta' => Carbon::today()->toDateString(),
        ])
        ->assertSessionHasErrors('nif');
});

/**
 * El documento es único **por organización** y no en toda la tabla: dos
 * clientes distintos pueden tener en plantilla a la misma persona, y con un
 * índice global el segundo no podría darla de alta.
 */
it('deja el mismo documento en dos organizaciones distintas', function (): void {
    $otra = comoOrganizacion();
    Persona::factory()->create(['nif' => '22222222J']);

    comoOrganizacion($this->organizacion);

    $this->actingAs($this->usuario)
        ->post('/personas', [
            'codigo' => 'PER-202',
            'nombre_pila' => 'Misma',
            'apellido1' => 'Persona',
            'nif' => '22222222J',
            'fecha_alta' => Carbon::today()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect($otra->id)->not->toBe($this->organizacion->id);
});

it('deja sin documento a toda la plantilla que haga falta', function (): void {
    Persona::factory()->count(3)->create(['nif' => null]);

    expect(Persona::query()->whereNull('nif')->count())->toBe(3);
});

/**
 * Protección de datos: el documento no entra en la búsqueda libre.
 *
 * Buscar «11111111H» y que salga la persona convertiría la caja de búsqueda en
 * un buscador de documentos de identidad, que no es para lo que está.
 */
it('no busca por el documento', function (): void {
    Persona::factory()->create(['nombre_pila' => 'Ana', 'apellido1' => 'Ruiz', 'nif' => '33333333P']);

    $this->actingAs($this->usuario)
        ->get('/personas?filter[q]=33333333P')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 0));

    $this->actingAs($this->usuario)
        ->get('/personas?filter[q]=Ruiz')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 1));
});

/** La columna existe, pero llega oculta: se enseña a quien la pide. */
it('manda el documento como columna oculta', function (): void {
    $this->actingAs($this->usuario)
        ->get('/personas')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $columnas = collect($pagina->toArray()['props']['recurso']['columnas']);
            $nif = $columnas->firstWhere('clave', 'nif');

            expect($nif)->not->toBeNull()
                ->and($nif['ocultaPorDefecto'])->toBeTrue();
        });
});

/**
 * La columna generada tiene que seguir sirviendo para ordenar, que es la mitad
 * del motivo por el que es columna y no un accesor de PHP.
 */
it('ordena por el nombre completo', function (): void {
    Persona::factory()->create(['nombre_pila' => 'Zoe', 'apellido1' => 'Alonso', 'apellido2' => null]);
    Persona::factory()->create(['nombre_pila' => 'Ana', 'apellido1' => 'Zurita', 'apellido2' => null]);

    $nombres = DB::table('personas')->orderBy('nombre')->pluck('nombre')->all();

    expect($nombres)->toBe(['Ana Zurita', 'Zoe Alonso']);
});
