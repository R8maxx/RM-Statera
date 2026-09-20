<?php

declare(strict_types=1);

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\RevisionDireccion\VincularDecision;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| El aislamiento de las revisiones por la dirección
|--------------------------------------------------------------------------
|
| Es el registro más sensible del producto: un acta aprobada contiene, congeladas,
| **las siete entradas de la 9.3** de esa organización —sus no conformidades, sus
| riesgos, sus auditorías y sus objetivos—. Filtrar una fila de aquí es filtrar el
| SGSI entero de un cliente en un solo documento.
|
| Y el segundo eje, el que las tres capas no tapan: entre dos revisiones de la
| MISMA organización lo único que separa es `scopeBindings()`.
|
*/

beforeEach(function (): void {
    $this->propia = comoOrganizacion();
    $this->usuario = usuarioCon();
    $this->ajena = Organizacion::factory()->create();
});

it('no lista las revisiones de otra organización', function (): void {
    RevisionDireccion::factory()->create(['codigo' => 'RD-PROPIA']);

    app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->ajena,
        fn () => RevisionDireccion::factory()->create(['codigo' => 'RD-AJENA']),
    );

    app(ContextoOrganizacion::class)->establecer($this->propia);

    expect(RevisionDireccion::query()->pluck('codigo')->all())->toBe(['RD-PROPIA']);
});

it('responde 404 al pedir la revisión de otra organización', function (): void {
    $ajena = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->ajena,
        fn (): RevisionDireccion => RevisionDireccion::factory()->enCurso()->create(),
    );

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $this->actingAs($this->usuario)->get("/revision-direccion/{$ajena->id}")->assertNotFound();
    $this->actingAs($this->usuario)->get("/revision-direccion/{$ajena->id}/editar")->assertNotFound();
    $this->actingAs($this->usuario)->delete("/revision-direccion/{$ajena->id}")->assertNotFound();
    $this->actingAs($this->usuario)->post("/revision-direccion/{$ajena->id}/aprobacion")->assertNotFound();
});

it('la revisión anterior nunca es la de otra organización', function (): void {
    /*
     * El caso que más duele si falla: `anterior()` busca por fecha, y sin el
     * scope de organización el acta de un cliente recogería como «acciones
     * previas» las decisiones de otro.
     */
    // El firmante tiene que existir de verdad: `aprobada_por_id` es una foránea,
    // y un id inventado aborta la transacción con un error que no habla de
    // aislamiento y hace fallar el test por el motivo equivocado.
    $suyo = User::factory()->create(['organizacion_id' => $this->ajena->id]);

    app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->ajena,
        fn () => RevisionDireccion::factory()->aprobada($suyo->id)->create(['codigo' => 'RD-AJENA']),
    );

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $mia = RevisionDireccion::factory()->enCurso()->create(['codigo' => 'RD-MIA']);

    expect($mia->anterior())->toBeNull();
});

it('no desvincula desde una revisión la decisión de otra', function (): void {
    $tarea = Tarea::factory()->create();

    $mia = RevisionDireccion::factory()->enCurso()->create();
    $otra = RevisionDireccion::factory()->enCurso()->create();

    app(VincularDecision::class)->vincular($otra, $tarea, $this->usuario);

    $this->actingAs($this->usuario)
        ->delete("/revision-direccion/{$mia->id}/decisiones/{$tarea->id}")
        ->assertNotFound();

    expect($otra->tareas()->count())->toBe(1);
});

it('no vincula la tarea de otra organización', function (): void {
    $ajena = app(ContextoOrganizacion::class)->paraOrganizacion(
        $this->ajena,
        fn (): Tarea => Tarea::factory()->create(),
    );

    app(ContextoOrganizacion::class)->establecer($this->propia);

    $revision = RevisionDireccion::factory()->enCurso()->create();

    // Se queda en el `exists` del `FormRequest`: esa regla corre bajo RLS, así
    // que para esta sesión la tarea de otro cliente no existe.
    $this->actingAs($this->usuario)
        ->post("/revision-direccion/{$revision->id}/decisiones/vincular", ['tarea_id' => $ajena->id])
        ->assertSessionHasErrors('tarea_id');

    expect($revision->tareas()->count())->toBe(0);
});
