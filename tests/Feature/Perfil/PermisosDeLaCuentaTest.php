<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Qué puedo hacer yo
|--------------------------------------------------------------------------
|
| Un bloque de SÓLO LECTURA en «Mi cuenta». Contesta «¿por qué no me sale este
| botón?», que es la pregunta real de quien mira sus permisos, y por eso pinta
| también lo que NO se tiene: una lista de sólo lo concedido no la contesta.
|
| Gestionar los permisos de otros es el § 4.19 y no está aquí: esto no abre
| ninguna ruta de escritura.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
});

it('declara el rol con la descripción que ya escribe el enum', function (): void {
    $usuario = usuarioCon(Rol::Tecnico);

    $this->actingAs($usuario)
        ->get('/perfil')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('permisos.roles', 1)
            ->where('permisos.roles.0.etiqueta', Rol::Tecnico->etiqueta())
            ->where('permisos.roles.0.descripcion', Rol::Tecnico->descripcion())
        );
});

it('agrupa por módulo y dice de cada verbo si se tiene', function (): void {
    $usuario = usuarioCon(Rol::Tecnico);

    $props = $this->actingAs($usuario)->get('/perfil')->viewData('page')['props'];

    $activos = collect($props['permisos']['modulos'])->firstWhere('clave', 'activos');

    expect($activos)->not->toBeNull()
        // El href es lo que el cliente resuelve contra `lib/navegacion.ts`
        // para sacar el título y el icono: un mapa y no una segunda lista.
        ->and($activos['href'])->toBe('/activos');

    $verbos = collect($activos['verbos'])->pluck('tiene', 'clave');

    expect($verbos['activos.ver'])->toBeTrue()
        ->and($verbos['activos.gestionar'])->toBeTrue();
});

it('pinta lo que NO se tiene dentro de un módulo que sí se ve', function (): void {
    // El técnico ve las no conformidades y las gestiona, y no las verifica:
    // comprobar que una acción correctiva funcionó no puede hacerlo quien la
    // ejecutó. Ése es justo el caso que hay que poder leer en la pantalla.
    $usuario = usuarioCon(Rol::Tecnico);

    $props = $this->actingAs($usuario)->get('/perfil')->viewData('page')['props'];

    $modulo = collect($props['permisos']['modulos'])->firstWhere('clave', 'no_conformidades');
    $verbos = collect($modulo['verbos'])->pluck('tiene', 'clave');

    expect($verbos['no_conformidades.gestionar'])->toBeTrue()
        ->and($verbos['no_conformidades.verificar'])->toBeFalse();
});

it('reparte los módulos entre los que se ven y los que no, derivándolo del enum', function (): void {
    $usuario = usuarioCon(Rol::Tecnico);

    $props = $this->actingAs($usuario)->get('/perfil')->viewData('page')['props'];

    $visibles = collect($props['permisos']['modulos'])->pluck('clave');
    $ocultos = collect($props['permisos']['sinAcceso'])->pluck('clave');

    // El reparto se deriva del enum y no se fija a mano: así el día que un rol
    // gane o pierda un módulo el test sigue probando la regla y no un ejemplo.
    $concedidos = collect(Rol::Tecnico->permisos())->map(fn (Permiso $p): string => $p->value);
    $porPrefijo = collect(Permiso::cases())->groupBy(fn (Permiso $p): string => Str::before($p->value, '.'));

    [$esperadosVisibles, $esperadosOcultos] = $porPrefijo
        ->partition(fn ($verbos): bool => $verbos->contains(
            fn (Permiso $p): bool => $concedidos->contains($p->value)
        ));

    expect($visibles->sort()->values()->all())->toBe($esperadosVisibles->keys()->sort()->values()->all())
        ->and($ocultos->sort()->values()->all())->toBe($esperadosOcultos->keys()->sort()->values()->all())
        // Y ninguno aparece en las dos listas.
        ->and($visibles->intersect($ocultos))->toBeEmpty();
});

