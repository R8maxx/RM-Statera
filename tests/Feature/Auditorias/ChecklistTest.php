<?php

declare(strict_types=1);

use App\Domain\Auditoria\CerrarAuditoria;
use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Auditoria\PrecargarChecklist;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\ChecklistRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;

/**
 * La checklist es el primer recurso acotado a un padre del producto, y trae un
 * eje de aislamiento que no existía: entre dos auditorías de la **misma**
 * organización no hay scope global, ni RLS, ni nada. Lo único que las separa es
 * lo que se escriba aquí.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon(Rol::ResponsableSeguridad);

    $marco = Marco::factory()->create();

    $this->montar = function (string $codigo) use ($marco): array {
        $sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();

        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => $codigo,
            'tipo' => TipoRequisito::Medida->value,
            'orden' => 1,
        ]);

        Implantacion::factory()->for($sistema)->create(['requisito_id' => $requisito->id]);

        $auditoria = Auditoria::factory()->paraSistema($sistema->id)->create();
        app(PrecargarChecklist::class)($auditoria);

        return ['auditoria' => $auditoria, 'sistema' => $sistema];
    };
});

it('la checklist de una auditoría no enseña las líneas de otra', function (): void {
    $una = ($this->montar)('op.acc.1');
    $otra = ($this->montar)('mp.if.1');

    $this->actingAs($this->usuario)
        ->get("/auditorias/{$una['auditoria']->id}/checklist")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->component('auditorias/Checklist')
            ->has('filas', 1)
            ->where('filas.0.codigo', 'op.acc.1')
        );

    $this->actingAs($this->usuario)
        ->get("/auditorias/{$otra['auditoria']->id}/checklist")
        ->assertInertia(fn (AssertableInertia $pagina) => $pagina
            ->has('filas', 1)
            ->where('filas.0.codigo', 'mp.if.1')
        );
});

/*
 * El que protege la decisión de la clave de caché. `Inertia::once()` omite la
 * prop cuando el cliente dice que ya la tiene, y el cliente la reclama por la
 * clave: si las dos checklists compartieran clave, la segunda auditoría se
 * pintaría con la definición de la primera —incluida la URL de su acción
 * masiva—, sin error y sin aviso.
 */
it('dos checklists no comparten la caché de su definición', function (): void {
    $una = ($this->montar)('op.acc.1');
    $otra = ($this->montar)('mp.if.1');

    /*
     * Se comprueba la clave y no el ida y vuelta por HTTP, a propósito: lo que
     * hay que fijar es **la decisión**, y el comportamiento que cuelga de ella es
     * de Inertia y no nuestro. El cliente reclama una prop `once` cuando la clave
     * coincide y **copia el valor viejo**; con la clave compartida, la segunda
     * checklist se pintaría con la definición de la primera —incluida la URL de
     * su acción masiva y las opciones de sus filtros—, sin error y sin aviso.
     *
     * Por eso el sufijo va en la clave de caché y no en `clave()`: ver el test
     * siguiente.
     */
    $clave = function (Auditoria $auditoria): ?string {
        $controlador = new class
        {
            use RespondeConRecurso;

            /** @return array<string, mixed> */
            public function props(Auditoria $auditoria): array
            {
                return $this->tabla(
                    new ChecklistRecurso($auditoria),
                    request(),
                    (string) $auditoria->id,
                );
            }
        };

        return $controlador->props($auditoria)['recurso']->getKey();
    };

    expect($clave($una['auditoria']))->toBe("recurso:checklist:{$una['auditoria']->id}")
        ->and($clave($otra['auditoria']))->toBe("recurso:checklist:{$otra['auditoria']->id}")
        ->and($clave($una['auditoria']))->not->toBe($clave($otra['auditoria']));
});

/*
 * La otra mitad de la misma decisión: la clave del **recurso** se queda estable.
 * Es el nombre con el que la vista de columnas se guarda en el navegador y con el
 * que se nombra el CSV; hacerla dinámica guardaría una vista por auditoría —y
 * quien ordena sus columnas las perdería en la siguiente— y metería dos puntos
 * en el nombre del fichero. Las columnas de una checklist son idénticas auditoría
 * a auditoría: compartir la vista es lo que se quiere.
 */
it('la clave del recurso se queda estable aunque la caché no', function (): void {
    $una = ($this->montar)('op.acc.1');
    $otra = ($this->montar)('mp.if.1');

    foreach ([$una, $otra] as $cual) {
        $this->actingAs($this->usuario)
            ->get("/auditorias/{$cual['auditoria']->id}/checklist")
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('recurso.clave', 'checklist'));
    }
});

