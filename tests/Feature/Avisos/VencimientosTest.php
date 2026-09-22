<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Aviso\Fuente;
use App\Domain\Aviso\Notifications\VencimientosDelDia;
use App\Domain\Aviso\ResumenVencimientos;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\ResumenDocumental;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Persona;
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

    expect($vencimientos->pasadosDe(Fuente::Evidencia))->toHaveCount(1)
        ->and($vencimientos->proximosDe(Fuente::Evidencia))->toHaveCount(1)
        ->and($vencimientos->pasadosDe(Fuente::Evidencia)[0]->titulo)->toBe('Certificado vencido')
        ->and($vencimientos->pasadosDe(Fuente::Evidencia)[0]->cuando('caduca', 'caducó'))->toBe('caducó hace 4 días')
        ->and($vencimientos->proximosDe(Fuente::Evidencia)[0]->cuando('caduca', 'caducó'))->toBe('caduca en 10 días');
});

it('la que caduca hoy cuenta como por caducar, no como caducada', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()]);

    $vencimientos = app(ResumenVencimientos::class)();

    expect($vencimientos->pasadosDe(Fuente::Evidencia))->toBeEmpty()
        ->and($vencimientos->proximosDe(Fuente::Evidencia))->toHaveCount(1)
        ->and($vencimientos->proximosDe(Fuente::Evidencia)[0]->cuando('caduca', 'caducó'))->toBe('caduca hoy');
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

    expect($vencimientos->pasadosDe(Fuente::Evidencia))->toHaveCount(1)
        ->and($vencimientos->pasadosDe(Fuente::Tarea))->toHaveCount(1)
        ->and($vencimientos->proximosDe(Fuente::Tarea))->toHaveCount(1)
        ->and($vencimientos->pasados())->toBe(2)
        ->and($vencimientos->pasadosDe(Fuente::Tarea)[0]->cuando())->toBe('venció hace 5 días');
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

    expect($vencimientos->pasadosDe(Fuente::Tarea))->toHaveCount(1)
        ->and($vencimientos->pasadosDe(Fuente::Tarea)[0]->titulo)->toBe('Tarea propia');
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

/*
|--------------------------------------------------------------------------
| La tercera fuente: la revisión documental (§ 4.5)
|--------------------------------------------------------------------------
|
| Lo que vence no es el documento sino **la revisión de su versión aprobada**: la
| fecha se calcula al firmar y se congela en la versión. Un documento sin
| periodicidad no vence nunca, y es una respuesta legítima.
|
*/

/** Una política aprobada cuya próxima revisión cae donde se diga. */
function politicaAprobadaCon(?Carbon $proximaRevision, string $titulo = 'Política de Seguridad'): Documento
{
    // Código único: `politica()` trae uno fijo, que es lo cómodo cuando sólo hay
    // una, y aquí se crean varias en el mismo test.
    $documento = Documento::factory()->politica()->create([
        'codigo' => 'POL-'.fake()->unique()->numerify('####'),
        'titulo' => $titulo,
    ]);

    DocumentoVersion::factory()
        ->delDocumento($documento->id)
        ->emitida()
        ->create(['fecha_proxima_revision' => $proximaRevision]);

    return $documento;
}

it('separa la revisión vencida de la que toca pronto', function (): void {
    politicaAprobadaCon(Carbon::today()->subDays(10), 'Política vieja');
    politicaAprobadaCon(Carbon::today()->addDays(5), 'Norma de contraseñas');

    // Fuera de la ventana y sin fecha: ninguna de las dos es asunto del aviso.
    politicaAprobadaCon(Carbon::today()->addMonths(6));
    politicaAprobadaCon(null);

    $vencimientos = app(ResumenVencimientos::class)();

    expect($vencimientos->pasadosDe(Fuente::Documento))->toHaveCount(1)
        ->and($vencimientos->proximosDe(Fuente::Documento))->toHaveCount(1)
        ->and($vencimientos->pasadosDe(Fuente::Documento)[0]->titulo)->toContain('Política vieja')
        ->and($vencimientos->pasadosDe(Fuente::Documento)[0]->cuando('toca revisar', 'tocaba revisar'))
        ->toBe('tocaba revisar hace 10 días');
});

/**
 * Un borrador no vence: lo que caduca es la revisión de lo que se **firmó**. Si
 * contara el borrador, el aviso saltaría por un documento que nadie ha entregado.
 */
it('un documento sin versión aprobada no vence', function (): void {
    $documento = Documento::factory()->politica()->create();

    DocumentoVersion::factory()
        ->delDocumento($documento->id)
        ->enRevision()
        ->create(['fecha_proxima_revision' => Carbon::today()->subDays(30)]);

    $vencimientos = app(ResumenVencimientos::class)();

    expect($vencimientos->pasadosDe(Fuente::Documento))->toBeEmpty()
        ->and($vencimientos->proximosDe(Fuente::Documento))->toBeEmpty();
});

