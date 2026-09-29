<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Documento\AcusarLectura;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Usuario\Enums\PaginaInicio;
use App\Domain\Usuario\Enums\Tema;
use App\Domain\Usuario\SesionesAbiertas;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Mi cuenta y el menú de la cuenta
|--------------------------------------------------------------------------
|
| Lo que llegó con el rediseño del menú: lo pendiente a tu nombre, las sesiones
| abiertas, los últimos accesos, las preferencias que siguen a la cuenta y la
| copia de tus datos. Lo que más importa probar es la frontera: que nada de
| esto enseñe, cuente o cierre algo de otra cuenta.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->cuenta = usuarioCon(Rol::ResponsableSeguridad);
});

/** Una fila de `sessions` como la deja el driver `database`. */
function sesionDe(User $cuenta, string $agente = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/131.0'): string
{
    $id = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $cuenta->id,
        'ip_address' => '10.0.4.21',
        'user_agent' => $agente,
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);

    return $id;
}

/*
|--------------------------------------------------------------------------
| El menú
|--------------------------------------------------------------------------
*/

it('el menú cuenta sólo las tareas abiertas a tu nombre, y aparte las vencidas', function (): void {
    $otro = usuarioCon(Rol::Tecnico);

    Tarea::factory()->de($this->cuenta)->create();
    Tarea::factory()->de($this->cuenta)->vencida()->create();
    Tarea::factory()->de($otro)->vencida()->create();

    $this->actingAs($this->cuenta)
        ->getJson('/perfil/menu')
        ->assertOk()
        ->assertJsonPath('tareas.abiertas', 2)
        ->assertJsonPath('tareas.vencidas', 1)
        ->assertJsonPath('rol', Rol::ResponsableSeguridad->etiqueta());
});

it('el menú cuenta los documentos que te faltan por leer, y deja de contarlos al acusar', function (): void {
    $version = DocumentoVersion::factory()
        ->delDocumento(Documento::factory()->politica()->create()->id)
        ->emitida()
        ->create();

    $this->actingAs($this->cuenta)->getJson('/perfil/menu')->assertJsonPath('porLeer.total', 1);

    app(AcusarLectura::class)($version, $this->cuenta);

    $this->actingAs($this->cuenta)->getJson('/perfil/menu')->assertJsonPath('porLeer.total', 0);
});

it('la cifra del menú es la del filtro de la tabla a la que lleva', function (): void {
    DocumentoVersion::factory()
        ->delDocumento(Documento::factory()->politica()->create()->id)
        ->emitida()
        ->create();

    $url = $this->actingAs($this->cuenta)->getJson('/perfil/menu')->json('porLeer.url');

    $this->actingAs($this->cuenta)
        ->get($url)
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 1));
});

it('a quien no puede aprobar no le cuenta firmas pendientes', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnico)->getJson('/perfil/menu')->assertJsonPath('porFirmar', null);
});

it('al auditor le dice hasta cuándo entra y qué sistemas ve', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $auditor->forceFill(['acceso_hasta' => now()->addDays(31)->toDateString()])->save();

    $this->actingAs($auditor)
        ->getJson('/perfil/menu')
        ->assertJsonPath('alcance.hasta', now()->addDays(31)->toDateString());

    $this->actingAs($this->cuenta)->getJson('/perfil/menu')->assertJsonPath('alcance', null);
});

it('enseña la entrada anterior a ésta y los intentos fallidos desde entonces', function (): void {
    sinOrganizacion();

    // Con minutos entre medias, como en la vida: la traza guarda al segundo.
    $this->post('/login', ['email' => $this->cuenta->email, 'password' => 'password']);
    $this->post('/logout');
    $this->travel(5)->minutes();
    $this->post('/login', ['email' => $this->cuenta->email, 'password' => 'mala']);
    $this->travel(5)->minutes();
    $this->post('/login', ['email' => $this->cuenta->email, 'password' => 'password']);

    $this->getJson('/perfil/menu')
        ->assertOk()
        ->assertJsonPath('entradaAnterior.fallidosDesde', 1);
});

/*
|--------------------------------------------------------------------------
| Sesiones abiertas
|--------------------------------------------------------------------------
*/

it('lista sólo tus sesiones, sin su id, y reconoce el navegador', function (): void {
    $mia = sesionDe($this->cuenta);
    sesionDe(usuarioCon(Rol::Tecnico));

    $this->actingAs($this->cuenta)
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('sesiones', 1)
            ->where('sesiones.0.clave', SesionesAbiertas::clave($mia))
            ->where('sesiones.0.navegador', 'Firefox')
            ->where('sesiones.0.sistema', 'Linux')
        )
        // El id es la cookie: no puede salir en la página.
        ->assertDontSee($mia);
});

it('cerrar una sesión pide la contraseña', function (): void {
    $mia = sesionDe($this->cuenta);

    $this->actingAs($this->cuenta)
        ->delete('/perfil/sesiones/'.SesionesAbiertas::clave($mia), ['password' => 'mala'])
        ->assertSessionHasErrors('password');

    expect(DB::table('sessions')->where('id', $mia)->exists())->toBeTrue();
});

