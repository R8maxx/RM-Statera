<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Las cuentas no cruzan de organización
|--------------------------------------------------------------------------
|
| `users` está fuera de las tres capas: sin scope global y sin RLS. Todo lo que
| aquí se acota, se acota a mano, y estos tests son lo único que lo comprueba
| de extremo a extremo.
|
*/

beforeEach(function (): void {
    $this->propia = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->ajena = Organizacion::factory()->create();
    $this->deOtra = usuarioCon(Rol::Tecnico, $this->ajena);

    comoOrganizacion($this->propia);
});

it('no lista las cuentas de otra organización', function (): void {
    $this->actingAs($this->responsable)->get('/cuentas')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina->where('filas', fn ($filas): bool => collect($filas)
            ->pluck('email')
            ->doesntContain($this->deOtra->email)));
});

it('responde 404 en todo lo que toca una cuenta de otra organización', function (): void {
    $id = $this->deOtra->id;

    $this->actingAs($this->responsable)->get("/cuentas/{$id}")->assertNotFound();
    $this->actingAs($this->responsable)->get("/cuentas/{$id}/editar")->assertNotFound();
    $this->actingAs($this->responsable)->put("/cuentas/{$id}", ['rol' => 'auditor'])->assertNotFound();
    $this->actingAs($this->responsable)->post("/cuentas/{$id}/desactivar")->assertNotFound();
    $this->actingAs($this->responsable)->post("/cuentas/{$id}/reactivar")->assertNotFound();
    $this->actingAs($this->responsable)->post("/cuentas/{$id}/reenviar")->assertNotFound();

    expect($this->deOtra->fresh()?->rol())->toBe(Rol::Tecnico);
});

it('no se le puede dar a un auditor un sistema de otra organización', function (): void {
    $ajeno = app(ContextoOrganizacion::class)->paraOrganizacion($this->ajena, fn (): Sistema => Sistema::factory()->create());

    $this->actingAs($this->responsable)->post('/cuentas', [
        'name' => 'Auditora', 'email' => 'aud@ejemplo.test', 'rol' => 'auditor',
        'sistemas' => [$ajeno->id], 'acceso_hasta' => today()->addWeek()->toDateString(),
    ])->assertSessionHasErrors('sistemas.0');

    expect(User::query()->where('organizacion_id', $this->propia->id)->where('email', 'aud@ejemplo.test')->exists())->toBeFalse();
});

it('una cuenta nueva nace en la organización de quien la invita', function (): void {
    Notification::fake();

    $this->actingAs($this->responsable)->post('/cuentas', ['name' => 'Ana', 'email' => 'ana@ejemplo.test', 'rol' => 'tecnico']);

    $cuenta = User::query()->where('organizacion_id', $this->propia->id)->where('email', 'ana@ejemplo.test')->first();

    expect($cuenta)->not->toBeNull()
        ->and($cuenta?->rol())->toBe(Rol::Tecnico);
});
