<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Models\FichaComercial;
use App\Domain\Traza\Models\EventoAuditoria;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| La ficha comercial del cliente (punto 54)
|--------------------------------------------------------------------------
*/

function comercialDePlataforma(): User
{
    $cuenta = User::factory()->comercial()->create();
    $cuenta->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => 'secreto'])->save();

    return $cuenta;
}

function fichaCompletaComercial(array $cambios = []): array
{
    return [
        'nombre' => 'Cliente Renombrado',
        'razon_social' => 'Cliente Renombrado, S.L.',
        'cif' => 'B55555555',
        'sector' => 'Logística',
        'contacto_nombre' => 'Fermín Facturas',
        'contacto_email' => 'facturas@cliente.test',
        'contacto_telefono' => '600000000',
        'notas' => 'Renueva en enero; pide descuento.',
        ...$cambios,
    ];
}

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    sinOrganizacion();
});

it('la plataforma edita la identificación y el contacto, y el cliente lo ve en su traza', function (): void {
    $this->actingAs(comercialDePlataforma())
        ->put("/plataforma/organizaciones/{$this->organizacion->id}/ficha", fichaCompletaComercial())
        ->assertRedirect();

    $organizacion = $this->organizacion->fresh();

    expect($organizacion?->razon_social)->toBe('Cliente Renombrado, S.L.')
        ->and(FichaComercial::query()->where('organizacion_afectada_id', $this->organizacion->id)->value('contacto_email'))->toBe('facturas@cliente.test');

    $evento = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->organizacion,
        fn () => EventoAuditoria::query()->where('entidad', 'Organizacion')->latest('id')->first(),
    );

    expect($evento?->valor_nuevo)->toHaveKey('razon_social')
        ->and($evento?->valor_nuevo)->not->toHaveKey('notas');
});

it('el CIF sigue siendo único', function (): void {
    Organizacion::factory()->create(['cif' => 'B55555555']);

    $this->actingAs(comercialDePlataforma())
        ->put("/plataforma/organizaciones/{$this->organizacion->id}/ficha", fichaCompletaComercial())
        ->assertSessionHasErrors('cif');
});

it('el cliente ve el contacto de facturación pero nunca las notas', function (): void {
    $this->actingAs(comercialDePlataforma())
        ->put("/plataforma/organizaciones/{$this->organizacion->id}/ficha", fichaCompletaComercial());

    $this->actingAs($this->responsable)->get('/organizacion')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('contactoFacturacion.contacto_email', 'facturas@cliente.test')
            ->missing('contactoFacturacion.notas'));

    expect(json_encode($this->actingAs($this->responsable)->get('/organizacion')->viewData('page')))
        ->not->toContain('pide descuento');
});

it('un cliente no edita su ficha comercial', function (): void {
    $this->actingAs($this->responsable)
        ->put("/plataforma/organizaciones/{$this->organizacion->id}/ficha", fichaCompletaComercial())
        ->assertForbidden();
});