it('cierra una sesión tuya y cambia el «recordarme»', function (): void {
    $mia = sesionDe($this->cuenta);
    $token = $this->cuenta->remember_token;

    $this->actingAs($this->cuenta)
        ->delete('/perfil/sesiones/'.SesionesAbiertas::clave($mia), ['password' => 'password'])
        ->assertRedirect('/perfil');

    expect(DB::table('sessions')->where('id', $mia)->exists())->toBeFalse()
        ->and($this->cuenta->fresh()?->remember_token)->not->toBe($token);
});

it('no cierra la sesión de otra cuenta aunque se conozca su huella', function (): void {
    $ajena = sesionDe(usuarioCon(Rol::Tecnico));

    $this->actingAs($this->cuenta)
        ->delete('/perfil/sesiones/'.SesionesAbiertas::clave($ajena), ['password' => 'password'])
        ->assertRedirect('/perfil');

    expect(DB::table('sessions')->where('id', $ajena)->exists())->toBeTrue();
});

it('cerrar las demás no toca las de otras cuentas', function (): void {
    sesionDe($this->cuenta);
    sesionDe($this->cuenta, 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0');
    $ajena = sesionDe(User::factory()->create(['organizacion_id' => Organizacion::factory()->create()->id]));

    $this->actingAs($this->cuenta)
        ->delete('/perfil/sesiones', ['password' => 'password'])
        ->assertRedirect('/perfil');

    expect(DB::table('sessions')->where('user_id', $this->cuenta->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', $ajena)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Accesos, contraseña y ficha
|--------------------------------------------------------------------------
*/

it('los últimos accesos salen de la traza, con los fallidos', function (): void {
    sinOrganizacion();

    $this->post('/login', ['email' => $this->cuenta->email, 'password' => 'mala']);
    $this->travel(1)->minutes();
    $this->post('/login', ['email' => $this->cuenta->email, 'password' => 'password']);

    $this->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('accesos', 2)
            ->where('accesos.0.correcto', true)
            ->where('accesos.1.correcto', false)
        );
});

it('cambiar la contraseña deja la fecha del cambio', function (): void {
    expect($this->cuenta->password_cambiada_en)->toBeNull();

    $this->actingAs($this->cuenta)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'una-contraseña-Nueva-2026',
        'password_confirmation' => 'una-contraseña-Nueva-2026',
    ]);

    expect($this->cuenta->fresh()?->password_cambiada_en)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Preferencias
|--------------------------------------------------------------------------
*/

it('guarda las preferencias en la cuenta', function (): void {
    $this->actingAs($this->cuenta)
        ->put('/perfil/preferencias', ['pagina_inicio' => 'tareas', 'avisos_por_correo' => false])
        ->assertRedirect('/perfil');

    $cuenta = $this->cuenta->fresh();

    expect($cuenta?->pagina_inicio)->toBe(PaginaInicio::Tareas)
        ->and($cuenta?->avisos_por_correo)->toBeFalse()
        // Lo que no llega no se toca.
        ->and($cuenta?->tema)->toBe(Tema::Sistema);
});

it('rechaza un tema que no existe', function (): void {
    $this->actingAs($this->cuenta)
        ->put('/perfil/preferencias', ['tema' => 'morado'])
        ->assertSessionHasErrors('tema');
});

it('el tema del menú se guarda sin navegar y viaja con cada página', function (): void {
    $this->actingAs($this->cuenta)->putJson('/perfil/tema', ['tema' => 'oscuro'])->assertNoContent();

    $this->actingAs($this->cuenta->fresh())
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('auth.usuario.tema', 'oscuro'));
});

it('al entrar lleva a la página de inicio elegida', function (PaginaInicio $pagina, string $destino): void {
    $this->cuenta->forceFill(['pagina_inicio' => $pagina])->save();

    $respuesta = $this->actingAs($this->cuenta->fresh())->get('/inicio');

    expect($respuesta->headers->get('Location'))->toContain($destino);
})->with([
    'el panel' => [PaginaInicio::Panel, '/panel'],
    'mis tareas' => [PaginaInicio::Tareas, '/tareas?'],
    'el calendario' => [PaginaInicio::Calendario, '/calendario'],
]);

it('a quien no le llega el resumen no se le ofrece apagarlo', function (): void {
    $this->actingAs(usuarioCon(Rol::Tecnico))
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('avisos', null));

    $this->actingAs($this->cuenta)
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('avisos.activos', true));
});

/*
|--------------------------------------------------------------------------
| La copia de tus datos
|--------------------------------------------------------------------------
*/

it('descarga tus datos sin secretos', function (): void {
    $respuesta = $this->actingAs($this->cuenta)->get('/perfil/mis-datos');

    $respuesta->assertOk()
        ->assertHeader('Content-Disposition')
        ->assertJsonPath('cuenta.correo', $this->cuenta->email);

    expect($respuesta->getContent())
        ->not->toContain('"password"')
        ->not->toContain('two_factor')
        ->not->toContain('remember_token');
});
