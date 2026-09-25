<?php

declare(strict_types=1);

use App\Domain\Activo\GeneradorEtiquetas;
use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Traza\Models\EventoAuditoria;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La ficha de la organización
|--------------------------------------------------------------------------
|
| `organizaciones` es la raíz del tenant y hasta aquí no tenía pantalla: sus
| columnas sólo se tocaban por seeder o entrando en la base. Dos de ellas ya
| decidían cosas que se imprimen —`url_base_etiquetas` gobierna los QR ya
| pegados en el parque, y las dos banderas del ENS deciden qué dice la DdA—.
|
| Lo que más se prueba aquí es lo que no se ve:
|
| 1. **La cadena vacía de `url_base_etiquetas`**, que atraviesa el `??` y deja
|    QR sin host en etiquetas que alguien va a imprimir.
| 2. **Que la traza se escriba de verdad**, porque el trait sale en silencio
|    sobre un modelo sin `organizacion_id` y ésta es la única tabla que no la
|    tiene.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon(Rol::ResponsableSeguridad);
});

/** La ficha completa, tal y como la manda el formulario. */
function fichaCompleta(array $cambios = []): array
{
    return [
        'nombre' => 'Talleres Merino',
        'razon_social' => 'Talleres Merino y Asociados, S.L.',
        'cif' => 'B12345678',
        'sector' => 'Metalurgia',
        'domicilio' => 'Calle del Tinte, 14',
        'codigo_postal' => '19001',
        'municipio' => 'Guadalajara',
        'provincia' => 'Guadalajara',
        'url_base_etiquetas' => 'https://sgsi.merino.example',
        'sujeto_obligado_ens' => false,
        'proveedor_sector_publico' => true,
        'reevaluacion_proveedor_alta_meses' => 12,
        'reevaluacion_proveedor_media_meses' => 24,
        'reevaluacion_proveedor_baja_meses' => 36,
        'plazo_vulnerabilidad_critica_dias' => 7,
        'plazo_vulnerabilidad_alta_dias' => 30,
        'plazo_vulnerabilidad_media_dias' => 90,
        'plazo_vulnerabilidad_baja_dias' => 180,
        ...$cambios,
    ];
}

it('sirve la ficha de la organización del contexto', function (): void {
    $this->organizacion->forceFill(['razon_social' => 'Ejemplo, S.A.'])->save();

    $this->actingAs($this->usuario)
        ->get('/organizacion')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('organizacion/Editar')
            ->where('organizacion.razon_social', 'Ejemplo, S.A.')
            ->where('organizacion.id', $this->organizacion->id)
        );
});

it('guarda la ficha entera', function (): void {
    $this->actingAs($this->usuario)
        ->put('/organizacion', fichaCompleta())
        ->assertRedirect('/organizacion');

    $organizacion = $this->organizacion->fresh();

    expect($organizacion->razon_social)->toBe('Talleres Merino y Asociados, S.L.')
        ->and($organizacion->nombre)->toBe('Talleres Merino')
        ->and($organizacion->municipio)->toBe('Guadalajara')
        ->and($organizacion->codigo_postal)->toBe('19001')
        ->and($organizacion->proveedor_sector_publico)->toBeTrue();
});

it('normaliza el CIF a mayúsculas y sin separadores', function (): void {
    // Sin esto, «b-1234» y «B1234» son dos CIF distintos frente al índice único.
    $this->actingAs($this->usuario)
        ->put('/organizacion', fichaCompleta(['cif' => ' b-1234 5678 ']))
        ->assertRedirect();

    expect($this->organizacion->fresh()->cif)->toBe('B12345678');
});

it('no deja repetir el CIF de otra organización', function (): void {
    Organizacion::factory()->create(['cif' => 'A99999999']);

    $this->actingAs($this->usuario)
        ->put('/organizacion', fichaCompleta(['cif' => 'A-99999999']))
        ->assertSessionHasErrors('cif');
});

it('deja guardar el CIF que ya tiene puesto', function (): void {
    // `Rule::unique()->ignore()`: sin eso, guardar la ficha sin tocar el CIF
    // chocaría consigo misma.
    $this->organizacion->forceFill(['cif' => 'B12345678'])->save();

    $this->actingAs($this->usuario)
        ->put('/organizacion', fichaCompleta(['cif' => 'B12345678']))
        ->assertSessionHasNoErrors();
});

/*
|--------------------------------------------------------------------------
| La cadena vacía de `url_base_etiquetas`
|--------------------------------------------------------------------------
|
| `GeneradorEtiquetas` resolvía con `??`, no con `?:`. Mientras la columna sólo
| la escribían los seeders nunca era cadena vacía; con un formulario delante,
| vaciar el campo manda `""`, que atraviesa el `??` y genera QR contra
| `/activos/3` — sin host, en una pegatina que alguien imprime.
|
*/

