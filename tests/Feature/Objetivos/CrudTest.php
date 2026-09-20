<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El registro de objetivos de seguridad por la interfaz
|--------------------------------------------------------------------------
|
| Lo que más importa aquí es el reparto entre lo que se escribe y lo que se
| firma: el formulario admite un objetivo **sin fecha** —para poder apuntar la
| idea el día que se tiene— y deja de admitirlo en cuanto el objetivo está
| aprobado, porque la 6.2 exige decir para cuándo.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
});

it('lista los objetivos con sus indicadores de registro', function (): void {
    Objetivo::factory()->count(3)->create();

    $this->actingAs($this->usuario)
        ->get('/objetivos')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('objetivos/Index')
            ->where('total', 3)
            ->has('alertas')
            ->has('pendientes')
            ->has('filas', 3));
});

it('propone el código del siguiente objetivo del año', function (): void {
    Objetivo::factory()->create(['codigo' => sprintf('OBJ-%d-01', Carbon::today()->year)]);

    $this->actingAs($this->usuario)
        ->get('/objetivos/crear')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('objetivos/Formulario')
            ->where('sugerencia.codigo', sprintf('OBJ-%d-02', Carbon::today()->year)));
});

it('da de alta un objetivo sin fecha y lo deja propuesto', function (): void {
    $this->actingAs($this->usuario)
        ->post('/objetivos', [
            'codigo' => 'OBJ-2026-07',
            'titulo' => 'Reducir el tiempo de aplicación de parches críticos',
            'descripcion' => null,
            'recursos' => 'Dos jornadas al mes del equipo de sistemas.',
            'responsable_id' => $this->usuario->id,
            'fecha_objetivo' => null,
        ])
        ->assertRedirect();

    $objetivo = Objetivo::query()->where('codigo', 'OBJ-2026-07')->firstOrFail();

    expect($objetivo->estado)->toBe(EstadoObjetivo::Propuesto)
        ->and($objetivo->fecha_objetivo)->toBeNull()
        // El alta deja su fila de histórico, con `estado_anterior` nulo.
        ->and($objetivo->transiciones()->count())->toBe(1);
});

it('no deja quitarle la fecha a un objetivo ya aprobado', function (): void {
    $objetivo = Objetivo::factory()->enEstado(EstadoObjetivo::Aprobado, $this->usuario->id)->create();

    $this->actingAs($this->usuario)
        ->put("/objetivos/{$objetivo->id}", [
            'codigo' => $objetivo->codigo,
            'titulo' => $objetivo->titulo,
            'fecha_objetivo' => null,
        ])
        ->assertSessionHasErrors('fecha_objetivo');

    expect($objetivo->refresh()->fecha_objetivo)->not->toBeNull();
});

it('el código es único dentro de la organización y no del mundo', function (): void {
    Objetivo::factory()->create(['codigo' => 'OBJ-2026-01']);

    $this->actingAs($this->usuario)
        ->post('/objetivos', [
            'codigo' => 'OBJ-2026-01',
            'titulo' => 'Otro objetivo',
        ])
        ->assertSessionHasErrors('codigo');

    // Y en otra organización el mismo código entra sin problema.
    $otra = comoOrganizacion();
    $suUsuario = usuarioCon(Rol::ResponsableSeguridad, $otra);

    $this->actingAs($suUsuario)
        ->post('/objetivos', [
            'codigo' => 'OBJ-2026-01',
            'titulo' => 'El primero de la otra organización',
        ])
        ->assertRedirect();
});

it('el estado no se mueve desde el formulario de edición', function (): void {
    $objetivo = Objetivo::factory()->create();

    $this->actingAs($this->usuario)
        ->put("/objetivos/{$objetivo->id}", [
            'codigo' => $objetivo->codigo,
            'titulo' => $objetivo->titulo,
            'estado' => EstadoObjetivo::Aprobado->value,
        ])
        ->assertRedirect();

    // Aprobar es un gesto con firma, fecha e histórico: no cabe en un `update`.
    expect($objetivo->refresh()->estado)->toBe(EstadoObjetivo::Propuesto)
        ->and($objetivo->aprobado_en)->toBeNull();
});

it('un auditor lee el registro y no escribe en él', function (): void {
    $auditor = usuarioCon(Rol::Auditor);
    $objetivo = Objetivo::factory()->create();

    $this->actingAs($auditor)->get('/objetivos')->assertOk();
    $this->actingAs($auditor)->get("/objetivos/{$objetivo->id}")->assertOk();
    $this->actingAs($auditor)->get('/objetivos/crear')->assertForbidden();
    $this->actingAs($auditor)->delete("/objetivos/{$objetivo->id}")->assertForbidden();
});

it('la ficha manda las transiciones con su permiso y su exigencia de motivo', function (): void {
    $objetivo = Objetivo::factory()->create(['fecha_objetivo' => Carbon::today()->addMonths(2)]);

    $this->actingAs($this->usuario)
        ->get("/objetivos/{$objetivo->id}")
        ->assertInertia(function (AssertableInertia $pagina): void {
            $transiciones = collect($pagina->toArray()['props']['transiciones']);

            $aprobar = $transiciones->firstWhere('valor', EstadoObjetivo::Aprobado->value);
            $retirar = $transiciones->firstWhere('valor', EstadoObjetivo::Retirado->value);

            expect($aprobar['permiso'])->toBe('objetivos.aprobar')
                ->and($aprobar['exigeMotivo'])->toBeFalse()
                ->and($retirar['permiso'])->toBe('objetivos.aprobar')
                ->and($retirar['exigeMotivo'])->toBeTrue();
        });
});
