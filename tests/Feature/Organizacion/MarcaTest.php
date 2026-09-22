<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Marca\MarcaDeLaOrganizacion;
use App\Domain\Organizacion\Marca\PiezaDeMarca;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| La marca de la organización cliente
|--------------------------------------------------------------------------
|
| Dos piezas —logo horizontal y símbolo— que salen en la portada del PDF, en la
| cabecera de cada página y en el desplegable de organización. Es **co-branding
| y no marca blanca**: Statera sigue arriba y lo sigue diciendo.
|
| Lo que este fichero clava, que es donde se rompe:
|
| 1. **El SVG se sanea de verdad.** Es el único formato del producto que llega
|    como documento XML y no como mapa de bits.
| 2. **Un mapa de bits sale PNG** y no se agranda. PNG y no WebP: un logo es
|    gráfico plano y va a imprenta.
| 3. **Nada se recorta.** Un símbolo apaisado sale apaisado — el logo del
|    cliente no es nuestro para retocarlo (DESIGN.md §2).
| 4. **Sin logo, todo sale como salía.** Ésa es la garantía que permite meter
|    esto sin tocar ningún documento existente.
|
*/

beforeEach(function (): void {
    Storage::fake('adjuntos');

    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon(Rol::ResponsableSeguridad);
});

function svgCon(string $dentro, string $atributos = ''): UploadedFile
{
    $xml = <<<SVG
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 40" {$atributos}>
        <rect width="100" height="40" fill="#007E81"/>
        {$dentro}
    </svg>
    SVG;

    return UploadedFile::fake()->createWithContent('logo.svg', $xml);
}

function guardada(string $ruta): string
{
    return Storage::disk('adjuntos')->get($ruta);
}

/*
|--------------------------------------------------------------------------
| El SVG
|--------------------------------------------------------------------------
*/

it('guarda el SVG como SVG, sin rasterizarlo', function (): void {
    $this->actingAs($this->usuario)
        ->post('/organizacion/marca/logo', ['pieza' => svgCon('')])
        ->assertRedirect('/organizacion');

    $ruta = $this->organizacion->fresh()->logo_ruta;

    expect($ruta)->toEndWith('.svg')
        ->and($ruta)->toStartWith("marca/{$this->organizacion->id}/logo-");

    // Se conserva el vector: es lo único que un SVG aporta en imprenta.
    expect(guardada($ruta))->toContain('<svg')->toContain('viewBox');
});

it('le quita el script al SVG', function (): void {
    $this->actingAs($this->usuario)
        ->post('/organizacion/marca/logo', [
            'pieza' => svgCon('<script>alert(1)</script>'),
        ])
        ->assertRedirect();

    $contenido = guardada($this->organizacion->fresh()->logo_ruta);

    expect($contenido)->not->toContain('<script')
        ->and($contenido)->not->toContain('alert(1)')
        // Y no se lleva por delante el dibujo.
        ->and($contenido)->toContain('<rect');
});

it('le quita los manejadores de eventos', function (): void {
    $this->actingAs($this->usuario)
        ->post('/organizacion/marca/logo', [
            'pieza' => svgCon('<circle cx="10" cy="10" r="5" onload="alert(1)"/>'),
        ])
        ->assertRedirect();

    $contenido = guardada($this->organizacion->fresh()->logo_ruta);

    expect($contenido)->not->toContain('onload')
        ->and($contenido)->not->toContain('alert(1)');
});

it('le quita las referencias remotas', function (): void {
    // Una remota tumbaría la generación del PDF entera:
    // `failOnResourceLoadingFailed()` está encendido y la allow-list de
    // Gotenberg deniega todo lo que no sea `file:///tmp/` o `data:`.
    $this->actingAs($this->usuario)
        ->post('/organizacion/marca/logo', [
            'pieza' => svgCon('<image href="https://ajeno.example/pixel.png" width="10" height="10"/>'),
        ])
        ->assertRedirect();

    expect(guardada($this->organizacion->fresh()->logo_ruta))->not->toContain('ajeno.example');
});

