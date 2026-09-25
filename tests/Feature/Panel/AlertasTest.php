<?php

declare(strict_types=1);

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Panel\AlertasDelPanel;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Http\Resources\Panel\Indicador;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El punto rojo de las pestañas del panel
|--------------------------------------------------------------------------
|
| Partir el panel en tres vistas tiene un riesgo y es sólo uno: que una pestaña
| esconda un incumplimiento detrás de un clic que nadie da. `AlertasDelPanel`
| cruza los once registros y cuenta lo rojo, y de ahí sale el punto de cada
| pestaña. Sin él, el reparto sería un sitio donde esconder cosas.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->alertas = app(AlertasDelPanel::class);
});

it('recoge el rojo de varios módulos', function (): void {
    Evidencia::factory()->create(['fecha_caducidad' => Carbon::today()->subDay()]);
    Incidente::factory()->fueraDePlazoAepd()->create();

    $claves = array_map(
        static fn (Indicador $alerta): string => $alerta->clave,
        ($this->alertas)($this->usuario),
    );

    expect($claves)->toContain('caducadas')
        ->and($claves)->toContain('fuera_de_plazo_aepd');
});

/**
 * **Sólo el rojo.** `alertas()` de cada registro devuelve también cosas que
 * piden atención sin estar incumplidas —`bloqueadas` en tareas,
 * `con_no_conformidades` en auditorías— y meterlas aquí devolvería la fila de
 * cifras que hay que leerse entera.
 */
it('deja fuera lo que pide atención sin estar incumplido', function (): void {
    // Una bloqueada y una vencida: la primera no es rojo —está aparcada, no
    // incumplida— y la segunda sí. Sólo tiene que subir la segunda.
    Tarea::factory()->enEstado(EstadoTarea::Bloqueada)->create();
    Tarea::factory()->create(['fecha_limite' => Carbon::today()->subWeek()]);

    $alertas = ($this->alertas)($this->usuario);

    expect($alertas)->not->toBeEmpty();

    foreach ($alertas as $alerta) {
        expect($alerta->tono)->toBe(AlertasDelPanel::TONO_ROJO)
            ->and($alerta->clave)->not->toBe('bloqueadas');
    }
});

/** Una tarjeta gastada en decir «cero» enseña a ignorar la tira entera. */
it('no pinta lo que está a cero', function (): void {
    expect(($this->alertas)($this->usuario))->toBe([]);
});

/**
 * Misma regla que ya tenía cada tarjeta por separado: conectar dos módulos abre
 * una puerta lateral al registro del otro si nadie lo decide.
 *
 * Se le quita el permiso **al rol** y no al usuario: `revokePermissionTo` sobre
 * la persona no quita lo que hereda, y el test pasaría por el motivo equivocado.
 */
it('no cuenta el rojo de un módulo que quien mira no puede ver', function (): void {
    Incidente::factory()->fueraDePlazoAepd()->create();

    $this->usuario->roles->first()?->revokePermissionTo('incidentes.ver');

    $claves = array_map(
        static fn (Indicador $alerta): string => $alerta->clave,
        ($this->alertas)($this->usuario->fresh()),
    );

    expect($claves)->not->toContain('fuera_de_plazo_aepd');
});