it('el correo nombra la revisión documental en su propio bloque', function (): void {
    politicaAprobadaCon(Carbon::today()->subDays(3), 'Política de Seguridad');

    $correo = (new VencimientosDelDia('Organización', app(ResumenVencimientos::class)()))
        ->toMail($this->responsable);

    $texto = implode(' ', array_map(
        static fn (mixed $linea): string => is_string($linea) ? $linea : '',
        $correo->introLines,
    ));

    expect($texto)->toContain('Documentos sin revisar a tiempo')
        // «Tocaba revisar» y no «venció»: el documento sigue aprobado y en vigor.
        ->toContain('tocaba revisar');
});

it('el documento de otra organización tampoco cruza', function (): void {
    $ajena = Organizacion::factory()->create();

    app(ContextoOrganizacion::class)->paraOrganizacion($ajena, function (): void {
        politicaAprobadaCon(Carbon::today()->subDays(5), 'Política ajena');
    });

    comoOrganizacion($this->organizacion);

    expect(app(ResumenVencimientos::class)()->pasadosDe(Fuente::Documento))->toBeEmpty();
});

/**
 * El indicador de la tabla y el aviso cuentan con el mismo scope, que es lo que
 * impide que el correo diga 12 y la pantalla enseñe 9.
 */
it('el indicador de la tabla cuenta lo mismo que el aviso', function (): void {
    politicaAprobadaCon(Carbon::today()->subDays(2));
    politicaAprobadaCon(Carbon::today()->addDays(40));

    $alertas = collect(app(ResumenDocumental::class)->alertas())->keyBy('clave');

    expect($alertas['revision_vencida']->valor)
        ->toBe(count(app(ResumenVencimientos::class)()->pasadosDe(Fuente::Documento)));
});

/*
|--------------------------------------------------------------------------
| El recuento no se olvida de ninguna fuente
|--------------------------------------------------------------------------
|
| `Vencimientos` tenía seis propiedades fijas y `pasados()` las sumaba a mano.
| Con siete fuentes serían catorce, y olvidar una **no rompe nada**: el asunto
| del correo diría «3 cosas pasadas de fecha» habiendo 9, que es el fallo más
| caro del módulo porque no falla.
|
| Este test lo cierra recorriendo `Fuente::cases()`. Una fuente nueva sin sembrar
| pone la suite en rojo con su nombre.
|
*/

it('el recuento de lo pasado incluye todas las fuentes', function (): void {
    foreach (Fuente::cases() as $fuente) {
        sembrarPasadoDe($fuente);
    }

    $vencimientos = app(ResumenVencimientos::class)();

    foreach (Fuente::cases() as $fuente) {
        expect($vencimientos->pasadosDe($fuente))->not->toBeEmpty(
            "La fuente `{$fuente->value}` no produjo nada pasado de fecha: amplía `sembrarPasadoDe()`.",
        );
    }

    expect($vencimientos->pasados())->toBe(count(Fuente::cases()));
});

it('el correo abre un bloque por cada fuente que trae algo', function (): void {
    foreach (Fuente::cases() as $fuente) {
        sembrarPasadoDe($fuente);
    }

    $correo = (new VencimientosDelDia('Tal S.L.', app(ResumenVencimientos::class)()))
        ->toMail(new stdClass);

    $texto = implode(' ', $correo->introLines);

    foreach (Fuente::cases() as $fuente) {
        expect($texto)->toContain($fuente->tituloPasados());
    }
});

/** Una cosa pasada de fecha de esta fuente, y exactamente una. */
function sembrarPasadoDe(Fuente $fuente): void
{
    $fecha = Carbon::today()->subDays(20);

    match ($fuente) {
        Fuente::Tarea => Tarea::factory()->create(['fecha_limite' => $fecha]),

        Fuente::Evidencia => Evidencia::factory()->create(['fecha_caducidad' => $fecha]),

        Fuente::Documento => politicaAprobadaCon($fecha, 'Política pasada'),

        Fuente::Formacion => (function () use ($fecha): void {
            $persona = Persona::factory()->create();
            $accion = AccionFormativa::factory()->create([
                'fecha' => $fecha->copy()->subMonths(Persona::MESES_DE_VIGENCIA_FORMATIVA),
            ]);

            $persona->asistencias()->create(['accion_formativa_id' => $accion->id, 'asistio' => true]);
        })(),

        Fuente::Indicador => Indicador::factory()->create(),

        Fuente::Implantacion => Implantacion::factory()->create([
            'estado' => EstadoImplantacion::NoIniciado->value,
            'fecha_objetivo' => $fecha,
        ]),

        Fuente::Obligacion => Compromiso::factory()->cada(12)->create([
            'computa_desde' => $fecha->copy()->subYear(),
        ]),
    };
}
