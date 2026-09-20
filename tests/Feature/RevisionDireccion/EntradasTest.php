<?php

declare(strict_types=1);

use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\RevisionDireccion\AprobarRevision;
use App\Domain\RevisionDireccion\EntradasRevision;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\RevisionDireccion\VincularDecision;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Las siete entradas obligatorias de la cláusula 9.3.2
|--------------------------------------------------------------------------
|
| Es lo que paga el módulo, y el motivo por el que el § 4.15 llevaba bloqueado
| desde el principio: la norma cierra la lista y hasta el § 6.2 y el § 10.1 dos de
| las siete no salían de ninguna parte.
|
| Lo que se fija aquí es que **las siete se recogen** —un cero es una entrada
| recogida y no una entrada que falte— y que las dos que llegaron con este tramo
| están de verdad conectadas.
|
*/

beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->entradas = app(EntradasRevision::class);
});

it('recoge las siete entradas incluso con el registro vacío', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();

    $recogidas = $this->entradas->para($revision);

    /*
     * Un cero es una entrada recogida. Una organización puede celebrar su primera
     * revisión sin auditorías, sin no conformidades y sin objetivos, y el acta lo
     * dirá; exigir contenido convertiría la primera revisión en imposible, que es
     * justo cuando más falta hace.
     */
    expect($recogidas)->toHaveKeys([
        'accionesPrevias',
        'contexto',
        'partesInteresadas',
        'desempeno',
        'riesgos',
        'mejoras',
    ]);

    expect($recogidas['accionesPrevias']['revision'])->toBeNull()
        ->and($recogidas['desempeno']['noConformidades']['abiertas'])->toBe(0)
        ->and($recogidas['mejoras']['total'])->toBe(0);
});

/*
 * La entrada d) que llegó con el § 6.2. Sin el módulo de objetivos, esta fila del
 * acta se habría quedado vacía para siempre.
 */
it('recoge el cumplimiento de los objetivos de seguridad', function (): void {
    Objetivo::factory()->enEstado(EstadoObjetivo::Aprobado, $this->usuario->id)
        ->create(['codigo' => 'OBJ-2026-01', 'titulo' => 'Llegar al 80 % del Anexo II']);

    $revision = RevisionDireccion::factory()->enCurso()->create();

    $objetivos = $this->entradas->para($revision)['desempeno']['objetivos'];

    expect($objetivos['total'])->toBe(1)
        ->and($objetivos['vivos'])->toBe(1)
        ->and($objetivos['detalle'][0]['codigo'])->toBe('OBJ-2026-01')
        // Con su evaluación al lado: es lo que la dirección mira para decidir.
        ->and($objetivos['detalle'][0])->toHaveKey('avance');
});

/*
 * La entrada g), que llegó con el § 10.1. Antes sólo existían dentro de una
 * auditoría, así que esta fila se habría quedado en «las que algún auditor
 * escribió».
 */
it('recoge las oportunidades de mejora abiertas', function (): void {
    Mejora::factory()->create(['codigo' => 'OM-2026-01', 'titulo' => 'Automatizar el inventario']);
    Mejora::factory()->enEstado(EstadoMejora::Descartada)->create();

    $revision = RevisionDireccion::factory()->enCurso()->create();

    $mejoras = $this->entradas->para($revision)['mejoras'];

    expect($mejoras['total'])->toBe(2)
        ->and($mejoras['abiertas'])->toBe(1)
        ->and($mejoras['detalle'])->toHaveCount(1)
        ->and($mejoras['detalle'][0]['codigo'])->toBe('OM-2026-01');
});

/*
 * La entrada a), y la que hace que la serie de actas signifique algo: sin ella
 * cada revisión empieza de cero y las decisiones de la anterior no se comprueban
 * nunca.
 */
