<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| La foto de perfil
|--------------------------------------------------------------------------
|
| Es un atributo de la CUENTA, no de la persona: `users` es quien entra y
| `personas` es la plantilla. Va en el disco `adjuntos` —el único de los tres
| sin Object Lock— porque la cara de alguien es un dato personal y tiene que
| poder borrarse de verdad.
|
| Tres cosas que este fichero clava y que son donde se rompe:
|
| 1. **Todo lo que entra sale WebP de 256×256.** Sin eso, alguien sube doce
|    megas y el chrome los descarga en cada pantalla.
| 2. **Cambiar la foto borra el objeto anterior.** Sin eso el bucket acumula
|    caras de gente que ya las cambió, que es justo lo que no puede pasar con
|    un dato personal.
| 3. **La ruta no lleva parámetro de usuario.** `users` no tiene scope global
|    ni RLS: si se pudiera pedir la de otro habría que acotarlo a mano y
|    ninguna capa avisaría de que se olvidó.
|
*/

beforeEach(function (): void {
    Storage::fake('adjuntos');

    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon(Rol::Tecnico);
});

/** Un PNG de verdad, para que GD tenga algo que decodificar. */
function imagenFalsa(int $ancho = 600, int $alto = 400, string $nombre = 'retrato.png'): UploadedFile
{
    return UploadedFile::fake()->image($nombre, $ancho, $alto);
}

/** Las dimensiones y el tipo del objeto que quedó en el almacén. */
function medirGuardada(string $ruta): array
{
    $bytes = Storage::disk('adjuntos')->get($ruta);
    $info = getimagesizefromstring($bytes);

    return ['ancho' => $info[0], 'alto' => $info[1], 'mime' => $info['mime']];
}

it('sube la foto y la normaliza a un cuadrado de 256 en WebP', function (): void {
    $this->actingAs($this->usuario)
        ->post('/perfil/foto', ['foto' => imagenFalsa(600, 400)])
        ->assertRedirect('/perfil');

    $usuario = $this->usuario->fresh();

    expect($usuario->foto_ruta)->not->toBeNull()
        ->and($usuario->foto_ruta)->toEndWith('.webp')
        // Con la organización delante, para que un listado del bucket sea
        // legible y una política de S3 pueda acotarse por prefijo.
        ->and($usuario->foto_ruta)->toStartWith("avatares/{$this->organizacion->id}/");

    Storage::disk('adjuntos')->assertExists($usuario->foto_ruta);

    expect(medirGuardada($usuario->foto_ruta))
        ->toBe(['ancho' => 256, 'alto' => 256, 'mime' => 'image/webp']);
});

it('recorta al cuadrado en lugar de deformar', function (): void {
    // Una imagen muy apaisada: si se escalara sin recortar saldría 256×171.
    $this->actingAs($this->usuario)
        ->post('/perfil/foto', ['foto' => imagenFalsa(1200, 300)])
        ->assertRedirect();

    expect(medirGuardada($this->usuario->fresh()->foto_ruta))
        ->toBe(['ancho' => 256, 'alto' => 256, 'mime' => 'image/webp']);
});

it('no agranda una imagen más pequeña que el destino', function (): void {
    $this->actingAs($this->usuario)
        ->post('/perfil/foto', ['foto' => imagenFalsa(64, 64)])
        ->assertRedirect();

    // Estirar 64 px a 256 no añade información: añade peso y borrosidad.
    expect(medirGuardada($this->usuario->fresh()->foto_ruta))
        ->toBe(['ancho' => 64, 'alto' => 64, 'mime' => 'image/webp']);
});

it('cambiar la foto borra el objeto anterior del almacén', function (): void {
    $this->actingAs($this->usuario)->post('/perfil/foto', ['foto' => imagenFalsa()]);
    $primera = $this->usuario->fresh()->foto_ruta;

    $this->actingAs($this->usuario)->post('/perfil/foto', ['foto' => imagenFalsa(500, 500, 'otra.png')]);
    $segunda = $this->usuario->fresh()->foto_ruta;

    expect($segunda)->not->toBe($primera);

    Storage::disk('adjuntos')->assertMissing($primera);
    Storage::disk('adjuntos')->assertExists($segunda);
});

