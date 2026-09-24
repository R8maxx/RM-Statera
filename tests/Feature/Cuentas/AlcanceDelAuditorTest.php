<?php

declare(strict_types=1);

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Concerns\AcotadoPorAlcance;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Documento\Models\Documento;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Usuario\Models\CuentaSistema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| El alcance del auditor externo (§ 4.19)
|--------------------------------------------------------------------------
|
| «Un rol de solo lectura con acceso limitado al alcance auditado.» En Statera
| el alcance es un sistema: el auditor ve los suyos y lo que es de toda la
| organización, y lo de los demás sistemas no existe para él — 404, igual que
| lo de otro cliente.
|
| El primer test descubre en vez de enumerar: todo modelo cuya tabla tenga
| `sistema_id` tiene que llevar `AcotadoPorAlcance`. Olvidarlo no rompe nada;
| deja al auditor viendo el registro de otro sistema, que es justo el fallo.
|
*/

/**
 * Los modelos de `app/Domain/**\/Models/`, por clase.
 *
 * @return list<class-string<Model>>
 */
function modelosDelDominio(): array
{
    $modelos = [];

    foreach (glob(base_path('app/Domain/*/Models/*.php')) ?: [] as $ruta) {
        $clase = 'App\\Domain\\'.str_replace('/', '\\', substr((string) strstr($ruta, 'Domain/'), 7, -4));

        if (class_exists($clase) && is_subclass_of($clase, Model::class)) {
            $modelos[] = $clase;
        }
    }

    return $modelos;
}

it('todo modelo con sistema_id se acota al alcance', function (): void {
    /*
     * `CuentaSistema` es la excepción declarada: es la tabla que DEFINE el
     * alcance, y acotarla por sí misma haría que el middleware no pudiera
     * leerla.
     */
    $excepciones = [CuentaSistema::class];

    $modelos = modelosDelDominio();
    expect($modelos)->not->toBeEmpty('El glob de modelos no encontró nada: el patrón dejó de casar.');

    $sinAcotar = collect($modelos)
        ->reject(fn (string $clase): bool => in_array($clase, $excepciones, true))
        ->filter(fn (string $clase): bool => Schema::hasColumn((new $clase)->getTable(), 'sistema_id'))
        ->reject(fn (string $clase): bool => in_array(AcotadoPorAlcance::class, class_uses_recursive($clase), true))
        ->values()
        ->all();

    expect($sinAcotar)->toBe([]);
});

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->auditado = Sistema::factory()->create(['codigo' => 'SIS-A']);
    $this->otro = Sistema::factory()->create(['codigo' => 'SIS-B']);

    $this->auditor = usuarioCon(Rol::Auditor);
    CuentaSistema::query()->create(['user_id' => $this->auditor->id, 'sistema_id' => $this->auditado->id]);
    $this->auditor->forceFill(['acceso_hasta' => today()->addMonth()])->save();

    $this->comoAuditor = function (callable $callback): mixed {
        $contexto = app(ContextoOrganizacion::class);
        $contexto->acotarASistemas([$this->auditado->id]);

        try {
            return $callback();
        } finally {
            $contexto->acotarASistemas(null);
        }
    };
});

it('ve su sistema y no el otro', function (): void {
    $this->actingAs($this->auditor)->get("/conformidad/sistemas/{$this->auditado->id}")->assertOk();
    $this->actingAs($this->auditor)->get("/conformidad/sistemas/{$this->otro->id}")->assertNotFound();

    expect(($this->comoAuditor)(fn () => Sistema::query()->pluck('codigo')->all()))->toBe(['SIS-A']);
});

it('las implantaciones del otro sistema no existen para él', function (): void {
    $suya = Implantacion::factory()->create(['sistema_id' => $this->auditado->id]);
    $ajena = Implantacion::factory()->create(['sistema_id' => $this->otro->id]);

    $this->actingAs($this->auditor)->get("/implantaciones/{$suya->id}")->assertOk();
    $this->actingAs($this->auditor)->get("/implantaciones/{$ajena->id}")->assertNotFound();
});

it('un activo es de los sistemas a los que da soporte', function (): void {
    $suyo = Activo::factory()->create();
    $suyo->sistemas()->attach($this->auditado->id, ['organizacion_id' => $this->organizacion->id]);
    $ajeno = Activo::factory()->create();
    $ajeno->sistemas()->attach($this->otro->id, ['organizacion_id' => $this->organizacion->id]);
    Activo::factory()->create();

    expect(($this->comoAuditor)(fn () => Activo::query()->pluck('id')->all()))->toBe([$suyo->id]);

    $this->actingAs($this->auditor)->get("/activos/{$ajeno->id}")->assertNotFound();
});

it('una evidencia se ve si prueba algo de su sistema', function (): void {
    $suya = Implantacion::factory()->create(['sistema_id' => $this->auditado->id]);
    $ajena = Implantacion::factory()->create(['sistema_id' => $this->otro->id]);

    $compartida = Evidencia::factory()->create();
    $compartida->implantaciones()->attach([$suya->id, $ajena->id], ['organizacion_id' => $this->organizacion->id]);
    $deOtro = Evidencia::factory()->create();
    $deOtro->implantaciones()->attach($ajena->id, ['organizacion_id' => $this->organizacion->id]);

    expect(($this->comoAuditor)(fn () => Evidencia::query()->pluck('id')->all()))->toBe([$compartida->id]);
});

it('lo que es de toda la organización lo ve igual', function (): void {
    $politica = Documento::factory()->actaRevision()->create();
    $deOtro = Documento::factory()->paraSistema($this->otro->id)->create(['codigo' => 'SOA-B']);
    $sinAtribuir = Incidente::factory()->create(['sistema_id' => null]);
    $incidenteDeOtro = Incidente::factory()->create(['sistema_id' => $this->otro->id]);

    ($this->comoAuditor)(function () use ($politica, $sinAtribuir, $incidenteDeOtro): void {
        expect(Documento::query()->pluck('id')->all())->toBe([$politica->id])
            ->and(Incidente::query()->whereKey($sinAtribuir->id)->exists())->toBeTrue()
            ->and(Incidente::query()->whereKey($incidenteDeOtro->id)->exists())->toBeFalse();
    });

    expect(Documento::query()->whereKey($deOtro->id)->exists())->toBeTrue();
});

it('el responsable y el técnico no tienen alcance: ven los dos sistemas', function (Rol $rol): void {
    $cuenta = usuarioCon($rol);

    $this->actingAs($cuenta)->get("/conformidad/sistemas/{$this->otro->id}")->assertOk();
})->with([Rol::ResponsableSeguridad, Rol::Tecnico]);

it('un auditor sin sistemas se señala en la lista y en su ficha', function (): void {
    $responsable = usuarioCon(Rol::ResponsableSeguridad);
    $sinAlcance = usuarioCon(Rol::Auditor);

    $this->actingAs($responsable)->get('/cuentas')
        ->assertInertia(fn ($pagina) => $pagina->where('auditoresSinAlcance', 1));

    $this->actingAs($responsable)->get("/cuentas/{$sinAlcance->id}")
        ->assertInertia(fn ($pagina) => $pagina->where('cuenta.sinAlcance', true));

    $this->actingAs($responsable)->get("/cuentas/{$this->auditor->id}")
        ->assertInertia(fn ($pagina) => $pagina->where('cuenta.sinAlcance', false));
});
