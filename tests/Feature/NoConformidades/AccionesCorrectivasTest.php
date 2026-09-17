<?php

declare(strict_types=1);

use App\Domain\Auditoria\CerrarAuditoria;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Auditoria\PrecargarChecklist;
use App\Domain\Auditoria\RegistrarHallazgo;
use App\Domain\Catalogo\Enums\TipoRequisito;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\NoConformidad\AbrirAccionCorrectiva;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\NoConformidad\RegistrarNoConformidad;
use App\Domain\NoConformidad\VincularAccionCorrectiva;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Tarea\Coste;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\VincularTarea;
use Illuminate\Support\Carbon;

/**
 * El doble vínculo, que es el fallo caro de este módulo.
 *
 * `Implantacion::sinTrabajo()` mira `implantacion_tarea`. Una acción correctiva
 * colgada sólo de la no conformidad no está ahí, así que **el plan de adecuación
 * imprimiría «sin trabajo planificado» sobre una medida que sí lo tiene**, en la
 * tabla que la dirección mira seguro. Un documento que se contradice con el
 * registro es peor que un documento incompleto.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->usuario = usuarioCon();

    $marco = Marco::factory()->create();
    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($marco)->create();

    $this->medida = function (string $codigo, int $orden) use ($marco): Implantacion {
        $requisito = Requisito::factory()->create([
            'marco_id' => $marco->id,
            'codigo' => $codigo,
            'tipo' => TipoRequisito::Medida->value,
            'orden' => $orden,
        ]);

        return Implantacion::factory()->for($this->sistema)->create(['requisito_id' => $requisito->id]);
    };

    /** Una no conformidad nacida de un hallazgo sobre una medida concreta. */
    $this->deMedida = function (Implantacion $implantacion): NoConformidad {
        $auditoria = Auditoria::factory()->paraSistema($this->sistema->id)->create();
        app(PrecargarChecklist::class)($auditoria);

        $punto = $auditoria->puntos()->where('implantacion_id', $implantacion->id)->firstOrFail();

        $hallazgo = app(RegistrarHallazgo::class)->registrar(
            $auditoria,
            TipoHallazgo::NcMenor,
            'La medida no está implantada como dice el procedimiento.',
            $punto,
        );

        return app(RegistrarNoConformidad::class)([
            'codigo' => 'NC-2026-'.$hallazgo->id,
            'origen' => OrigenNoConformidad::Auditoria->value,
            'hallazgo_id' => $hallazgo->id,
            'descripcion' => 'Tratamiento del hallazgo.',
            'fecha_deteccion' => Carbon::today(),
        ], $this->usuario);
    };
});

it('abrir la acción correctiva la vincula también a la medida, y la saca de «sin trabajo»', function (): void {
    $implantacion = ($this->medida)('op.acc.1', 1);

    expect(Implantacion::query()->sinTrabajo()->pluck('id'))->toContain($implantacion->id);

    $nc = ($this->deMedida)($implantacion);

    $tarea = app(AbrirAccionCorrectiva::class)($nc, [
        'titulo' => 'Reescribir el procedimiento de altas',
        'prioridad' => 'alta',
    ], $this->usuario);

    expect($nc->tareas()->pluck('tareas.id'))->toContain($tarea->id)
        // El segundo vínculo: sin él, el plan de adecuación mentiría.
        ->and($tarea->implantaciones()->pluck('implantaciones.id'))->toContain($implantacion->id)
        ->and(Implantacion::query()->sinTrabajo()->pluck('id'))->not->toContain($implantacion->id);
});

/*
 * El vínculo vive en la acción de dominio y no en el controlador. Si sólo lo
 * hiciera el formulario de alta, la tarea que alguien vincule más tarde desde la
 * ficha no lo tendría y el falso positivo volvería por la otra puerta.
 */
it('vincular una tarea que ya existía ata igualmente la medida', function (): void {
    $implantacion = ($this->medida)('op.acc.1', 1);
    $nc = ($this->deMedida)($implantacion);

    $tarea = Tarea::factory()->create(['titulo' => 'Comprar el gestor de identidades']);

    expect($tarea->implantaciones()->count())->toBe(0);

    app(VincularAccionCorrectiva::class)->vincular($nc, $tarea, $this->usuario);

    expect($tarea->fresh()?->implantaciones()->pluck('implantaciones.id'))->toContain($implantacion->id);
});

/*
 * Cubre una parte de los casos y hay que decirlo: una no conformidad suelta, o de
 * un hallazgo que no cuelga de ninguna medida —«el programa de auditoría no está
 * definido»—, no tiene a qué apuntar. Forzarla contra una implantación arbitraria
 * sería el vicio que `OrigenTarea::Propia` existe para evitar.
 */
it('una no conformidad sin medida detrás vincula la tarea y nada más', function (): void {
    $nc = app(RegistrarNoConformidad::class)([
        'codigo' => 'NC-2026-99',
        'descripcion' => 'El programa de auditoría interna no está definido.',
        'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    $tarea = app(AbrirAccionCorrectiva::class)($nc, [
        'titulo' => 'Definir el programa anual de auditoría',
        'prioridad' => 'media',
    ], $this->usuario);

    expect($nc->tareas()->count())->toBe(1)
        ->and($tarea->implantaciones()->count())->toBe(0);
});