it('guarda como nula la base de etiquetas que se deja vacía', function (): void {
    $this->organizacion->forceFill(['url_base_etiquetas' => 'https://viejo.example'])->save();

    $this->actingAs($this->usuario)
        ->put('/organizacion', fichaCompleta(['url_base_etiquetas' => '']))
        ->assertRedirect();

    expect($this->organizacion->fresh()->url_base_etiquetas)->toBeNull();
});

it('el QR sigue llevando host aunque la base quede vacía', function (): void {
    $activo = Activo::factory()->create();

    // La columna a cadena vacía, que es lo que podría dejar un importador o una
    // escritura directa aunque el formulario ya la normalice.
    $this->organizacion->forceFill(['url_base_etiquetas' => ''])->save();

    $contenido = app(GeneradorEtiquetas::class)->contenido($activo, $this->organizacion->fresh());

    expect($contenido)->toBe(rtrim((string) config('app.url'), '/')."/activos/{$activo->id}")
        ->and($contenido)->not->toStartWith('/activos');
});

/*
|--------------------------------------------------------------------------
| El permiso
|--------------------------------------------------------------------------
*/

it('el técnico y el auditor no llegan a la ficha', function (): void {
    foreach ([Rol::Tecnico, Rol::Auditor] as $rol) {
        $usuario = usuarioCon($rol);

        $this->actingAs($usuario)->get('/organizacion')->assertForbidden();
        $this->actingAs($usuario)->put('/organizacion', fichaCompleta())->assertForbidden();
    }

    // Y nada se ha escrito por el camino.
    expect($this->organizacion->fresh()->razon_social)->toBeNull();
});

it('exige sesión iniciada', function (): void {
    $this->get('/organizacion')->assertRedirect('/login');
});

/*
|--------------------------------------------------------------------------
| La traza, que es lo que sale en silencio
|--------------------------------------------------------------------------
|
| `RegistroTraza::escribir()` lee `getAttribute('organizacion_id')` y hace
| `return` si llega nulo. `organizaciones` no tiene esa columna, así que sin el
| accesor `organizacionId` el trait no escribiría nada y nadie fallaría. Este
| test es lo único que impide que ese accesor se retire sin enterarse.
|
*/

it('deja traza al cambiar la ficha', function (): void {
    $this->actingAs($this->usuario)
        ->put('/organizacion', fichaCompleta(['cif' => 'B87654321']))
        ->assertRedirect();

    $evento = EventoAuditoria::query()
        ->where('entidad', 'Organizacion')
        ->where('entidad_id', $this->organizacion->id)
        ->latest('id')
        ->first();

    expect($evento)->not->toBeNull('El trait salió en silencio: falta el accesor `organizacionId`.')
        ->and($evento->organizacion_id)->toBe($this->organizacion->id)
        ->and($evento->usuario_id)->toBe($this->usuario->id);
});

/*
|--------------------------------------------------------------------------
| La razón social en el documento
|--------------------------------------------------------------------------
*/

it('el nombre legal es la razón social cuando la hay', function (): void {
    $organizacion = Organizacion::factory()->create([
        'nombre' => 'Merino',
        'razon_social' => 'Talleres Merino y Asociados, S.L.',
    ]);

    expect($organizacion->nombreLegal())->toBe('Talleres Merino y Asociados, S.L.');
});

it('sin razón social el nombre legal sigue siendo el nombre de siempre', function (): void {
    // Es lo que hace que ningún documento cambie de texto por migrar: toda
    // organización que ya existía tiene la razón social a nulo.
    $organizacion = Organizacion::factory()->create(['nombre' => 'Merino', 'razon_social' => null]);

    expect($organizacion->nombreLegal())->toBe('Merino');
});

it('una organización sólo se puede modificar desde su propio contexto', function (): void {
    /*
     * Consecuencia de que la traza cuelgue de `eventos_auditoria`, que SÍ lleva
     * RLS: actualizar una organización que no es la del contexto intenta
     * escribir un evento de otro tenant y la política lo rechaza.
     *
     * En la aplicación no puede pasar —la ruta no tiene parámetro y la
     * organización sale del contexto—, y que reviente es lo correcto: una
     * escritura a mano que cruce la frontera tiene que fallar ruidosamente y no
     * quedarse sin traza. Esto lo clava para que nadie lo «arregle» apagando la
     * traza.
     */
    $ajena = Organizacion::factory()->create(['nombre' => 'De otra casa']);

    expect(fn () => $ajena->forceFill(['razon_social' => 'Otra, S.L.'])->save())
        ->toThrow(QueryException::class);
});