it('con los tres roles de partida nadie tiene un módulo oculto', function (): void {
    /*
     * Hoy los tres roles del § 4.19 llevan el `.ver` de los diecisiete módulos,
     * así que el resumen «no ves: …» no se pinta nunca. Queda clavado para que
     * el día que eso cambie se sepa por este test y no por una pantalla que
     * empieza a decir algo nuevo sin que nadie lo haya decidido.
     */
    foreach (Rol::cases() as $rol) {
        $usuario = usuarioCon($rol);

        $this->actingAs($usuario)
            ->get('/perfil')
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('permisos.sinAcceso', 0));
    }
});

it('un módulo revocado entero baja al resumen y deja de ocupar una fila', function (): void {
    // La rama de `sinAcceso` que hoy ningún rol de partida activa. Se ejercita
    // quitándole el módulo ENTERO al rol, que es la única forma de llegar a
    // ella y la que llegará el día que haya un rol más estrecho.
    $usuario = usuarioCon(Rol::Tecnico);

    $rol = Role::findByName(Rol::Tecnico->value);
    $rol->revokePermissionTo(Permiso::ActivosVer->value);
    $rol->revokePermissionTo(Permiso::ActivosGestionar->value);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $props = $this->actingAs($usuario->fresh())->get('/perfil')->viewData('page')['props'];

    expect(collect($props['permisos']['modulos'])->pluck('clave'))->not->toContain('activos')
        ->and(collect($props['permisos']['sinAcceso'])->pluck('clave'))->toContain('activos');
});

it('refleja lo que se le quita AL ROL, no al usuario', function (): void {
    // `revokePermissionTo` sobre la persona no quita lo que hereda del rol, y
    // el test pasaría por el motivo equivocado. La lección es de `AlertasTest`.
    $usuario = usuarioCon(Rol::Tecnico);

    $rol = Role::findByName(Rol::Tecnico->value);
    $rol->revokePermissionTo(Permiso::ActivosGestionar->value);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $props = $this->actingAs($usuario->fresh())->get('/perfil')->viewData('page')['props'];

    $modulo = collect($props['permisos']['modulos'])->firstWhere('clave', 'activos');
    $verbos = collect($modulo['verbos'])->pluck('tiene', 'clave');

    expect($verbos['activos.gestionar'])->toBeFalse()
        ->and($verbos['activos.ver'])->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| El que descubre en vez de enumerar
|--------------------------------------------------------------------------
|
| El título y el icono de cada módulo los resuelve el cliente contra
| `lib/navegacion.ts`, que es el mapa único de la aplicación. Si un permiso
| nuevo trae un prefijo que no está allí, `entradaDe()` devuelve `undefined` y
| la fila sale **sin nombre y sin icono, sin que falle nadie** — el mismo fallo
| silencioso que `IconosTest` y `TonosTest` existen para cerrar.
|
| Se parsea el fichero fuente porque el otro lado es TypeScript y no se puede
| leer desde PHP, igual que en `EsquemaEnDosIdiomasTest`.
|
*/

it('todo prefijo de permiso tiene entrada en el mapa de navegación', function (): void {
    $fuente = (string) file_get_contents(resource_path('js/lib/navegacion.ts'));

    preg_match_all("/href:\s*'([^']+)'/", $fuente, $coincidencias);
    $hrefs = $coincidencias[1];

    expect($hrefs)->not->toBeEmpty('No se encontró ningún href en navegacion.ts: el patrón dejó de casar.');

    $sinEntrada = collect(Permiso::cases())
        ->map(fn (Permiso $permiso): string => Str::before($permiso->value, '.'))
        ->unique()
        ->reject(fn (string $prefijo): bool => in_array('/'.str_replace('_', '-', $prefijo), $hrefs, true))
        ->values()
        ->all();

    expect($sinEntrada)->toBe([]);
});
