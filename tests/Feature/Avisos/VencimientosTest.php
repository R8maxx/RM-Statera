<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Aviso\Notifications\VencimientosDelDia;
use App\Domain\Aviso\ResumenVencimientos;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * El primer aviso que da la herramienta.
 *
 * Evidencias guarda `fecha_caducidad` y `periodicidad_renovacion` desde el
 * principio y el panel cuenta las caducadas, pero nadie se enteraba si no entraba
 * a mirar. Una evidencia caducada no prueba nada: el requisito que sostenía se
 * queda sin prueba, y eso es un hallazgo que se encuentra solo.
 */
beforeEach(function (): void {
    Notification::fake();

    $this->organizacion = comoOrganizacion();
    $this->responsable = usuarioCon(Rol::ResponsableSeguridad);
    $this->tecnico = usuarioCon(Rol::Tecnico);
});

it('reparte lo caducado de lo que está por caducar', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDays(4), 'titulo' => 'Certificado vencido']);
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->addDays(10), 'titulo' => 'Captura del IdP']);

    // Fuera de la ventana: ni caducada ni inminente.
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->addMonths(6)]);

    // Sin caducidad: no vence nunca, no es asunto de este aviso.
    Evidencia::factory()->create(['fecha_caducidad' => null]);

    $vencimientos = app(ResumenVencimientos::class)();

    expect($vencimientos->evidenciasCaducadas)->toHaveCount(1)
        ->and($vencimientos->evidenciasPorCaducar)->toHaveCount(1)
        ->and($vencimientos->evidenciasCaducadas[0]->titulo)->toBe('Certificado vencido')
        ->and($vencimientos->evidenciasCaducadas[0]->cuando('caduca', 'caducó'))->toBe('caducó hace 4 días')
        ->and($vencimientos->evidenciasPorCaducar[0]->cuando('caduca', 'caducó'))->toBe('caduca en 10 días');
});

it('la que caduca hoy cuenta como por caducar, no como caducada', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()]);

    $vencimientos = app(ResumenVencimientos::class)();

    expect($vencimientos->evidenciasCaducadas)->toBeEmpty()
        ->and($vencimientos->evidenciasPorCaducar)->toHaveCount(1)
        ->and($vencimientos->evidenciasPorCaducar[0]->cuando('caduca', 'caducó'))->toBe('caduca hoy');
});

it('avisa al responsable de seguridad y no al técnico', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    $this->artisan('avisos:enviar')->assertSuccessful();

    Notification::assertSentTo($this->responsable, VencimientosDelDia::class);
    Notification::assertNotSentTo($this->tecnico, VencimientosDelDia::class);
});

it('sin nada que vencer no manda nada', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => null]);
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->addYear()]);

    $this->artisan('avisos:enviar')->assertSuccessful();

    Notification::assertNothingSent();
});

/**
 * La prioridad 2 de cobertura del proyecto, y aquí con un agravante: un aviso
 * cruzado no se queda en la pantalla de quien mira, sale por correo fuera de la
 * herramienta y ya no se puede recoger.
 */
it('la evidencia de una organización no aparece en el aviso de otra', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    Evidencia::factory()->create([
        'fecha_caducidad' => Carbon::today()->subDay(),
        'titulo' => 'Contrato de la otra organización',
    ]);
    $suyo = usuarioCon(Rol::ResponsableSeguridad, $ajena);

    comoOrganizacion($this->organizacion);
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay(), 'titulo' => 'Contrato propio']);

    $this->artisan('avisos:enviar')->assertSuccessful();

    Notification::assertSentTo(
        $this->responsable,
        fn (VencimientosDelDia $aviso): bool => str_contains(
            (string) json_encode($aviso->toMail($this->responsable)->toArray()),
            'Contrato propio',
        ) && ! str_contains(
            (string) json_encode($aviso->toMail($this->responsable)->toArray()),
            'Contrato de la otra organización',
        ),
    );

    Notification::assertSentTo($suyo, VencimientosDelDia::class);
});

/**
 * Un comando programado no tiene petición ni usuario, así que sin contexto de
 * organización el scope no devuelve nada y RLS deniega por defecto: el comando no
 * revienta, sencillamente no ve nada. Y un aviso que no salta es idéntico a no
 * tener nada que avisar.
 */
it('ve las evidencias aunque arranque sin contexto de organización', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    app(ContextoOrganizacion::class)->olvidar();

    $this->artisan('avisos:enviar')->assertSuccessful();

    Notification::assertSentTo($this->responsable, VencimientosDelDia::class);
});

