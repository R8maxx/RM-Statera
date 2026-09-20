<?php

declare(strict_types=1);

use App\Domain\Incidente\Models\Incidente;
use App\Domain\Incidente\PlazoNotificacion;
use App\Domain\Incidente\RegistrarNotificacion;
use App\Domain\Incidente\RegistroIncidentes;
use App\Http\Resources\IncidenteRecurso;
use App\Http\Resources\Panel\Indicador;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El reloj de la notificación, y dónde NO lo hay
|--------------------------------------------------------------------------
|
| **Sólo hay cuenta atrás donde la ley pone un número.** Son 72 h desde que se
| tiene constancia, y el número sale del artículo 33.1 del RGPD. Para el
| CCN-CERT el RD 311/2022 dice «sin dilación» y no fija horas: inventarse un
| plazo sería una opinión de la herramienta disfrazada de obligación legal, que
| es lo mismo que el producto se niega a hacer con el riesgo residual.
|
| Y es **el único rojo del módulo**: ni los estados ni la peligrosidad lo gastan.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->registro = app(RegistroIncidentes::class);

    $this->valor = function (string $clave): int {
        $indicador = collect([...$this->registro->alertas(), ...$this->registro->pendientes()])
            ->first(fn (Indicador $uno): bool => $uno->clave === $clave);

        expect($indicador)->not->toBeNull("No existe el indicador `{$clave}`.");

        return $indicador->valor;
    };
});

it('no cuenta plazo si no hay datos personales de por medio', function (): void {
    $incidente = Incidente::factory()->create([
        'fecha_deteccion' => Carbon::now()->subDays(10),
    ]);

    $plazo = $incidente->plazoAepd();

    expect($plazo->aplica)->toBeFalse()
        ->and($plazo->vencido)->toBeFalse()
        ->and($plazo->tono)->toBe('no_aplica');
});

it('cuenta las horas que quedan dentro del plazo', function (): void {
    $incidente = Incidente::factory()->enPlazoAepd()->create();

    $plazo = $incidente->plazoAepd();

    expect($plazo->aplica)->toBeTrue()
        ->and($plazo->vencido)->toBeFalse()
        ->and($plazo->horasRestantes)->toBeGreaterThan(0)
        ->and($plazo->horasRestantes)->toBeLessThanOrEqual(PlazoNotificacion::HORAS_AEPD);
});

/** El único rojo del módulo. */
it('marca el plazo vencido cuando pasan las 72 horas sin notificar', function (): void {
    $incidente = Incidente::factory()->fueraDePlazoAepd()->create();

    $plazo = $incidente->plazoAepd();

    expect($plazo->vencido)->toBeTrue()
        ->and($plazo->tono)->toBe('caducada');
});

/**
 * Notificar tarde sigue siendo un incumplimiento, y esconderlo al anotar la
 * notificación sería borrar la prueba.
 */
it('distingue la notificación a tiempo de la que llegó tarde', function (): void {
    $aTiempo = Incidente::factory()->notificadoAepd()->create();

    $tarde = Incidente::factory()->create([
        'notificable_aepd' => true,
        'fecha_deteccion' => Carbon::now()->subHours(PlazoNotificacion::HORAS_AEPD + 10),
        'notificado_aepd_en' => Carbon::now(),
    ]);

    expect($aTiempo->plazoAepd()->vencido)->toBeFalse()
        ->and($aTiempo->plazoAepd()->tono)->toBe('implantado')
        ->and($tarde->plazoAepd()->vencido)->toBeTrue()
        ->and($tarde->plazoAepd()->tono)->toBe('caducada');
});

/**
 * **La decisión del módulo**: el RD 311/2022 dice «sin dilación» y no fija
 * horas, así que aquí no hay cuenta atrás ni rojo que valga.
 */
it('no inventa una cuenta atrás para el CCN-CERT', function (): void {
    $incidente = Incidente::factory()->create([
        'notificable_ccn_cert' => true,
        'fecha_deteccion' => Carbon::now()->subMonths(6),
    ]);

    $notificacion = $incidente->notificacionCcnCert();

    expect($notificacion->aplica)->toBeTrue()
        ->and($notificacion->notificado)->toBeFalse()
        ->and($notificacion->vencido)->toBeFalse()
        ->and($notificacion->horasRestantes)->toBeNull()
        ->and($notificacion->tono)->not->toBe('caducada');
});

/*
|--------------------------------------------------------------------------
| Anotar la notificación
|--------------------------------------------------------------------------
*/