/*
 * Donde uno espera un error y no lo hay: las no conformidades se tratan **después**
 * de cerrar la auditoría, que es cuando se sabe qué hubo. Escribir en la pivote no
 * pasa por el trigger de inmutabilidad, que blinda la checklist y los hallazgos y
 * no lo que cuelga de ellos.
 */
it('se puede tratar una no conformidad de una auditoría ya cerrada', function (): void {
    $implantacion = ($this->medida)('op.acc.1', 1);
    $nc = ($this->deMedida)($implantacion);

    $auditoria = $nc->hallazgo?->auditoria;
    expect($auditoria)->not->toBeNull();

    $cerrar = app(CerrarAuditoria::class);
    $cerrar->empezar($auditoria);
    $cerrar->cerrar($auditoria, $this->usuario);

    $tarea = app(AbrirAccionCorrectiva::class)($nc, [
        'titulo' => 'Reescribir el procedimiento',
        'prioridad' => 'alta',
    ], $this->usuario);

    expect($tarea->implantaciones()->pluck('implantaciones.id'))->toContain($implantacion->id);
});

it('la acción correctiva nace con el origen puesto y no se pregunta', function (): void {
    $nc = app(RegistrarNoConformidad::class)([
        'codigo' => 'NC-2026-98',
        'descripcion' => 'Cualquier cosa.',
        'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    $tarea = app(AbrirAccionCorrectiva::class)($nc, [
        'titulo' => 'Hacer lo que haya que hacer',
        'prioridad' => 'media',
        // Aunque llegue otro origen, manda el de la acción de dominio: una
        // acción correctiva marcada «iniciativa propia» pierde lo único que la
        // hacía trazable.
        'origen' => OrigenTarea::Propia->value,
    ], $this->usuario);

    expect($tarea->origen)->toBe(OrigenTarea::NoConformidad);
});

/*
 * El número que se le lleva a la dirección. Una acción correctiva que hace
 * avanzar tres medidas se presupuesta **una vez**: es el mismo argumento
 * aritmético que dejó las subtareas fuera de `tareas`.
 */
it('una acción correctiva que cubre tres medidas cuesta una vez', function (): void {
    $primera = ($this->medida)('op.acc.1', 1);
    $segunda = ($this->medida)('op.acc.2', 2);
    $tercera = ($this->medida)('op.acc.3', 3);

    $nc = ($this->deMedida)($primera);

    $tarea = app(AbrirAccionCorrectiva::class)($nc, [
        'titulo' => 'Implantar MFA',
        'prioridad' => 'critica',
        'coste_estimado' => '1200.00',
    ], $this->usuario);

    app(VincularTarea::class)->vincular($tarea, $segunda, $this->usuario);
    app(VincularTarea::class)->vincular($tarea, $tercera, $this->usuario);

    // Las tres medidas la imputan, y el total la cuenta una vez.
    expect($tarea->implantaciones()->count())->toBe(3)
        ->and(Coste::total([$tarea, $tarea, $tarea]))->toBe(1200.0);
});

/*
 * Desvincular suelta la acción correctiva y **deja puesto el vínculo con la
 * medida**: no hay forma de saber si lo escribió el módulo o una persona desde la
 * ficha de la implantación, y quitarlo a ciegas borraría trabajo planificado a
 * mano.
 */
it('desvincular suelta la acción correctiva y no el trabajo sobre la medida', function (): void {
    $implantacion = ($this->medida)('op.acc.1', 1);
    $nc = ($this->deMedida)($implantacion);

    $tarea = app(AbrirAccionCorrectiva::class)($nc, [
        'titulo' => 'Reescribir el procedimiento',
        'prioridad' => 'media',
    ], $this->usuario);

    app(VincularAccionCorrectiva::class)->desvincular($nc, $tarea);

    expect($nc->fresh()?->tareas()->count())->toBe(0)
        ->and($tarea->fresh()?->implantaciones()->pluck('implantaciones.id'))->toContain($implantacion->id);
});

/*
 * La costura entre las dos mitades del módulo: una no conformidad mayor sin
 * tratamiento detrás es un hallazgo de la auditoría siguiente.
 */
it('un hallazgo tratado deja de contar como sin tratar, y una observación nunca contó', function (): void {
    $implantacion = ($this->medida)('op.acc.1', 1);
    $auditoria = Auditoria::factory()->paraSistema($this->sistema->id)->create();
    app(PrecargarChecklist::class)($auditoria);

    $punto = $auditoria->puntos()->where('implantacion_id', $implantacion->id)->firstOrFail();
    $registrar = app(RegistrarHallazgo::class);

    $mayor = $registrar->registrar($auditoria, TipoHallazgo::NcMayor, 'Falla el sistema de gestión.', $punto);
    $registrar->registrar($auditoria, TipoHallazgo::Observacion, 'Podría documentarse mejor.', $punto);

    expect(Hallazgo::query()->sinTratar()->pluck('id'))
        ->toContain($mayor->id)
        ->toHaveCount(1);

    app(RegistrarNoConformidad::class)([
        'codigo' => 'NC-2026-50',
        'origen' => OrigenNoConformidad::Auditoria->value,
        'hallazgo_id' => $mayor->id,
        'descripcion' => 'Tratamiento del hallazgo mayor.',
        'fecha_deteccion' => Carbon::today(),
    ], $this->usuario);

    expect(Hallazgo::query()->sinTratar()->count())->toBe(0);
});