it('el auditor ve la tira, porque tiene todos los .ver', function (): void {
    Incidente::factory()->fueraDePlazoAepd()->create();

    $auditor = usuarioCon(Rol::Auditor);

    expect(($this->alertas)($auditor))->not->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Las tres vistas
|--------------------------------------------------------------------------
*/

it('pinta el conmutador con las tres vistas en las tres', function (): void {
    foreach (['/panel', '/panel/ciclo', '/panel/organizacion'] as $ruta) {
        $this->actingAs($this->usuario)
            ->get($ruta)
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('vistas', 3)->etc());
    }
});

/**
 * **La lista de alertas no viaja al cliente**, y es la diferencia con el primer
 * intento: lo que el conmutador necesita es saber si las hay, no cuáles. El
 * detalle vive dentro, en la tarjeta del módulo que lo produce.
 */
it('no manda las alertas en sí, sólo su recuento por pestaña', function (): void {
    Incidente::factory()->fueraDePlazoAepd()->create();

    $this->actingAs($this->usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->missing('alertas')->etc());
});

/**
 * El punto dice dónde mirar sin obligar a abrir las tres. Un incidente fuera de
 * plazo cuelga de «El ciclo».
 */
it('marca con punto sólo la pestaña que contiene el rojo', function (): void {
    Incidente::factory()->fueraDePlazoAepd()->create();

    $this->actingAs($this->usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('vistas.0.clave', 'cumplimiento')
            ->where('vistas.0.alertas', 0)
            ->where('vistas.1.clave', 'ciclo')
            ->where('vistas.1.alertas', 1)
            ->where('vistas.2.clave', 'organizacion')
            ->where('vistas.2.alertas', 0)
            ->etc());
});

/**
 * **El test que descubre solo.**
 *
 * `AlertasDelPanel::FUENTES` es una lista literal, como `Rol::permisos()`, y
 * tiene el mismo riesgo: olvidar un módulo nuevo no rompe nada — su rojo
 * sencillamente deja de verse, que es el fallo silencioso que la tira existe
 * para cerrar. Así que el test no enumera módulos: **recorre `app/Domain/`
 * buscando registros con `alertas()`** y exige que estén declarados.
 *
 * Lleva la lista de las excepciones declaradas y exige que quien deje de serlo
 * salga de ella, igual que `RlsDeclaradaTest` con `users` y las tres de spatie.
 */
it('ningún registro con alertas se queda fuera de la tira', function (): void {
    /*
     * `RegistroContexto` no entra: sus dos `alertas*()` devuelven lista vacía a
     * propósito —una debilidad apuntada en un DAFO no va mal, es algo que la
     * organización ha sabido ver— y no tienen la firma `alertas()`.
     */
    $declarados = array_map(
        static fn (array $fuente): string => $fuente[1],
        AlertasDelPanel::FUENTES,
    );

    $conAlertas = [];

    foreach (glob(app_path('Domain/*/{Registro,Resumen}*.php'), GLOB_BRACE) ?: [] as $fichero) {
        $clase = 'App\\Domain\\'
            .basename(dirname($fichero)).'\\'
            .basename($fichero, '.php');

        if (! class_exists($clase) || ! method_exists($clase, 'alertas')) {
            continue;
        }

        $conAlertas[] = $clase;
    }

    // Si el `glob` deja de encontrar nada, el test pasaría sin comprobar nada:
    // es el mismo fallo que ya mordió a `FactoriesSinOrganizacionTest`, cuyo
    // `glob` miraba sólo el primer nivel y daba por cubierto lo que no cubría.
    expect($conAlertas)->not->toBeEmpty();

    $olvidados = array_values(array_diff($conAlertas, $declarados));

    expect($olvidados)->toBe(
        [],
        'Estos registros declaran alertas() y no están en AlertasDelPanel::FUENTES, '
        .'así que su rojo no llegaría al panel: '.implode(', ', $olvidados),
    );
});

/** Y al revés: una fuente declarada que ya no tenga alertas es una llamada muerta. */
it('no declara fuentes que no tengan alertas', function (): void {
    foreach (AlertasDelPanel::FUENTES as [, $registro]) {
        expect(method_exists($registro, 'alertas'))->toBeTrue("{$registro} está declarado y no tiene alertas().");
    }
});

/**
 * **El segundo eslabón, que el anterior no cubría.** Estar en `FUENTES` hace que
 * el rojo se cuente; que caiga en una pestaña lo decide `VISTAS`, y obligaciones,
 * proveedores y vulnerabilidades entraron en lo primero sin entrar en lo
 * segundo. Se contaban y no marcaban ninguna pestaña.
 *
 * El test no enumera módulos: pregunta a cada fuente qué `base` llevan sus
 * alertas —la lleva aunque valgan cero— y exige que esté en una vista y en una
 * sola.
 */
it('toda alerta de toda fuente cae en una pestaña y en una sola', function (): void {
    $bases = [];

    foreach (AlertasDelPanel::FUENTES as [, $registro]) {
        foreach (app($registro)->alertas() as $alerta) {
            $bases[$alerta->base] = $registro;
        }
    }

    expect($bases)->not->toBeEmpty();

    foreach ($bases as $base => $registro) {
        $vistas = array_keys(array_filter(
            AlertasDelPanel::VISTAS,
            static fn (array $modulos): bool => in_array($base, $modulos, true),
        ));

        expect($vistas)->toHaveCount(
            1,
            "La alerta con base {$base} de {$registro} cae en ".count($vistas).' pestañas de AlertasDelPanel::VISTAS.',
        );
    }
});

it('marca «El ciclo» con una vulnerabilidad fuera de plazo', function (): void {
    Vulnerabilidad::factory()->create([
        'severidad' => 'critica',
        'fecha_deteccion' => Carbon::today()->subDays(30),
        'fecha_limite' => Carbon::today()->subDays(23),
    ]);

    $this->actingAs($this->usuario)
        ->get('/panel/ciclo')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('vistas.1.clave', 'ciclo')
            ->where('vistas.1.alertas', 1)
            ->where('vulnerabilidades.fueraDePlazo', 1)
            ->etc());
});

it('lleva los proveedores a «La organización»', function (): void {
    $this->actingAs($this->usuario)
        ->get('/panel/organizacion')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('proveedores')->etc());
});
