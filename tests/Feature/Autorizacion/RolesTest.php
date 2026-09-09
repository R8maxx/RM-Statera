<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Roles y permisos
|--------------------------------------------------------------------------
|
| El RBAC autoriza; el aislamiento sigue siendo de la organización. Un permiso
| no atraviesa jamás la frontera del tenant, y eso es lo que más se comprueba
| aquí: que ser responsable de seguridad de la organización A no abre nada de la
| B.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->marco = Marco::factory()->create();
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();
    $this->requisito = Requisito::factory()->conCodigo('op.acc.5')->create(['marco_id' => $this->marco->id]);
    $this->implantacion = Implantacion::factory()->create([
        'organizacion_id' => $this->organizacion->id,
        'sistema_id' => $this->sistema->id,
        'requisito_id' => $this->requisito->id,
    ]);
});

it('el auditor lee todo el cumplimiento', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    foreach (['/panel', '/sistemas', '/implantaciones', '/evidencias'] as $ruta) {
        $this->actingAs($auditor)->get($ruta)->assertOk();
    }

    $this->actingAs($auditor)->get("/implantaciones/{$this->implantacion->id}")->assertOk();
});

it('el auditor no puede alterar lo que audita', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)
        ->post('/sistemas', ['codigo' => 'X', 'nombre' => 'X', 'marco_id' => $this->marco->id, 'estado' => 'activo'])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post("/implantaciones/{$this->implantacion->id}/estado", ['estado' => EstadoImplantacion::Implantado->value])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post('/evidencias', ['titulo' => 'X'])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->get("/sistemas/{$this->sistema->id}/valoracion")
        ->assertForbidden();

    expect($this->implantacion->fresh()->estado)->toBe(EstadoImplantacion::NoIniciado)
        ->and(Sistema::query()->count())->toBe(1);
});

it('la tabla no le ofrece al auditor ninguna acción de escritura', function (): void {
    $auditor = usuarioCon(Rol::Auditor);

    $this->actingAs($auditor)
        ->get('/sistemas')
        ->assertInertia(function (AssertableInertia $pagina): void {
            $acciones = collect(data_get($pagina->toArray(), 'props.recurso.accionesFila'))->pluck('clave');
            $generales = collect(data_get($pagina->toArray(), 'props.recurso.accionesGenerales'))->pluck('clave');

            // Filtrar la interfaz es cosmética —lo que manda es el `can:` de la
            // ruta—, pero enseñar un botón que va a dar 403 es mentirle a quien
            // lo pulsa.
            expect($acciones)->not->toContain('editar', 'eliminar', 'valoracion')
                ->and($generales)->not->toContain('crear');
        });
});

it('el técnico implanta y prueba, pero no redefine el alcance', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);

    // Lo suyo: mover estados y registrar evidencias.
    $this->actingAs($tecnico)
        ->post("/implantaciones/{$this->implantacion->id}/estado", ['estado' => EstadoImplantacion::EnProgreso->value])
        ->assertSessionHasNoErrors();

    expect($this->implantacion->fresh()->estado)->toBe(EstadoImplantacion::EnProgreso);

    // Lo que no: valorar dimensiones y dar de alta sistemas. Eso cambia lo que
    // se le exige a la organización entera, y quien lo hace responde de ello.
    $this->actingAs($tecnico)->get("/sistemas/{$this->sistema->id}/valoracion")->assertForbidden();
    $this->actingAs($tecnico)->get('/sistemas/crear')->assertForbidden();
    $this->actingAs($tecnico)->delete("/sistemas/{$this->sistema->id}")->assertForbidden();
});

it('el responsable de seguridad lo puede todo dentro de su organización', function (): void {
    $responsable = usuarioCon(Rol::ResponsableSeguridad);

    foreach (Permiso::cases() as $permiso) {
        expect($responsable->can($permiso->value))->toBeTrue($permiso->value);
    }
});

it('un rol no atraviesa la frontera de la organización', function (): void {
    $otra = Organizacion::factory()->create();
    $responsableDeOtra = usuarioCon(Rol::ResponsableSeguridad, $otra);

    comoOrganizacion($this->organizacion);

    // Tiene todos los permisos —en su casa—, y aun así no ve nada de ésta.
    $this->actingAs($responsableDeOtra)
        ->get("/sistemas/{$this->sistema->id}/editar")
        ->assertNotFound();

    $this->actingAs($responsableDeOtra)
        ->get("/implantaciones/{$this->implantacion->id}")
        ->assertNotFound();

    $this->actingAs($responsableDeOtra)
        ->get('/sistemas')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('filas', 0));
});

it('el mismo usuario no hereda los permisos de la organización anterior', function (): void {
    $otra = Organizacion::factory()->create();

    // Auditor aquí, y nada en la otra: el registrar tiene que apuntar a la
    // organización activa, no a la última que se tocó.
    $auditor = usuarioCon(Rol::Auditor);

    comoOrganizacion($otra);

    expect($auditor->hasRole(Rol::Auditor->value))->toBeFalse()
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($otra->id);

    comoOrganizacion($this->organizacion);

    expect($auditor->fresh()?->hasRole(Rol::Auditor->value))->toBeTrue();
});

it('sembrar es idempotente y retira lo que el rol deja de tener', function (): void {
    $sembrar = app(SembrarRoles::class);

    $sembrar->paraOrganizacion($this->organizacion);
    $sembrar->paraOrganizacion($this->organizacion);

    $auditor = usuarioCon(Rol::Auditor);

    expect($auditor->can(Permiso::EvidenciasVer->value))->toBeTrue()
        ->and($auditor->can(Permiso::EvidenciasGestionar->value))->toBeFalse();
});

it('cada evidencia sigue siendo de su organización aunque el rol lo permita', function (): void {
    $otra = Organizacion::factory()->create();
    comoOrganizacion($otra);
    $ajena = Evidencia::factory()->create(['organizacion_id' => $otra->id]);
    comoOrganizacion($this->organizacion);

    $responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->actingAs($responsable)->get("/evidencias/{$ajena->id}")->assertNotFound();
});
