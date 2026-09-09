<?php

declare(strict_types=1);

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Páginas de error
|--------------------------------------------------------------------------
|
| Antes un 404 devolvía la plantilla de Symfony: ni se parecía a la
| herramienta ni decía a dónde ir. Aquí importa más de lo normal porque el
| aislamiento multi-tenant responde 404 —no 403— cuando alguien pide un
| recurso de otra organización: decir «existe pero no es tuyo» ya sería
| filtrar información. Ese 404 lo va a ver gente real.
|
| Lo que se fija es el contrato: componente `Error`, el código de estado en
| los props, y —sobre todo— que la respuesta conserva su estado HTTP. Un 404
| que devuelve 200 rompe el cacheado y los rastreadores.
|
*/

it('pinta la página de error de Inertia cuando la ruta no existe', function (): void {
    $this->get('/ruta-que-no-existe')
        ->assertStatus(404)
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('Error')
            ->where('estado', 404)
        );
});

it('conserva el código de estado, no lo convierte en un 200', function (): void {
    // Una página de error que responde 200 le dice al navegador y a cualquier
    // rastreador que todo ha ido bien.
    expect($this->get('/otra-ruta-inexistente')->getStatusCode())->toBe(404);
});

it('devuelve un 404 —no un 403— al pedir un sistema de otra organización', function (): void {
    $marco = Marco::factory()->create();

    $propia = Organizacion::factory()->create(['nombre' => 'Propia']);
    $ajena = Organizacion::factory()->create(['nombre' => 'Ajena']);

    comoOrganizacion($ajena);
    $sistemaAjeno = Sistema::factory()->de($ajena)->conMarco($marco)->create(['codigo' => 'AJE-01']);

    comoOrganizacion($propia);
    $usuario = usuarioCon();

    $this->actingAs($usuario)
        ->get("/sistemas/{$sistemaAjeno->id}/editar")
        ->assertStatus(404)
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->component('Error')->where('estado', 404));
});