it('anota la notificación y la deja en el histórico', function (): void {
    $incidente = Incidente::factory()->enPlazoAepd()->create();

    app(RegistrarNotificacion::class)->aepd($incidente, Carbon::now(), $this->usuario, 'Registro 2026/0001.');

    expect($incidente->fresh()?->notificado_aepd_en)->not->toBeNull()
        ->and($incidente->transiciones()->latest('id')->first()?->nota)
        ->toContain('Notificado a la AEPD');
});

/**
 * **No cambia el estado**, y es deliberado: se puede notificar con el incidente
 * abierto, en tratamiento o resuelto, y meterlo en la máquina de estados
 * obligaría a inventarse un «notificado» que no dice nada de la contención.
 */
it('anotar la notificación no mueve el estado', function (): void {
    $incidente = Incidente::factory()->enPlazoAepd()->create();
    $antes = $incidente->estado;

    app(RegistrarNotificacion::class)->aepd($incidente, null, $this->usuario);

    expect($incidente->fresh()?->estado)->toBe($antes);
});

/**
 * Si se ha notificado, es que había que notificar: rechazar la anotación
 * obligaría a editar el incidente antes de poder decir la verdad sobre él.
 */
it('marca notificable al anotar una notificación que no lo estaba', function (): void {
    $incidente = Incidente::factory()->create();

    app(RegistrarNotificacion::class)->ccnCert($incidente, null, $this->usuario);

    expect($incidente->fresh()?->notificable_ccn_cert)->toBeTrue();
});

it('anota la notificación por la interfaz con la fecha que se le pasa', function (): void {
    $incidente = Incidente::factory()->fueraDePlazoAepd()->create();
    $cuando = Carbon::now()->subHour();

    $this->actingAs($this->usuario)
        ->post("/incidentes/{$incidente->id}/notificaciones", [
            'destinatario' => 'aepd',
            'notificado_en' => $cuando->toDateTimeString(),
        ])
        ->assertRedirect();

    expect($incidente->fresh()?->notificado_aepd_en?->format('Y-m-d H:i'))
        ->toBe($cuando->format('Y-m-d H:i'));
});

/*
|--------------------------------------------------------------------------
| Las cifras
|--------------------------------------------------------------------------
*/

/**
 * El test que impide que el panel y la tabla discrepen: cada indicador tiene un
 * filtro con **su misma clave**, y `Filtro::porScope()` hace el resto porque los
 * dos nombran el mismo scope. Con la condición escrita dos veces, el día que
 * cambie una el panel diría 12 y la lista enseñaría 9.
 */
it('cada indicador tiene el filtro de su misma clave en la tabla', function (): void {
    $filtros = collect(app(IncidenteRecurso::class)->filtros())
        ->map(fn ($filtro): string => $filtro->clave)
        ->all();

    $claves = collect([...$this->registro->alertas(), ...$this->registro->pendientes()])
        ->map(fn (Indicador $indicador): string => $indicador->clave)
        ->all();

    expect($claves)->not->toBeEmpty();

    foreach ($claves as $clave) {
        expect($filtros)->toContain($clave);
    }
});

it('separa lo que está fuera de plazo de lo que está en plazo', function (): void {
    Incidente::factory()->fueraDePlazoAepd()->create();
    Incidente::factory()->enPlazoAepd()->create();
    Incidente::factory()->notificadoAepd()->create();

    expect(($this->valor)('fuera_de_plazo_aepd'))->toBe(1)
        ->and(($this->valor)('en_plazo_aepd'))->toBe(1);
});

it('manda el resumen de incidentes al panel', function (): void {
    Incidente::factory()->fueraDePlazoAepd()->create();

    $this->actingAs($this->usuario)
        ->get('/panel/ciclo')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('incidentes.total', 1)
            ->where('incidentes.fueraDePlazoAepd', 1)
            ->etc());
});

/**
 * No se manda a quien no puede ver el registro. Se le quita el permiso **al
 * rol** y no al usuario: `revokePermissionTo` sobre la persona no quita lo que
 * hereda, y el test pasaría por el motivo equivocado.
 */
it('no manda el resumen a quien no puede ver los incidentes', function (): void {
    Incidente::factory()->create();

    $this->usuario->roles->first()?->revokePermissionTo('incidentes.ver');

    $this->actingAs($this->usuario->fresh())
        ->get('/panel/ciclo')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('incidentes', null)->etc());
});