it('la entrada a) son las decisiones de la revisión anterior', function (): void {
    $anterior = RevisionDireccion::factory()
        ->delPeriodo(Carbon::today()->subYear(), Carbon::today()->subMonths(6))
        ->enCurso()
        ->create(['codigo' => 'RD-2025-01']);

    $tarea = Tarea::factory()->create(['titulo' => 'Contratar la formación anual']);
    app(VincularDecision::class)->vincular($anterior, $tarea, $this->usuario);

    app(AprobarRevision::class)($anterior, $this->usuario);

    $actual = RevisionDireccion::factory()
        ->delPeriodo(Carbon::today()->subMonths(5), Carbon::today())
        ->enCurso()
        ->create(['codigo' => 'RD-2026-01']);

    $previas = $this->entradas->para($actual)['accionesPrevias'];

    expect($previas['revision']['codigo'])->toBe('RD-2025-01')
        ->and($previas['acciones'])->toHaveCount(1)
        ->and($previas['acciones'][0]['titulo'])->toBe('Contratar la formación anual')
        ->and($previas['abiertas'])->toBe(1);
});

it('la revisión anterior se busca por fecha de celebración y no por cuándo se tecleó', function (): void {
    // La de 2026 se registra ANTES que la de 2025, que es lo que pasa cuando
    // alguien mete el histórico en la herramienta después de empezar a usarla.
    $nueva = RevisionDireccion::factory()
        ->delPeriodo(Carbon::today()->subMonths(5), Carbon::today())
        ->enCurso()
        ->create(['codigo' => 'RD-2026-01']);

    $vieja = RevisionDireccion::factory()
        ->delPeriodo(Carbon::today()->subYear(), Carbon::today()->subMonths(6))
        ->aprobada($this->usuario->id)
        ->create(['codigo' => 'RD-2025-01']);

    expect($nueva->anterior()?->codigo)->toBe($vieja->codigo)
        // Y al revés no: la vieja no tiene ninguna anterior.
        ->and($vieja->anterior())->toBeNull();
});

it('las decisiones se pueden registrar sobre un acta ya aprobada', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();
    app(AprobarRevision::class)($revision, $this->usuario);

    /*
     * Es justo donde uno espera un error: escribir en `revision_tarea` no pasa
     * por el trigger de inmutabilidad —que blinda el acta, no lo que cuelga de
     * ella— y es lo correcto, porque una decisión se ejecuta en las semanas
     * siguientes. Mismo caso que la acción correctiva de una auditoría cerrada.
     */
    $this->actingAs($this->usuario)
        ->post("/revision-direccion/{$revision->id}/decisiones", [
            'titulo' => 'Revisar el contrato del proveedor de copias',
            'prioridad' => 'media',
        ])
        ->assertRedirect();

    expect($revision->refresh()->tareas()->count())->toBe(1);
});

it('la decisión nace con el origen «revisión por la dirección»', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();

    $this->actingAs($this->usuario)
        ->post("/revision-direccion/{$revision->id}/decisiones", [
            'titulo' => 'Aumentar el presupuesto de formación',
            'prioridad' => 'alta',
            // Se manda a propósito y tiene que ignorarse.
            'origen' => 'propia',
        ])
        ->assertRedirect();

    $tarea = Tarea::query()->where('titulo', 'Aumentar el presupuesto de formación')->firstOrFail();

    // El origen que llevaba desde la primera migración declarado y sin ofrecerse,
    // esperando justamente a este módulo.
    expect($tarea->origen)->toBe(OrigenTarea::RevisionDireccion)
        ->and($tarea->origen->disponible())->toBeTrue();
});

it('la ficha enseña las entradas en vivo mientras el acta no está firmada', function (): void {
    $revision = RevisionDireccion::factory()->enCurso()->create();

    $this->actingAs($this->usuario)
        ->get("/revision-direccion/{$revision->id}")
        ->assertInertia(fn ($pagina) => $pagina
            ->where('congeladas', false)
            ->has('entradas.desempeno'));
});