it('el resumen no toca la organización activa al terminar', function (): void {
    $contexto = app(ContextoOrganizacion::class);

    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    $this->artisan('avisos:enviar')->assertSuccessful();

    expect($contexto->id())->toBe($this->organizacion->id);
});

it('--dry-run cuenta pero no envía', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    $this->artisan('avisos:enviar', ['--dry-run' => true])->assertSuccessful();

    Notification::assertNothingSent();
});

/**
 * Las tareas entran en el mismo resumen, pero en su propia lista: una evidencia
 * caducada es una prueba que ya no prueba y una tarea vencida es trabajo que no
 * se hizo. Se arreglan de formas distintas.
 */
it('cuenta las tareas vencidas aparte de las evidencias caducadas', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDays(2), 'titulo' => 'Certificado']);
    Tarea::factory()->vencida(5)->create(['titulo' => 'Revisar la política']);
    Tarea::factory()->paraElDia(Carbon::today()->addDays(9))->create(['titulo' => 'Contratar la revisión']);

    $vencimientos = app(ResumenVencimientos::class)();

    expect($vencimientos->evidenciasCaducadas)->toHaveCount(1)
        ->and($vencimientos->tareasVencidas)->toHaveCount(1)
        ->and($vencimientos->tareasPorVencer)->toHaveCount(1)
        ->and($vencimientos->pasados())->toBe(2)
        ->and($vencimientos->tareasVencidas[0]->cuando())->toBe('venció hace 5 días');
});

/**
 * Una tarea cerrada no vence: está cerrada, con su motivo en el histórico. Mismo
 * criterio con el que el cumplimiento se cuenta sobre lo exigible y el inventario
 * sobre lo vigente.
 */
it('una tarea cerrada con el plazo pasado no se avisa', function (): void {
    Tarea::factory()
        ->enEstado(EstadoTarea::Hecha)
        ->paraElDia(Carbon::today()->subMonth())
        ->create();

    Tarea::factory()
        ->enEstado(EstadoTarea::Descartada)
        ->paraElDia(Carbon::today()->subMonth())
        ->create();

    expect(app(ResumenVencimientos::class)()->hayAlgo())->toBeFalse();
});

it('una tarea sin plazo no vence nunca', function (): void {
    Tarea::factory()->count(3)->create(['fecha_limite' => null]);

    expect(app(ResumenVencimientos::class)()->hayAlgo())->toBeFalse();
});

it('el correo nombra las dos cosas por separado', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay(), 'titulo' => 'Captura del IdP']);
    Tarea::factory()->vencida()->create(['titulo' => 'Redactar el procedimiento']);

    $aviso = new VencimientosDelDia('Organización de pruebas', app(ResumenVencimientos::class)());

    $texto = (string) json_encode($aviso->toMail($this->responsable)->toArray());

    expect($texto)
        ->toContain('Evidencias caducadas')
        ->toContain('Captura del IdP')
        ->toContain('Tareas vencidas')
        ->toContain('Redactar el procedimiento');
});

it('la tarea de otra organización tampoco cruza', function (): void {
    $ajena = Organizacion::factory()->create();

    comoOrganizacion($ajena);
    Tarea::factory()->vencida()->create(['titulo' => 'Tarea de la otra organización']);

    comoOrganizacion($this->organizacion);
    Tarea::factory()->vencida()->create(['titulo' => 'Tarea propia']);

    $vencimientos = app(ResumenVencimientos::class)();

    expect($vencimientos->tareasVencidas)->toHaveCount(1)
        ->and($vencimientos->tareasVencidas[0]->titulo)->toBe('Tarea propia');
});

/**
 * Explicar que una evidencia caducada no prueba nada en un correo donde todo lo
 * que hay son tareas es ruido, y de los que enseñan a no leer el primer párrafo.
 */
it('la entradilla habla de lo que hay en ese correo', function (): void {
    Tarea::factory()->vencida()->create();

    $soloTareas = (string) json_encode(
        (new VencimientosDelDia('Pruebas', app(ResumenVencimientos::class)()))
            ->toMail($this->responsable)
            ->toArray(),
    );

    expect($soloTareas)
        ->toContain('Hay tareas vencidas')
        ->not->toContain('Una evidencia caducada no prueba nada');

    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);

    $lasDos = (string) json_encode(
        (new VencimientosDelDia('Pruebas', app(ResumenVencimientos::class)()))
            ->toMail($this->responsable)
            ->toArray(),
    );

    expect($lasDos)->toContain('pruebas caducadas y trabajo sin hacer');
});
