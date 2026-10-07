<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El primer paso de quien estrena Statera (punto 42)
|--------------------------------------------------------------------------
|
| Una organización recién dada de alta desde la plataforma nace sólo con su
| nombre. Antes del sistema, el panel le pide decir quién firma.
|
*/

it('pide la ficha mientras falta la razón social, el CIF o el domicilio', function (): void {
    comoOrganizacion();
    $responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->actingAs($responsable)->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('fichaOrganizacion', false));
});

it('da la ficha por hecha cuando están los tres datos', function (): void {
    $organizacion = comoOrganizacion();
    $organizacion->update(['razon_social' => 'Cliente, S.L.', 'cif' => 'B22222222', 'domicilio' => 'Calle Uno, 1']);
    $responsable = usuarioCon(Rol::ResponsableSeguridad);

    $this->actingAs($responsable)->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('fichaOrganizacion', true));
});

it('no se lo pide a quien no puede editar la ficha', function (Rol $rol): void {
    comoOrganizacion();
    $cuenta = usuarioCon($rol);

    $this->actingAs($cuenta)->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('fichaOrganizacion', null));
})->with([Rol::Tecnico, Rol::Auditor]);
