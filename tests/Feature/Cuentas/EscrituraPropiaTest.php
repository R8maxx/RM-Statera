<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Tarea\Models\Tarea;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El técnico escribe lo suyo (§ 4.19)
|--------------------------------------------------------------------------
|
| «Un técnico ve sus tareas y las implantaciones a su cargo.» Se leyó así: lee
| todo, y escribe sólo en lo que tiene asignado o en lo que no tiene a nadie.
| La regla vale en todas las rutas de escritura de los dos módulos, porque va
| en el grupo y no en cada `FormRequest`.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->tecnico = usuarioCon(Rol::Tecnico);
    $this->companera = usuarioCon(Rol::Tecnico);
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
});

it('lee la tarea de otra persona pero no la mueve, ni la edita, ni la borra', function (): void {
    $ajena = Tarea::factory()->create(['responsable_id' => $this->companera->id]);

    $this->actingAs($this->tecnico)->get("/tareas/{$ajena->id}")->assertOk();

    $this->actingAs($this->tecnico)->post("/tareas/{$ajena->id}/estado", ['estado' => 'en_curso'])->assertForbidden();
    $this->actingAs($this->tecnico)->get("/tareas/{$ajena->id}/editar")->assertForbidden();
    $this->actingAs($this->tecnico)->delete("/tareas/{$ajena->id}")->assertForbidden();
    $this->actingAs($this->tecnico)->put("/tareas/{$ajena->id}/subtareas", ['subtareas' => []])->assertForbidden();

    expect($ajena->fresh()?->estado->value)->toBe('pendiente');
});

it('mueve la suya y la que no tiene a nadie', function (?string $quien): void {
    $tarea = Tarea::factory()->create(['responsable_id' => $quien === 'yo' ? $this->tecnico->id : null]);

    $this->actingAs($this->tecnico)->post("/tareas/{$tarea->id}/estado", ['estado' => 'en_curso'])->assertRedirect();

    expect($tarea->fresh()?->estado->value)->toBe('en_curso');
})->with(['yo', null]);

it('el responsable de seguridad mueve la de cualquiera', function (): void {
    $ajena = Tarea::factory()->create(['responsable_id' => $this->companera->id]);

    $this->actingAs($this->responsable)->post("/tareas/{$ajena->id}/estado", ['estado' => 'en_curso'])->assertRedirect();

    expect($ajena->fresh()?->estado->value)->toBe('en_curso');
});

it('en el cambio masivo se salta las ajenas y lo dice', function (): void {
    $suya = Tarea::factory()->create(['responsable_id' => $this->tecnico->id]);
    $ajena = Tarea::factory()->create(['responsable_id' => $this->companera->id]);

    $this->actingAs($this->tecnico)
        ->post('/tareas/estado', ['tareas' => [$suya->id, $ajena->id], 'estado' => 'en_curso'])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.exito', fn (string $mensaje): bool => str_contains($mensaje, '1 están a cargo de otra persona'));

    expect($suya->fresh()?->estado->value)->toBe('en_curso')
        ->and($ajena->fresh()?->estado->value)->toBe('pendiente');
});

it('en el tablero la tarjeta ajena llega sin adónde moverse', function (): void {
    $ajena = Tarea::factory()->create(['responsable_id' => $this->companera->id]);

    $this->actingAs($this->tecnico)->get('/tareas/tablero')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('columnas.0.tarjetas.0.id', $ajena->id)
            ->where('columnas.0.tarjetas.0.transiciones', [])
            ->where('columnas.0.tarjetas.0.descartable', false));
});

it('la implantación de otro se lee y no se escribe', function (): void {
    $ajena = Implantacion::factory()->create(['responsable_id' => $this->companera->id]);

    $this->actingAs($this->tecnico)->get("/implantaciones/{$ajena->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('puedeEscribir', false)
            ->where('aCargoDeOtro', $this->companera->name));

    $this->actingAs($this->tecnico)
        ->post("/implantaciones/{$ajena->id}/estado", ['estado' => 'planificado'])
        ->assertForbidden();

    $this->actingAs($this->tecnico)
        ->post('/implantaciones/estado', ['implantaciones' => [$ajena->id], 'estado' => 'planificado']);

    expect($ajena->fresh()?->estado->value)->toBe('no_iniciado');
});

it('la primera vez en la sesión el técnico empieza por lo suyo', function (): void {
    Tarea::factory()->create(['responsable_id' => $this->tecnico->id]);

    $this->actingAs($this->tecnico)->get('/tareas')
        ->assertRedirect('/tareas?'.http_build_query(['filter' => ['responsable_id' => (string) $this->tecnico->id]]));

    // Y si quita el filtro, no vuelve.
    $this->actingAs($this->tecnico)->get('/tareas')->assertOk();
});

it('sin nada a su cargo no se le filtra: una tabla vacía no es un punto de partida', function (): void {
    Tarea::factory()->create(['responsable_id' => $this->companera->id]);

    $this->actingAs($this->tecnico)->get('/tareas')->assertOk();
    $this->actingAs($this->tecnico)->get('/implantaciones')->assertOk();
});

it('al responsable no se le filtra nada', function (): void {
    $this->actingAs($this->responsable)->get('/tareas')->assertOk();
    $this->actingAs($this->responsable)->get('/implantaciones')->assertOk();
});
