<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Domain\Vulnerabilidad\VectorCvss;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La ficha: cuánto tardó y por dónde pasó
|--------------------------------------------------------------------------
|
| El auditor no pregunta si está cerrada: pregunta cuánto tardó. Los días los
| cuenta `CicloRemediacion` en el servidor, contra el plazo, y paran cuando la
| vulnerabilidad sale de abierta y de remediación.
|
*/

beforeEach(function (): void {
    comoOrganizacion();
    $this->tecnico = usuarioCon(Rol::Tecnico);
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->travelTo(Carbon::parse('2026-08-12 09:00'));
    $this->vulnerabilidad = Vulnerabilidad::factory()->create([
        'fecha_deteccion' => '2026-08-12',
        'fecha_limite' => '2026-09-11',
    ]);

    $this->mover = fn ($quien, string $estado, ?string $nota = null) => $this->actingAs($quien)
        ->post("/vulnerabilidades/{$this->vulnerabilidad->id}/estado", ['estado' => $estado, 'nota' => $nota])
        ->assertSessionHasNoErrors();

    $this->ficha = fn () => $this->actingAs($this->responsable)->get("/vulnerabilidades/{$this->vulnerabilidad->id}");
});

it('cuenta hasta hoy y lo que se pasa del límite mientras sigue abierta', function (): void {
    $this->travelTo(Carbon::parse('2026-09-29 10:00'));

    ($this->ficha)()->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->where('plazo.dias', 30)
        ->where('plazo.transcurridos', 48)
        ->where('plazo.fuera', 18)
        ->where('plazo.corre', true)
        ->where('plazo.finRotulo', 'Hoy')
        ->where('vulnerabilidad.fueraDePlazo', true)
        ->where('camino.0.paso', 'actual')
        ->where('camino.1.paso', 'pendiente')
        ->etc());
});

it('para el reloj al mitigarla, aunque se cierre después', function (): void {
    $this->travelTo(Carbon::parse('2026-08-20 10:00'));
    ($this->mover)($this->tecnico, 'en_remediacion');
    $this->travelTo(Carbon::parse('2026-09-01 10:00'));
    ($this->mover)($this->tecnico, 'mitigada');
    $this->travelTo(Carbon::parse('2026-09-20 10:00'));
    ($this->mover)($this->tecnico, 'cerrada', 'Reescaneo limpio.');

    ($this->ficha)()->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->where('plazo.transcurridos', 20)
        ->where('plazo.fuera', 0)
        ->where('plazo.corre', false)
        ->where('plazo.finRotulo', 'Mitigada')
        ->where('plazo.fin', '2026-09-01')
        ->where('vulnerabilidad.fechaLimite', '2026-09-11')
        ->has('camino', 4)
        ->where('camino.1.paso', 'dado')
        ->where('camino.3.paso', 'actual')
        ->where('camino.3.valor', 'cerrada')
        ->etc());
});

it('dice el paso que se saltó en vez de pintarlo hecho', function (): void {
    ($this->mover)($this->tecnico, 'mitigada');

    ($this->ficha)()->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->where('camino.1.valor', 'en_remediacion')
        ->where('camino.1.paso', 'saltado')
        ->where('camino.1.fecha', null)
        ->where('camino.2.paso', 'actual')
        ->etc());
});

it('una aceptada sale del camino y acaba en su estado', function (): void {
    ($this->mover)($this->tecnico, 'en_remediacion');
    ($this->mover)($this->responsable, 'aceptada', 'Sin parche; aislado.');

    ($this->ficha)()->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->has('camino', 3)
        ->where('camino.1.valor', 'en_remediacion')
        ->where('camino.2.valor', 'aceptada')
        ->where('camino.2.paso', 'actual')
        ->where('plazo.finRotulo', 'Aceptada')
        ->etc());
});

it('una reabierta olvida el cierre anterior', function (): void {
    ($this->mover)($this->tecnico, 'mitigada');
    ($this->mover)($this->tecnico, 'cerrada', 'Verificado.');
    ($this->mover)($this->tecnico, 'abierta', 'Ha vuelto a salir.');

    ($this->ficha)()->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->where('camino.0.paso', 'actual')
        ->where('camino.2.paso', 'pendiente')
        ->where('camino.3.fecha', null)
        ->etc());
});

it('una informativa no tiene plazo', function (): void {
    $this->vulnerabilidad->forceFill(['severidad' => 'informativa', 'fecha_limite' => null])->save();

    ($this->ficha)()->assertInertia(fn (AssertableInertia $pagina) => $pagina
        ->where('plazo', null)
        ->has('severidades', 5)
        ->etc());
});

it('desglosa un vector de la v3 y deja pasar lo que no entiende', function (): void {
    $vector = new VectorCvss;

    expect($vector->desglose('CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:H'))
        ->toHaveCount(8)
        ->and($vector->desglose('AV:L/AC:L/PR:H/UI:R/S:C/C:N/I:L/A:N')[0])
        ->toBe(['metrica' => 'Vector de ataque', 'valor' => 'Local'])
        ->and($vector->desglose('CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N'))->toBeNull()
        ->and($vector->desglose('AV:N/AC:H'))->toBeNull()
        ->and($vector->desglose('AV:N/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H'))->toBeNull()
        ->and($vector->desglose(null))->toBeNull();
});