it('rechaza lo que no es ni imagen ni SVG', function (): void {
    $this->actingAs($this->usuario)
        ->post('/organizacion/marca/logo', [
            'pieza' => UploadedFile::fake()->create('contrato.pdf', 12, 'application/pdf'),
        ])
        ->assertSessionHasErrors('pieza');

    expect($this->organizacion->fresh()->logo_ruta)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| El mapa de bits
|--------------------------------------------------------------------------
*/

it('convierte un mapa de bits a PNG y lo reduce por el lado mayor', function (): void {
    $this->actingAs($this->usuario)
        ->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('logo.jpg', 3000, 800)])
        ->assertRedirect();

    $ruta = $this->organizacion->fresh()->logo_ruta;
    $info = getimagesizefromstring(guardada($ruta));

    expect($ruta)->toEndWith('.png')
        ->and($info['mime'])->toBe('image/png')
        // 3000×800 escalado por el lado mayor a 1024.
        ->and($info[0])->toBe(1024)
        ->and($info[1])->toBe(273);
});

it('no agranda un logo más pequeño que el destino', function (): void {
    $this->actingAs($this->usuario)
        ->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('logo.png', 200, 60)])
        ->assertRedirect();

    $info = getimagesizefromstring(guardada($this->organizacion->fresh()->logo_ruta));

    expect([$info[0], $info[1]])->toBe([200, 60]);
});

it('no recorta el símbolo aunque llegue apaisado', function (): void {
    // DESIGN.md §2: el logo del cliente no se recorta ni se deforma. Si su
    // símbolo es apaisado, sale apaisado.
    $this->actingAs($this->usuario)
        ->post('/organizacion/marca/simbolo', ['pieza' => UploadedFile::fake()->image('s.png', 800, 200)])
        ->assertRedirect();

    $info = getimagesizefromstring(guardada($this->organizacion->fresh()->simbolo_ruta));

    expect([$info[0], $info[1]])->toBe([512, 128]);
});

/*
|--------------------------------------------------------------------------
| Las dos piezas son independientes
|--------------------------------------------------------------------------
*/

it('el logo y el símbolo no se pisan', function (): void {
    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('l.png', 400, 100)]);
    $this->actingAs($this->usuario)->post('/organizacion/marca/simbolo', ['pieza' => UploadedFile::fake()->image('s.png', 100, 100)]);

    $organizacion = $this->organizacion->fresh();

    expect($organizacion->logo_ruta)->toContain('/logo-')
        ->and($organizacion->simbolo_ruta)->toContain('/simbolo-');

    Storage::disk('adjuntos')->assertExists($organizacion->logo_ruta);
    Storage::disk('adjuntos')->assertExists($organizacion->simbolo_ruta);
});

it('cambiar una pieza borra el objeto anterior', function (): void {
    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('a.png', 400, 100)]);
    $primera = $this->organizacion->fresh()->logo_ruta;

    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('b.png', 400, 100)]);
    $segunda = $this->organizacion->fresh()->logo_ruta;

    expect($segunda)->not->toBe($primera);
    Storage::disk('adjuntos')->assertMissing($primera);
    Storage::disk('adjuntos')->assertExists($segunda);
});

it('borrar se lleva la columna y el objeto', function (): void {
    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('l.png', 400, 100)]);
    $ruta = $this->organizacion->fresh()->logo_ruta;

    $this->actingAs($this->usuario)->delete('/organizacion/marca/logo')->assertRedirect('/organizacion');

    expect($this->organizacion->fresh()->logo_ruta)->toBeNull();
    Storage::disk('adjuntos')->assertMissing($ruta);
});

/*
|--------------------------------------------------------------------------
| Servir, y quién puede qué
|--------------------------------------------------------------------------
*/

it('sirve la pieza como redirect firmado y no como enlace al bucket', function (): void {
    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('l.png', 400, 100)]);

    $respuesta = $this->actingAs($this->usuario)->get('/organizacion/marca/logo');

    $respuesta->assertRedirect();
    expect($respuesta->headers->get('Location'))->toContain($this->organizacion->fresh()->logo_ruta);
});

it('sin pieza subida no hay nada que servir', function (): void {
    $this->actingAs($this->usuario)->get('/organizacion/marca/logo')->assertNotFound();
});

it('una pieza inventada responde 404 sin llegar al controlador', function (): void {
    $this->actingAs($this->usuario)->get('/organizacion/marca/favicon')->assertNotFound();
});

