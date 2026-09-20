<?php

declare(strict_types=1);

use App\Domain\Incidente\CambiarEstadoIncidente;
use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Excepciones\TransicionDeIncidenteNoPermitida;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Incidente\Models\IncidenteTransicion;
use App\Domain\Incidente\RegistrarIncidente;

/*
|--------------------------------------------------------------------------
| El ciclo de un incidente: op.exp.7
|--------------------------------------------------------------------------
|
| **Cerrar exige lección aprendida**, y es el argumento del módulo: `op.exp.7`
| pide aprender del incidente, y es el paso que todo el mundo se salta el día
| que el servicio vuelve. Lo imponen el dominio y un `CHECK`, y esta es la
| aserción que paga el módulo.
|
| `resuelto` **no es** `cerrado`, por lo mismo que `Cerrada` no es `Verificada`
| en una no conformidad: el servicio está restablecido y falta escribir qué se
| aprendió.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->cambiar = app(CambiarEstadoIncidente::class);
    $this->incidente = app(RegistrarIncidente::class)([
        'codigo' => 'INC-2026-01',
        'titulo' => 'Correo fraudulento',
        'descripcion' => 'Se recibieron doce correos pidiendo una transferencia urgente.',
        'fecha_deteccion' => now()->subHours(2),
    ], $this->usuario);
});

it('nace abierto y con su primera transición en el histórico', function (): void {
    expect($this->incidente->estado)->toBe(EstadoIncidente::Abierto)
        ->and(IncidenteTransicion::query()->count())->toBe(1)
        ->and(IncidenteTransicion::query()->sole()->estado_anterior)->toBeNull();
});

it('avanza de abierto a en tratamiento sin pedir nada escrito', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::EnTratamiento, $this->usuario);

    expect($this->incidente->fresh()?->estado)->toBe(EstadoIncidente::EnTratamiento);
});

/** La aserción que paga el módulo. */
it('no deja cerrar sin lección aprendida', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::Resuelto, $this->usuario);

    expect(fn () => ($this->cambiar)($this->incidente->fresh(), EstadoIncidente::Cerrado, $this->usuario))
        ->toThrow(TransicionDeIncidenteNoPermitida::class);
});

it('cierra con lección aprendida y sella la fecha', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::Resuelto, $this->usuario);

    $this->incidente->update(['leccion_aprendida' => 'Se añadió la regla al filtro de correo.']);

    $cerrado = ($this->cambiar)($this->incidente->fresh(), EstadoIncidente::Cerrado, $this->usuario);

    expect($cerrado->estado)->toBe(EstadoIncidente::Cerrado)
        ->and($cerrado->fecha_cierre)->not->toBeNull();
});

/**
 * La fecha la pone el dominio: el `CHECK` acopla estado y fecha en las dos
 * direcciones, así que reabrir tiene que soltarla o la escritura se rechaza.
 */
it('reabrir suelta la fecha de cierre', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::Resuelto, $this->usuario);
    $this->incidente->update(['leccion_aprendida' => 'Algo.']);
    ($this->cambiar)($this->incidente->fresh(), EstadoIncidente::Cerrado, $this->usuario);

    $reabierto = ($this->cambiar)(
        $this->incidente->fresh(),
        EstadoIncidente::Resuelto,
        $this->usuario,
        'La lección estaba mal redactada.',
    );

    expect($reabierto->fecha_cierre)->toBeNull();
});

/**
 * **De cerrado se vuelve a resuelto y nunca a abierto**, misma puerta que la
 * auditoría cerrada y el acta aprobada: reabrir para corregir es legítimo; decir
 * que el incidente nunca se resolvió es reescribir el pasado.
 */
it('de cerrado no se vuelve a abierto', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::Resuelto, $this->usuario);
    $this->incidente->update(['leccion_aprendida' => 'Algo.']);
    ($this->cambiar)($this->incidente->fresh(), EstadoIncidente::Cerrado, $this->usuario);

    expect(fn () => ($this->cambiar)($this->incidente->fresh(), EstadoIncidente::Abierto, $this->usuario, 'Motivo'))
        ->toThrow(TransicionDeIncidenteNoPermitida::class);
});

/** Volver atrás exige decir por qué; avanzar no. */
it('reabrir exige motivo escrito', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::Resuelto, $this->usuario);

    expect(fn () => ($this->cambiar)($this->incidente->fresh(), EstadoIncidente::EnTratamiento, $this->usuario))
        ->toThrow(TransicionDeIncidenteNoPermitida::class);
});

it('reabrir con motivo lo guarda en el histórico', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::Resuelto, $this->usuario);
    ($this->cambiar)($this->incidente->fresh(), EstadoIncidente::EnTratamiento, $this->usuario, 'Volvió a pasar.');

    expect(IncidenteTransicion::query()->latest('id')->first()?->nota)->toBe('Volvió a pasar.');
});

/*
|--------------------------------------------------------------------------
| Por la interfaz
|--------------------------------------------------------------------------
*/

it('rechaza el cierre sin lección con un mensaje legible y no con un 500', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::Resuelto, $this->usuario);

    $this->actingAs($this->usuario)
        ->from("/incidentes/{$this->incidente->id}")
        ->post("/incidentes/{$this->incidente->id}/estado", ['estado' => 'cerrado'])
        ->assertRedirect("/incidentes/{$this->incidente->id}")
        ->assertSessionHasErrors('estado');

    expect($this->incidente->fresh()?->estado)->toBe(EstadoIncidente::Resuelto);
});

it('guarda la lección aprendida por su propia ruta', function (): void {
    $this->actingAs($this->usuario)
        ->put("/incidentes/{$this->incidente->id}/leccion", [
            'leccion_aprendida' => 'El filtro no marcaba los dominios parecidos al propio.',
        ])
        ->assertRedirect();

    expect($this->incidente->fresh()?->leccion_aprendida)
        ->toBe('El filtro no marcaba los dominios parecidos al propio.');
});

/**
 * `resuelto` cuenta como abierto: el servicio volvió y falta la lección, que es
 * justo el paso que no se puede dar por bueno.
 */
it('cuenta como abierto lo que está resuelto y sin cerrar', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::Resuelto, $this->usuario);

    expect(Incidente::query()->abiertos()->count())->toBe(1)
        ->and(Incidente::query()->sinLeccion()->count())->toBe(1);
});

/** Por construcción no puede haber ninguno: lo impide el `CHECK`. */
it('nunca hay cerrados sin lección', function (): void {
    ($this->cambiar)($this->incidente, EstadoIncidente::Resuelto, $this->usuario);
    $this->incidente->update(['leccion_aprendida' => 'Algo.']);
    ($this->cambiar)($this->incidente->fresh(), EstadoIncidente::Cerrado, $this->usuario);

    expect(Incidente::query()->cerradosSinLeccion()->count())->toBe(0);
});

/**
 * Ningún estado gasta rojo: un incidente abierto no va mal, va siendo atendido.
 * El único rojo es el plazo de la AEPD.
 */
it('ningún estado gasta el rojo', function (): void {
    foreach (EstadoIncidente::cases() as $estado) {
        expect($estado->tono())->not->toBe('caducada');
    }
});
