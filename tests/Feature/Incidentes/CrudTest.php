<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Incidente\Enums\ClasificacionIncidente;
use App\Domain\Incidente\Enums\PeligrosidadIncidente;
use App\Domain\Incidente\Models\Incidente;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El registro de incidentes por la interfaz
|--------------------------------------------------------------------------
|
| **Se pide lo mínimo para que el registro exista**, y es deliberado: quien
| apunta un incidente a las tres de la mañana no tiene todavía ni el impacto ni
| los activos afectados, y un formulario que se los exija hace que el incidente
| se apunte en otro sitio — o no se apunte.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('lista los incidentes con sus indicadores', function (): void {
    Incidente::factory()->count(3)->create();

    $this->actingAs($this->usuario)
        ->get('/incidentes')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('incidentes/Index')
            ->where('total', 3)
            ->has('alertas')
            ->has('pendientes')
            ->has('filas', 3));
});

it('propone el código del siguiente incidente del año', function (): void {
    Incidente::factory()->create(['codigo' => sprintf('INC-%d-01', Carbon::today()->year)]);
    Incidente::factory()->create(['codigo' => sprintf('INC-%d-02', Carbon::today()->year)]);

    $this->actingAs($this->usuario)
        ->get('/incidentes/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('incidentes/Formulario')
            ->where('sugerencia.codigo', sprintf('INC-%d-03', Carbon::today()->year)));
});

it('registra un incidente sin sistema ni activos', function (): void {
    $this->actingAs($this->usuario)
        ->post('/incidentes', [
            'codigo' => 'INC-2026-07',
            'titulo' => 'Correo fraudulento',
            'descripcion' => 'Doce correos pidiendo una transferencia urgente.',
            'clasificacion' => ClasificacionIncidente::Fraude->value,
            'peligrosidad' => PeligrosidadIncidente::Media->value,
            'fecha_deteccion' => Carbon::now()->subHours(2)->toDateTimeString(),
        ])
        ->assertRedirect();

    $incidente = Incidente::query()->where('codigo', 'INC-2026-07')->sole();

    expect($incidente->sistema_id)->toBeNull()
        ->and($incidente->notificable_aepd)->toBeFalse();
});

it('no se detecta lo que todavía no ha empezado', function (): void {
    $this->actingAs($this->usuario)
        ->post('/incidentes', [
            'codigo' => 'INC-2026-08',
            'titulo' => 'Algo',
            'descripcion' => 'Algo pasó.',
            'clasificacion' => ClasificacionIncidente::Otros->value,
            'peligrosidad' => PeligrosidadIncidente::Baja->value,
            'fecha_deteccion' => Carbon::now()->subDays(2)->toDateTimeString(),
            'fecha_inicio' => Carbon::now()->toDateTimeString(),
        ])
        ->assertSessionHasErrors('fecha_inicio');
});

it('pinta la ficha con las dos notificaciones y su fundamento', function (): void {
    $incidente = Incidente::factory()->enPlazoAepd()->create();

    $this->actingAs($this->usuario)
        ->get("/incidentes/{$incidente->id}")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('incidentes/Ficha')
            ->where('incidente.codigo', $incidente->codigo)
            ->where('notificaciones.aepd.notificable', true)
            ->where('notificaciones.ccnCert.horasRestantes', null)
            ->has('notificaciones.aepd.fundamento')
            ->has('notificaciones.ccnCert.fundamento')
            ->has('transiciones')
            ->etc());
});

/**
 * **Dos permisos y ninguno de supervisión.** El auditor lee y no escribe, como
 * en todos los registros; el técnico lo gestiona entero, notificación incluida.
 */
it('el auditor lee el registro y no lo toca', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $incidente = Incidente::factory()->create();

    $this->actingAs($auditor)->get('/incidentes')->assertOk();
    $this->actingAs($auditor)->get("/incidentes/{$incidente->id}")->assertOk();
    $this->actingAs($auditor)
        ->post("/incidentes/{$incidente->id}/estado", ['estado' => 'en_tratamiento'])
        ->assertForbidden();
});
