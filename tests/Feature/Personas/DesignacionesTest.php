<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Persona\DesignarRol;
use App\Domain\Persona\Enums\RolEns;
use App\Domain\Persona\Excepciones\DesignacionIncompatible;
use App\Domain\Persona\Excepciones\PersonaNoDesignable;
use App\Domain\Persona\Excepciones\RolYaDesignado;
use App\Domain\Persona\Models\DesignacionRol;
use App\Domain\Persona\Models\Persona;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Los nombramientos ENS: la cláusula 5.3
|--------------------------------------------------------------------------
|
| **Es lo que paga el módulo.** La especificación no dice «avisar» ni
| «señalar»: dice que el sistema debe **impedir** que el responsable de
| seguridad y el responsable del sistema recaigan en la misma persona.
|
| La regla vive en `DesignarRol` y no en un `CHECK` porque es una condición
| **entre filas** y un `CHECK` sólo ve una — el precedente exacto es
| `RegistrarDependencia`, que rechaza los ciclos del grafo de activos por lo
| mismo. Y no vive en el `FormRequest` porque vale igual para un importador y
| para el seeder, que es lo que prueba el primer bloque de aquí.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->sistema = Sistema::factory()->create();
    $this->persona = Persona::factory()->create();
    $this->designar = app(DesignarRol::class);
});

it('designa a una persona en un rol con su fecha de inicio', function (): void {
    $designacion = ($this->designar)(
        $this->persona,
        $this->sistema,
        RolEns::ResponsableSeguridad,
        Carbon::today()->subMonths(3),
        $this->usuario,
    );

    expect($designacion->rol)->toBe(RolEns::ResponsableSeguridad)
        ->and($designacion->estaVigente())->toBeTrue()
        ->and($designacion->designada_por_id)->toBe($this->usuario->id);
});

it('impide que el responsable de seguridad sea además responsable del sistema', function (): void {
    ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSeguridad);

    expect(fn () => ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSistema))
        ->toThrow(DesignacionIncompatible::class);
});

it('lo impide también en el orden contrario', function (): void {
    ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSistema);

    expect(fn () => ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSeguridad))
        ->toThrow(DesignacionIncompatible::class);
});

/**
 * La incompatibilidad es **por sistema**, que es donde significa algo y como la
 * reparte la guía. Una persona puede ser responsable de seguridad de un sistema
 * y responsable del sistema de otro sin que nadie pierda separación de
 * funciones.
 */
it('no impide los dos roles en sistemas distintos', function (): void {
    $otro = Sistema::factory()->create();

    ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSeguridad);
    ($this->designar)($this->persona, $otro, RolEns::ResponsableSistema);

    expect(DesignacionRol::query()->vigentes()->count())->toBe(2);
});

/** Una designación revocada ya no choca: la comprobación mira las vigentes. */
it('deja designar el rol incompatible tras revocar el anterior', function (): void {
    $primera = ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSeguridad);

    $this->designar->revocar($primera);

    ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSistema);

    expect(DesignacionRol::query()->vigentes()->count())->toBe(1);
});

it('no admite un segundo titular vigente de un rol único', function (): void {
    $otra = Persona::factory()->create();

    ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSeguridad);

    expect(fn () => ($this->designar)($otra, $this->sistema, RolEns::ResponsableSeguridad))
        ->toThrow(RolYaDesignado::class);
});

/**
 * Responsable de la información y del servicio pueden ser varios: uno por cada
 * información tratada y por cada servicio prestado. Exigirles unicidad sería
 * inventarse una restricción que la guía no pone.
 */
it('admite varios responsables de la información en el mismo sistema', function (): void {
    $otra = Persona::factory()->create();

    ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableInformacion);
    ($this->designar)($otra, $this->sistema, RolEns::ResponsableInformacion);

    expect(DesignacionRol::query()->vigentes()->count())->toBe(2);
});

it('no designa a quien ya no está en plantilla', function (): void {
    $baja = Persona::factory()->deBaja()->create();

    expect(fn () => ($this->designar)($baja, $this->sistema, RolEns::ResponsableSeguridad))
        ->toThrow(PersonaNoDesignable::class);
});

/**
 * Revocar **no borra**: la pregunta del auditor es «¿desde cuándo?» y también
 * «¿hasta cuándo?», y una tabla que sólo guarde el nombramiento actual no
 * contesta ninguna de las dos (invariante 7).
 */
it('revocar pone fecha de fin y conserva la fila', function (): void {
    $designacion = ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSeguridad);

    $this->designar->revocar($designacion, Carbon::today());

    expect(DesignacionRol::query()->count())->toBe(1)
        ->and($designacion->fresh()?->estaVigente())->toBeFalse();
});

/** El `CHECK` rechaza `hasta < desde`, y el error hablaría de una restricción. */
it('nunca revoca antes de la fecha de designación', function (): void {
    $designacion = ($this->designar)(
        $this->persona,
        $this->sistema,
        RolEns::ResponsableSeguridad,
        Carbon::today(),
    );

    $revocada = $this->designar->revocar($designacion, Carbon::today()->subMonth());

    expect($revocada->hasta?->toDateString())->toBe($designacion->desde->toDateString());
});

// --- Por la interfaz ------------------------------------------------------

it('rechaza la designación incompatible con un mensaje legible y no con un 500', function (): void {
    ($this->designar)($this->persona, $this->sistema, RolEns::ResponsableSeguridad);

    $this->actingAs($this->usuario)
        ->from("/personas/{$this->persona->id}")
        ->post("/personas/{$this->persona->id}/designaciones", [
            'sistema_id' => $this->sistema->id,
            'rol' => RolEns::ResponsableSistema->value,
        ])
        ->assertRedirect("/personas/{$this->persona->id}")
        ->assertSessionHasErrors('rol');

    expect(DesignacionRol::query()->vigentes()->count())->toBe(1);
});

/**
 * **Designar es el noveno verbo de supervisión.** Un técnico da de alta a
 * alguien, apunta su formación y marca su checklist; nombrar al responsable de
 * seguridad de un sistema es un acto que la organización firma.
 */
it('el técnico gestiona personas pero no designa roles', function (): void {
    $tecnica = usuarioCon(Rol::Tecnico);

    $this->actingAs($tecnica)
        ->post("/personas/{$this->persona->id}/designaciones", [
            'sistema_id' => $this->sistema->id,
            'rol' => RolEns::ResponsableSeguridad->value,
        ])
        ->assertForbidden();

    $this->actingAs($tecnica)->get('/personas')->assertOk();
});
