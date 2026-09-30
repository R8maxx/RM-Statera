<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Cambio\CambiarEstadoCambio;
use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Excepciones\TransicionDeCambioNoPermitida;
use App\Domain\Cambio\Models\CambioSgsi;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| El ciclo de un cambio del SGSI (cláusula 6.3)
|--------------------------------------------------------------------------
|
| La regla del módulo es la de objetivos: **un borrador se escribe como se
| pueda; un compromiso, no**. Aprobar exige plazo y firmante, y lo hace quien
| tiene `cambios_sgsi.aprobar`. Revisar exige decir si sirvió.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->cambiar = app(CambiarEstadoCambio::class);
});

it('aprobar sin fecha prevista se rechaza en el dominio', function (): void {
    $cambio = CambioSgsi::factory()->create();

    expect(fn () => ($this->cambiar)($cambio, EstadoCambio::Aprobado, $this->usuario))
        ->toThrow(TransicionDeCambioNoPermitida::class, 'para cuándo');

    expect($cambio->refresh()->estado)->toBe(EstadoCambio::Propuesto);
});

it('aprobar estampa la firma, y reabrir después no la reescribe', function (): void {
    $cambio = CambioSgsi::factory()->create(['fecha_prevista' => Carbon::today()->addMonth()]);
    $otro = usuarioCon();

    ($this->cambiar)($cambio, EstadoCambio::Aprobado, $this->usuario);
    ($this->cambiar)($cambio, EstadoCambio::Implantado, $otro);
    ($this->cambiar)($cambio, EstadoCambio::Aprobado, $otro, 'No quedó hecho del todo.');

    $cambio->refresh();

    expect($cambio->estado)->toBe(EstadoCambio::Aprobado)
        ->and($cambio->aprobado_por_id)->toBe($this->usuario->id)
        ->and($cambio->fecha_implantacion)->toBeNull();
});

it('volver a propuesto suelta la firma entera, y el histórico la conserva', function (): void {
    $cambio = CambioSgsi::factory()->create(['fecha_prevista' => Carbon::today()->addMonth()]);

    ($this->cambiar)($cambio, EstadoCambio::Aprobado, $this->usuario);
    ($this->cambiar)($cambio, EstadoCambio::Propuesto, $this->usuario);

    $cambio->refresh();

    expect($cambio->aprobado_por_id)->toBeNull()
        ->and($cambio->aprobado_en)->toBeNull()
        ->and($cambio->transiciones()->where('estado_nuevo', 'aprobado')->value('usuario_id'))->toBe($this->usuario->id);
});

it('revisar exige decir si sirvió, y el texto queda en el cambio', function (): void {
    $cambio = CambioSgsi::factory()->enEstado(EstadoCambio::Implantado, $this->usuario)->create();

    expect(fn () => ($this->cambiar)($cambio, EstadoCambio::Revisado, $this->usuario, '   '))
        ->toThrow(TransicionDeCambioNoPermitida::class, 'si consiguió');

    ($this->cambiar)($cambio, EstadoCambio::Revisado, $this->usuario, 'Sí: el alcance nuevo ya se auditó.');

    $cambio->refresh();

    expect($cambio->estado)->toBe(EstadoCambio::Revisado)
        ->and($cambio->revision)->toBe('Sí: el alcance nuevo ya se auditó.')
        ->and($cambio->fecha_cierre)->not->toBeNull();
});

it('descartar exige motivo', function (): void {
    $cambio = CambioSgsi::factory()->create();

    expect(fn () => ($this->cambiar)($cambio, EstadoCambio::Descartado, $this->usuario))
        ->toThrow(TransicionDeCambioNoPermitida::class, 'por qué');
});

it('no se salta de propuesto a implantado', function (): void {
    $cambio = CambioSgsi::factory()->create(['fecha_prevista' => Carbon::today()->addMonth()]);

    expect(fn () => ($this->cambiar)($cambio, EstadoCambio::Implantado, $this->usuario))
        ->toThrow(TransicionDeCambioNoPermitida::class);
});

it('el formulario devuelve el error en el campo cuando falta la nota', function (): void {
    $cambio = CambioSgsi::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/cambios-sgsi/{$cambio->id}/estado", ['estado' => 'descartado'])
        ->assertSessionHasErrors('nota');
});

it('el técnico propone y lleva a cabo, y no aprueba', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $cambio = CambioSgsi::factory()->create(['fecha_prevista' => Carbon::today()->addMonth()]);

    $this->actingAs($tecnico)
        ->post("/cambios-sgsi/{$cambio->id}/estado", ['estado' => 'aprobado'])
        ->assertForbidden();

    expect($cambio->refresh()->estado)->toBe(EstadoCambio::Propuesto);

    // Descartar su propia propuesta sí puede.
    $this->actingAs($tecnico)
        ->post("/cambios-sgsi/{$cambio->id}/estado", ['estado' => 'descartado', 'nota' => 'Se resuelve sin cambiar nada.'])
        ->assertRedirect();

    expect($cambio->refresh()->estado)->toBe(EstadoCambio::Descartado);
});

it('renunciar a un cambio ya aprobado es de quien firma', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $cambio = CambioSgsi::factory()->enEstado(EstadoCambio::Aprobado, $this->usuario)->create();

    $this->actingAs($tecnico)
        ->post("/cambios-sgsi/{$cambio->id}/estado", ['estado' => 'descartado', 'nota' => 'Ya no hace falta.'])
        ->assertForbidden();

    $this->actingAs($tecnico)
        ->post("/cambios-sgsi/{$cambio->id}/estado", ['estado' => 'implantado'])
        ->assertRedirect();

    expect($cambio->refresh()->estado)->toBe(EstadoCambio::Implantado);
});

it('el responsable de seguridad aprueba desde la ficha', function (): void {
    $cambio = CambioSgsi::factory()->create(['fecha_prevista' => Carbon::today()->addMonth()]);

    $this->actingAs($this->usuario)
        ->post("/cambios-sgsi/{$cambio->id}/estado", ['estado' => 'aprobado'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($cambio->refresh()->aprobado_por_id)->toBe($this->usuario->id);
});

it('un auditor lee el registro y no escribe en él', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $cambio = CambioSgsi::factory()->create();

    $this->actingAs($auditor)->get('/cambios-sgsi')->assertOk();
    $this->actingAs($auditor)->get("/cambios-sgsi/{$cambio->id}")->assertOk();
    $this->actingAs($auditor)->get('/cambios-sgsi/crear')->assertForbidden();
    $this->actingAs($auditor)->delete("/cambios-sgsi/{$cambio->id}")->assertForbidden();
});
