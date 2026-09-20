<?php

declare(strict_types=1);

use App\Domain\Persona\DesignarRol;
use App\Domain\Persona\Enums\RolEns;
use App\Domain\Persona\Enums\TipoPasoPersona;
use App\Domain\Persona\GuardarPasos;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\AcuerdoConfidencialidad;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\RegistrarAsistencia;
use App\Domain\Persona\RegistroPersonas;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\PersonaRecurso;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| El registro de personas: sus cifras y su cobertura del 5.3
|--------------------------------------------------------------------------
|
| **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y
| eso es lo que garantiza que pulsar la cifra enseñe exactamente esa cifra. Con
| la condición escrita dos veces, el día que cambie una el panel dirá 12 y la
| lista enseñará 9 — y a partir de ahí nadie se fía del panel.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->registro = app(RegistroPersonas::class);

    $this->valor = function (string $clave): int {
        $indicador = collect([...$this->registro->alertas(), ...$this->registro->pendientes()])
            ->first(fn (Indicador $uno): bool => $uno->clave === $clave);

        expect($indicador)->not->toBeNull("No existe el indicador `{$clave}`.");

        return $indicador->valor;
    };
});

/**
 * El test que impide que el panel y la tabla discrepen: recorre los cuatro
 * indicadores y comprueba que cada uno tiene un filtro con **su misma clave**.
 * `Filtro::porScope()` hace el resto, porque los dos nombran el mismo scope.
 */
it('cada indicador tiene el filtro de su misma clave en la tabla', function (): void {
    $filtros = collect(app(PersonaRecurso::class)->filtros())
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

it('cuenta las activas y deja fuera a quien se fue', function (): void {
    Persona::factory()->count(2)->create();
    Persona::factory()->deBaja()->create();

    expect(($this->valor)('activas'))->toBe(2);
});

it('cuenta como sin formación a la activa sin asistencias', function (): void {
    $formada = Persona::factory()->create();
    Persona::factory()->create();

    app(RegistrarAsistencia::class)(AccionFormativa::factory()->create(), [$formada->id => true]);

    expect(($this->valor)('sin_formacion'))->toBe(1);
});

it('cuenta como sin acuerdo a la activa sin papel firmado', function (): void {
    $conAcuerdo = Persona::factory()->create();
    Persona::factory()->create();

    AcuerdoConfidencialidad::factory()->create([
        'persona_id' => $conAcuerdo->id,
        'organizacion_id' => $conAcuerdo->organizacion_id,
    ]);

    expect(($this->valor)('sin_acuerdo'))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| La cobertura de la cláusula 5.3
|--------------------------------------------------------------------------
|
| Se cuenta sobre la pareja (sistema, rol) y no sobre personas, que es otro
| denominador. Es la cifra que hasta este módulo iba impresa como limitación en
| el PDF de la Declaración de Aplicabilidad del ENS.
|
*/

it('cuenta los cinco roles exigibles por sistema', function (): void {
    Sistema::factory()->count(2)->create();

    $cobertura = $this->registro->cobertura();

    expect($cobertura['exigibles'])->toBe(10)
        ->and($cobertura['designados'])->toBe(0)
        ->and($cobertura['faltan'])->toHaveCount(10);
});

it('descuenta un rol en cuanto alguien lo tiene vigente', function (): void {
    $sistema = Sistema::factory()->create();

    app(DesignarRol::class)(
        Persona::factory()->create(),
        $sistema,
        RolEns::ResponsableSeguridad,
    );

    $cobertura = $this->registro->cobertura();

    expect($cobertura['designados'])->toBe(1)
        ->and($cobertura['exigibles'])->toBe(5)
        ->and($cobertura['faltan'])->toHaveCount(4);
});

/*
|--------------------------------------------------------------------------
| En el panel
|--------------------------------------------------------------------------
*/

it('manda el resumen de personas al panel', function (): void {
    $sistema = Sistema::factory()->create();
    $persona = Persona::factory()->create();

    app(DesignarRol::class)($persona, $sistema, RolEns::ResponsableSeguridad);

    $this->actingAs($this->usuario)
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->where('personas.total', 1)
            ->where('personas.activas', 1)
            ->where('personas.rolesDesignados', 1)
            ->where('personas.rolesExigibles', 5)
            ->etc());
});

/**
 * **No se manda a quien no tiene `personas.ver`.** Conectar dos módulos abre una
 * puerta lateral al registro del otro sin que nadie la decida; el frontend
 * decide qué pinta y nunca qué autoriza.
 *
 * Se le quita el permiso **al rol** y no al usuario: `revokePermissionTo` sobre
 * la persona no quita lo que hereda, y el test pasaría por el motivo equivocado.
 */
it('no manda el resumen a quien no puede ver el registro', function (): void {
    Persona::factory()->create();

    $this->usuario->roles->first()?->revokePermissionTo('personas.ver');

    $this->actingAs($this->usuario->fresh())
        ->get('/panel')
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('personas', null)->etc());
});

/** El rojo del panel es uno solo, y es la salida sin cerrar. */
it('cuenta la salida sin cerrar como alerta del panel', function (): void {
    $persona = Persona::factory()->create();

    app(GuardarPasos::class)($persona, TipoPasoPersona::Baja, [['titulo' => 'Revocar accesos']]);

    $persona->update(['fecha_baja' => Carbon::today()]);

    expect($this->registro->paraElPanel()->bajaSinCerrar)->toBe(1);
});
