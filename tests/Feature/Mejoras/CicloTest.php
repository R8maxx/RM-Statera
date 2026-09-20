<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Mejora\CambiarEstadoMejora;
use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Excepciones\TransicionDeMejoraNoPermitida;
use App\Domain\Mejora\Models\Mejora;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El ciclo de una oportunidad de mejora (cláusula 10.1)
|--------------------------------------------------------------------------
|
| Es el registro más corto del producto y eso es lo que hay que fijar: **una sola
| transición pide algo escrito** —descartar— porque es la única decisión que un
| auditor puede cuestionar. Implantar no la pide: lo que se hizo lo cuentan sus
| tareas, y pedir un texto para cerrar lo que sí se hizo convierte en trámite el
| único gesto del registro que da alegrías.
|
| Y **dos permisos y no tres**: aquí no hay nada que firmar.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('descartar una mejora exige decir por qué', function (): void {
    $mejora = Mejora::factory()->create();

    expect(fn () => app(CambiarEstadoMejora::class)($mejora, EstadoMejora::Descartada, $this->usuario))
        ->toThrow(TransicionDeMejoraNoPermitida::class, 'un buzón abandonado');

    expect($mejora->refresh()->estado)->toBe(EstadoMejora::Propuesta);
});

it('implantar no pide motivo: lo que se hizo lo cuentan sus tareas', function (): void {
    $mejora = Mejora::factory()->create();

    app(CambiarEstadoMejora::class)($mejora, EstadoMejora::Implantada, $this->usuario);

    $mejora->refresh();

    expect($mejora->estado)->toBe(EstadoMejora::Implantada)
        ->and($mejora->fecha_cierre)->not->toBeNull();
});

it('reabrir una implantada suelta la fecha de cierre', function (): void {
    $mejora = Mejora::factory()->enEstado(EstadoMejora::Implantada)->create();

    app(CambiarEstadoMejora::class)($mejora, EstadoMejora::EnCurso, $this->usuario, 'Se dio por hecha antes de tiempo.');

    expect($mejora->refresh()->fecha_cierre)->toBeNull()
        ->and($mejora->estado)->toBe(EstadoMejora::EnCurso);
});

it('una descartada vuelve a proponerse, y el histórico conserva el motivo', function (): void {
    $mejora = Mejora::factory()->create();

    $cambiar = app(CambiarEstadoMejora::class);
    $cambiar($mejora, EstadoMejora::Descartada, $this->usuario, 'No hay presupuesto este año.');
    $cambiar($mejora, EstadoMejora::Propuesta, $this->usuario);

    $mejora->refresh();

    expect($mejora->estado)->toBe(EstadoMejora::Propuesta)
        ->and($mejora->fecha_cierre)->toBeNull();

    $motivo = $mejora->transiciones()
        ->where('estado_nuevo', EstadoMejora::Descartada->value)
        ->value('nota');

    expect($motivo)->toBe('No hay presupuesto este año.');
});

it('no se salta de descartada a implantada', function (): void {
    $mejora = Mejora::factory()->enEstado(EstadoMejora::Descartada)->create();

    expect(fn () => app(CambiarEstadoMejora::class)($mejora, EstadoMejora::Implantada, $this->usuario))
        ->toThrow(TransicionDeMejoraNoPermitida::class);
});

it('el formulario devuelve el error en el campo cuando falta el motivo', function (): void {
    $mejora = Mejora::factory()->create();

    $this->actingAs($this->usuario)
        ->post("/mejoras/{$mejora->id}/estado", ['estado' => EstadoMejora::Descartada->value])
        ->assertSessionHasErrors('nota');

    expect($mejora->refresh()->estado)->toBe(EstadoMejora::Propuesta);
});

/*
|--------------------------------------------------------------------------
| Dos verbos y no tres
|--------------------------------------------------------------------------
*/

it('el técnico gestiona las mejoras enteras: aquí no hay nada que firmar', function (): void {
    $tecnico = usuarioCon(Rol::Tecnico);
    $mejora = Mejora::factory()->create();

    $this->actingAs($tecnico)
        ->post("/mejoras/{$mejora->id}/estado", [
            'estado' => EstadoMejora::Descartada->value,
            'nota' => 'Se resuelve con lo que ya hay.',
        ])
        ->assertRedirect();

    expect($mejora->refresh()->estado)->toBe(EstadoMejora::Descartada);
});

it('un auditor lee el registro y no escribe en él', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $mejora = Mejora::factory()->create();

    $this->actingAs($auditor)->get('/mejoras')->assertOk();
    $this->actingAs($auditor)->get("/mejoras/{$mejora->id}")->assertOk();
    $this->actingAs($auditor)->get('/mejoras/crear')->assertForbidden();
    $this->actingAs($auditor)->delete("/mejoras/{$mejora->id}")->assertForbidden();
});

/*
 * El rasgo que define al módulo, escrito como aserción: ni un solo rojo. Si
 * alguien le pone `caducada` a un estado o deja pasar el rojo del plazo, esto se
 * pone en rojo de verdad.
 */
it('ningún estado de una mejora gasta rojo', function (): void {
    foreach (EstadoMejora::cases() as $estado) {
        expect($estado->tono())->not->toBe(
            'caducada',
            "El estado `{$estado->value}` gasta el rojo del dominio, y una mejora no incumple nada.",
        );
    }
});

it('una mejora pasada de fecha se señala en gris y no en rojo', function (): void {
    $mejora = Mejora::factory()->pasadaDeFecha()->create();

    expect($mejora->sePasoDeFecha())->toBeTrue();

    $this->actingAs($this->usuario)
        ->get("/mejoras/{$mejora->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('mejora.previstaTono', 'no_iniciado'));
});