it('la acción masiva no toca las líneas de otra auditoría', function (): void {
    $una = ($this->montar)('op.acc.1');
    $otra = ($this->montar)('mp.if.1');

    $ajena = AuditoriaPunto::query()->where('auditoria_id', $otra['auditoria']->id)->firstOrFail();

    $this->actingAs($this->usuario)
        ->post("/auditorias/{$una['auditoria']->id}/checklist/resultado", ['puntos' => [$ajena->id]])
        ->assertRedirect();

    expect($ajena->fresh()?->resultado)->toBe(ResultadoPunto::Pendiente);
});

it('no se puede revisar una línea desde la auditoría equivocada', function (): void {
    $una = ($this->montar)('op.acc.1');
    $otra = ($this->montar)('mp.if.1');

    $ajena = AuditoriaPunto::query()->where('auditoria_id', $otra['auditoria']->id)->firstOrFail();

    // `scopeBindings()` resuelve el punto dentro de su auditoría: el de otra no
    // existe para esta ruta.
    $this->actingAs($this->usuario)
        ->put("/auditorias/{$una['auditoria']->id}/checklist/{$ajena->id}", [
            'resultado' => ResultadoPunto::Conforme->value,
        ])
        ->assertNotFound();
});

it('no se puede colgar un hallazgo de una línea de otra auditoría', function (): void {
    $una = ($this->montar)('op.acc.1');
    $otra = ($this->montar)('mp.if.1');

    $ajena = AuditoriaPunto::query()->where('auditoria_id', $otra['auditoria']->id)->firstOrFail();

    $this->actingAs($this->usuario)
        ->post("/auditorias/{$una['auditoria']->id}/hallazgos", [
            'tipo' => TipoHallazgo::NcMenor->value,
            'descripcion' => 'Colado desde otra auditoría.',
            'auditoria_punto_id' => $ajena->id,
        ])
        ->assertNotFound();

    expect(Hallazgo::query()->count())->toBe(0);
});

/*
 * El trigger también lo impide, pero el error de PostgreSQL sube como un 500 que
 * nadie lee. La guarda del dominio es la que da un mensaje que se entiende.
 */
it('marcar en bloque una auditoría cerrada da un error legible y no toca nada', function (): void {
    $una = ($this->montar)('op.acc.1');
    $auditoria = $una['auditoria'];

    $punto = AuditoriaPunto::query()->where('auditoria_id', $auditoria->id)->firstOrFail();

    $cerrar = app(CerrarAuditoria::class);
    $cerrar->empezar($auditoria);
    $cerrar->cerrar($auditoria, $this->usuario);

    $this->actingAs($this->usuario)
        ->post("/auditorias/{$auditoria->id}/checklist/resultado", ['puntos' => [$punto->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($punto->fresh()?->resultado)->toBe(ResultadoPunto::Pendiente);
});

it('marca conformes las líneas seleccionadas y sólo ésas', function (): void {
    $una = ($this->montar)('op.acc.1');
    $auditoria = $una['auditoria'];

    // Una segunda medida en el mismo sistema, para que haya algo que no tocar.
    $requisito = Requisito::factory()->create([
        'marco_id' => $auditoria->sistema?->marco_id,
        'codigo' => 'op.acc.2',
        'tipo' => TipoRequisito::Medida->value,
        'orden' => 2,
    ]);
    Implantacion::factory()->for($auditoria->sistema)->create(['requisito_id' => $requisito->id]);
    app(PrecargarChecklist::class)($auditoria);

    $puntos = AuditoriaPunto::query()->where('auditoria_id', $auditoria->id)->orderBy('id')->get();

    $this->actingAs($this->usuario)
        ->post("/auditorias/{$auditoria->id}/checklist/resultado", ['puntos' => [$puntos[0]->id]])
        ->assertRedirect();

    expect($puntos[0]->fresh()?->resultado)->toBe(ResultadoPunto::Conforme)
        ->and($puntos[1]->fresh()?->resultado)->toBe(ResultadoPunto::Pendiente);
});

it('el auditor mira la checklist y no la toca', function (): void {
    $una = ($this->montar)('op.acc.1');
    $auditor = usuarioCon(Rol::Auditor);
    $punto = AuditoriaPunto::query()->where('auditoria_id', $una['auditoria']->id)->firstOrFail();

    $this->actingAs($auditor)
        ->get("/auditorias/{$una['auditoria']->id}/checklist")
        ->assertOk();

    $this->actingAs($auditor)
        ->post("/auditorias/{$una['auditoria']->id}/checklist/resultado", ['puntos' => [$punto->id]])
        ->assertForbidden();
});
