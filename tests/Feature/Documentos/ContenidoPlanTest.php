<?php

declare(strict_types=1);

use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Documento\Contenido\PlanAdecuacionEns;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\AplicarValoracion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\VincularTarea;

/**
 * Qué dice exactamente el plan de adecuación del ENS.
 *
 * Hace la pregunta contraria a la Declaración de Aplicabilidad: no qué se exige
 * y cómo está, sino **qué falta**, quién lo lleva, para cuándo y cuánto cuesta.
 * De ahí que lo que estos tests fijan sea sobre todo qué NO sale y qué NO se
 * cuenta dos veces.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();

    $importador = app(ImportadorCatalogo::class);
    foreach ($importador->ficherosDe(base_path('catalogo')) as $fichero) {
        $importador->importar($fichero);
    }

    $ens = Marco::query()->where('codigo', 'ENS-RD311-2022')->firstOrFail();

    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($ens)->create([
        'alcance_declarado' => 'Sede electrónica y su plataforma de tramitación.',
    ]);

    app(AplicarValoracion::class)->aplicar(
        $this->sistema,
        valoracion(['C' => 'bajo', 'I' => 'bajo', 'D' => 'bajo', 'A' => 'bajo', 'T' => 'bajo']),
        [],
    );

    $this->plan = function (): ContenidoDocumento {
        $documento = Documento::factory()->plan()->paraSistema($this->sistema->id)->create();
        $version = DocumentoVersion::factory()->delDocumento($documento->id)->create();

        return app(PlanAdecuacionEns::class)->construir($documento->fresh(), $version);
    };

    /** Las implantaciones exigibles del sistema, en orden, para poder tocarlas. */
    $this->exigibles = fn (int $cuantas) => Implantacion::query()
        ->delSistema($this->sistema->id)
        ->aplicables()
        ->orderBy('id')
        ->limit($cuantas)
        ->get();
});

it('lista sólo lo exigible que no está implantado', function (): void {
    $implantada = ($this->exigibles)(1)->first();
    $implantada->update(['estado' => EstadoImplantacion::Implantado->value]);

    $contenido = ($this->plan)();

    $codigos = array_map(static fn (FilaRequisito $fila): string => $fila->codigo, $contenido->filas);

    expect($codigos)->not->toContain($implantada->requisito->codigo);

    // Y ninguna fila excluida: `aplica = false` va con `estado = no_aplica`, que
    // el `CHECK` de la tabla garantiza, y eso no es trabajo pendiente.
    foreach ($contenido->filas as $fila) {
        expect($fila->aplica)->toBeTrue()
            ->and($fila->estado)->not->toBe('implantado');
    }
});

it('enseña el denominador: cuántas se exigen y cuántas están ya puestas', function (): void {
    ($this->exigibles)(3)->each(
        static fn (Implantacion $i) => $i->update(['estado' => EstadoImplantacion::Implantado->value])
    );

    $contenido = ($this->plan)();

    $exigibles = $contenido->resumen['exigibles'];

    expect($contenido->resumen['implantadas'])->toBe(3)
        ->and($contenido->resumen['total'])->toBe($exigibles - 3)
        ->and($contenido->filas)->toHaveCount($exigibles - 3);
});

/*
 * El test del módulo. `implantacion_tarea` es N:M: una actuación hace avanzar
 * varias medidas a la vez, y sumar la columna presupuestaría tres veces una
 * tarea que cubre tres medidas.
 */
it('no cuenta dos veces una tarea que cubre dos medidas', function (): void {
    $dos = ($this->exigibles)(2);

    $tarea = Tarea::factory()->create(['coste_estimado' => '1000.00']);

    $vincular = app(VincularTarea::class);
    foreach ($dos as $implantacion) {
        $vincular->vincular($tarea, $implantacion, null);
    }

    $contenido = ($this->plan)();

    // La columna se la imputa a cada una de las dos…
    $conCoste = array_values(array_filter(
        $contenido->filas,
        static fn (FilaRequisito $fila): bool => $fila->costeEstimado !== null,
    ));

    expect($conCoste)->toHaveCount(2)
        ->and($conCoste[0]->costeEstimado)->toBe('1.000,00 €')
        ->and($conCoste[1]->costeEstimado)->toBe('1.000,00 €');

    // …y el total la cuenta una vez. 1.000, no 2.000.
    expect($contenido->resumen['costeTotal'])->toBe('1.000,00 €')
        ->and($contenido->resumen['tareas'])->toBe(1);
});