it('borrar la foto se lleva la columna y el objeto', function (): void {
    $this->actingAs($this->usuario)->post('/perfil/foto', ['foto' => imagenFalsa()]);
    $ruta = $this->usuario->fresh()->foto_ruta;

    $this->actingAs($this->usuario)
        ->delete('/perfil/foto')
        ->assertRedirect('/perfil');

    expect($this->usuario->fresh()->foto_ruta)->toBeNull();

    Storage::disk('adjuntos')->assertMissing($ruta);
});

it('rechaza lo que no es una de las tres imágenes admitidas', function (): void {
    // Aquí SÍ hay lista blanca, al revés que en los adjuntos: aquello es «lo
    // que haya que adjuntar» y esto es una imagen que vamos a decodificar.
    $this->actingAs($this->usuario)
        ->post('/perfil/foto', ['foto' => UploadedFile::fake()->create('nomina.pdf', 12, 'application/pdf')])
        ->assertSessionHasErrors('foto');

    expect($this->usuario->fresh()->foto_ruta)->toBeNull();
});

it('sirve la foto como un redirect y no como un enlace al bucket', function (): void {
    $this->actingAs($this->usuario)->post('/perfil/foto', ['foto' => imagenFalsa()]);

    $respuesta = $this->actingAs($this->usuario)->get('/perfil/foto');

    $respuesta->assertRedirect();

    expect($respuesta->headers->get('Location'))->toContain($this->usuario->fresh()->foto_ruta);
});

it('sin foto no hay nada que servir', function (): void {
    $this->actingAs($this->usuario)->get('/perfil/foto')->assertNotFound();
});

it('exige sesión iniciada en las tres rutas', function (): void {
    $this->get('/perfil/foto')->assertRedirect('/login');
    $this->post('/perfil/foto', ['foto' => imagenFalsa()])->assertRedirect('/login');
    $this->delete('/perfil/foto')->assertRedirect('/login');
});

it('la URL que viaja al cliente cambia al cambiar la foto', function (): void {
    // Sin sufijo de versión el navegador cachea la foto vieja y cambiarla no
    // se ve. Es el fallo que aparece a los dos días y en otra pantalla.
    $this->actingAs($this->usuario)->post('/perfil/foto', ['foto' => imagenFalsa()]);
    $primera = $this->actingAs($this->usuario)->get('/perfil')->viewData('page')['props']['auth']['usuario']['foto'];

    $this->actingAs($this->usuario)->post('/perfil/foto', ['foto' => imagenFalsa(500, 500, 'otra.png')]);
    $segunda = $this->actingAs($this->usuario)->get('/perfil')->viewData('page')['props']['auth']['usuario']['foto'];

    expect($primera)->toStartWith('/perfil/foto?v=')
        ->and($segunda)->toStartWith('/perfil/foto?v=')
        ->and($segunda)->not->toBe($primera);
});

it('sin foto el prop compartido llega a nulo y el chrome cae a las iniciales', function (): void {
    $props = $this->actingAs($this->usuario)->get('/perfil')->viewData('page')['props'];

    expect($props['auth']['usuario']['foto'])->toBeNull();
});

it('la foto de una cuenta no es la de otra', function (): void {
    $otro = User::factory()->create(['organizacion_id' => $this->organizacion->id]);

    $this->actingAs($this->usuario)->post('/perfil/foto', ['foto' => imagenFalsa()]);

    // La ruta no admite parámetro: cada quien sirve la suya y no hay forma de
    // pedir la de nadie más.
    $this->actingAs($otro)->get('/perfil/foto')->assertNotFound();

    expect($otro->fresh()->foto_ruta)->toBeNull();
});