it('cualquiera con sesión ve el logo, y sólo el responsable lo cambia', function (): void {
    // Verlo no lleva permiso de gestión: lo pinta el sidebar de los tres roles.
    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('l.png', 400, 100)]);

    $tecnico = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnico)->get('/organizacion/marca/logo')->assertRedirect();

    $this->actingAs($tecnico)
        ->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('otro.png', 400, 100)])
        ->assertForbidden();

    $this->actingAs($tecnico)->delete('/organizacion/marca/logo')->assertForbidden();
});

it('exige sesión iniciada', function (): void {
    $this->get('/organizacion/marca/logo')->assertRedirect('/login');
});

/*
|--------------------------------------------------------------------------
| Lo que llega al documento
|--------------------------------------------------------------------------
*/

it('da el data URI de la pieza, con su tipo', function (): void {
    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('l.png', 400, 100)]);
    $this->actingAs($this->usuario)->post('/organizacion/marca/simbolo', ['pieza' => svgCon('')]);

    $marca = app(MarcaDeLaOrganizacion::class);
    $organizacion = $this->organizacion->fresh();

    expect($marca->dataUri($organizacion, PiezaDeMarca::Logo))->toStartWith('data:image/png;base64,')
        ->and($marca->dataUri($organizacion, PiezaDeMarca::Simbolo))->toStartWith('data:image/svg+xml;base64,');
});

it('sin pieza subida el data URI es nulo, y el documento sale como salía', function (): void {
    $marca = app(MarcaDeLaOrganizacion::class);

    expect($marca->dataUri($this->organizacion, PiezaDeMarca::Logo))->toBeNull()
        ->and($marca->dataUri($this->organizacion, PiezaDeMarca::Simbolo))->toBeNull();
});

it('un objeto que no está no tumba la generación', function (): void {
    // Un logo es decoración; un documento que no se genera es un problema de
    // verdad. Si el almacén no responde, la pieza se omite y ya está.
    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('l.png', 400, 100)]);

    Storage::disk('adjuntos')->delete($this->organizacion->fresh()->logo_ruta);

    expect(app(MarcaDeLaOrganizacion::class)->dataUri($this->organizacion->fresh(), PiezaDeMarca::Logo))
        ->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Lo que llega al chrome
|--------------------------------------------------------------------------
*/

/**
 * Los props compartidos de una petición, con el usuario RECARGADO.
 *
 * El `fresh()` no es adorno: `actingAs()` autentica la instancia que se le pasa,
 * y `HandleInertiaRequests` lee `$usuario->organizacion`, que se cachea en esa
 * instancia la primera vez. Reutilizándola entre peticiones, la segunda ve la
 * organización de antes de subir el logo y el prop llega nulo — un falso
 * negativo que en la aplicación no ocurre, porque cada petición carga su usuario.
 *
 * @return array<string, mixed>
 */
function propsDelPanel(TestCase $test, User $usuario): array
{
    return $test->actingAs($usuario->fresh())->get('/panel')->viewData('page')['props'];
}

it('comparte el logo con todas las pantallas, para el desplegable del sidebar', function (): void {
    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('l.png', 400, 100)]);

    expect(propsDelPanel($this, $this->usuario)['organizacion']['logo'])
        ->toStartWith('/organizacion/marca/logo?v=');
});

it('sin logo el prop compartido es nulo y el sidebar sale como salía', function (): void {
    expect(propsDelPanel($this, $this->usuario)['organizacion']['logo'])->toBeNull();
});

it('la URL compartida cambia al cambiar el logo', function (): void {
    // Sin el sufijo de versión el navegador sirve el logo viejo de su caché y
    // cambiarlo no se ve. Mismo fallo que ya tuvo la foto de perfil.
    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('a.png', 400, 100)]);
    $primera = propsDelPanel($this, $this->usuario)['organizacion']['logo'];

    $this->actingAs($this->usuario)->post('/organizacion/marca/logo', ['pieza' => UploadedFile::fake()->image('b.png', 400, 100)]);
    $segunda = propsDelPanel($this, $this->usuario)['organizacion']['logo'];

    expect($segunda)->not->toBe($primera);
});