it('no presupuesta trabajo ya cerrado', function (): void {
    $implantacion = ($this->exigibles)(1)->first();

    $vincular = app(VincularTarea::class);

    $vincular->vincular(
        Tarea::factory()->enEstado(EstadoTarea::Hecha)->create(['coste_estimado' => '500.00']),
        $implantacion,
        null,
    );
    $vincular->vincular(
        Tarea::factory()->enEstado(EstadoTarea::Descartada)->create(['coste_estimado' => '700.00']),
        $implantacion,
        null,
    );

    $contenido = ($this->plan)();

    expect($contenido->resumen['costeTotal'])->toBe('0,00 €')
        ->and($contenido->resumen['tareas'])->toBe(0);

    $fila = collect($contenido->filas)
        ->firstOrFail(fn (FilaRequisito $f): bool => $f->codigo === $implantacion->requisito->codigo);

    expect($fila->tareas)->toBe([])
        ->and($fila->costeEstimado)->toBeNull();
});

/*
 * Blindaje: si alguien le enchufara al plan el resumen de las declaraciones,
 * imprimiría «0 % implantado» sobre una tabla en la que ese cero no significa
 * nada. Es la mina que `DocumentoCalculado::resumen()` es abstracto para evitar.
 */
it('no habla de porcentaje implantado ni de exclusiones', function (): void {
    $resumen = ($this->plan)()->resumen;

    expect($resumen)->not->toHaveKey('porcentaje')
        ->and($resumen)->not->toHaveKey('excluidos')
        ->and($resumen)->toHaveKeys(['exigibles', 'implantadas', 'sinFecha', 'sinTrabajo', 'costeTotal']);
});

it('señala las medidas de las que no consta que nadie esté haciendo nada', function (): void {
    $implantacion = ($this->exigibles)(1)->first();

    app(VincularTarea::class)->vincular(Tarea::factory()->create(), $implantacion, null);

    $contenido = ($this->plan)();

    expect($contenido->resumen['sinTrabajo'])->toBe(count($contenido->filas) - 1);
});

/*
 * Sin esto, el `.docx` de una versión emitida saldría sin fecha, sin tareas y
 * sin coste mientras el PDF hermano —con la huella de ese PDF impresa dentro— sí
 * los lleva.
 */
it('los cuatro campos del plan sobreviven a la instantánea', function (): void {
    $implantacion = ($this->exigibles)(1)->first();
    $implantacion->update(['fecha_objetivo' => '2027-03-15']);

    app(VincularTarea::class)->vincular(
        Tarea::factory()->create(['titulo' => 'Cifrar los portátiles', 'coste_estimado' => '250.00']),
        $implantacion,
        null,
    );

    $contenido = ($this->plan)();
    $rehidratado = ContenidoDocumento::desdeInstantanea(
        $contenido->paraInstantanea(),
        app(MarkdownDocumento::class),
    );

    $original = collect($contenido->filas)
        ->firstOrFail(fn (FilaRequisito $f): bool => $f->codigo === $implantacion->requisito->codigo);
    $vuelto = collect($rehidratado->filas)
        ->firstOrFail(fn (FilaRequisito $f): bool => $f->codigo === $implantacion->requisito->codigo);

    expect($vuelto->fechaObjetivo)->toBe('15/03/2027')->toBe($original->fechaObjetivo)
        ->and($vuelto->costeEstimado)->toBe('250,00 €')->toBe($original->costeEstimado)
        ->and($vuelto->tareas)->toBe($original->tareas)
        ->and($vuelto->tareas[0])->toContain('Cifrar los portátiles');
});

it('declara por escrito que la columna de coste no suma el total', function (): void {
    $limitaciones = implode("\n", ($this->plan)()->limitaciones);

    expect($limitaciones)
        ->toContain('una sola vez')
        ->toContain('calendario de obligaciones');
});
