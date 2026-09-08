<?php

declare(strict_types=1);

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Models\AplicabilidadEns;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;

/**
 * Recálculo tras un cambio de valoración. Prioridad 3 de cobertura.
 *
 * La regla que manda (§3 de la especificación): el sistema NO borra
 * implantaciones. Marca las que dejan de aplicar, crea las nuevas en
 * `no_iniciado` y avisa de la diferencia. Borrarlas destruiría la traza de lo
 * que se hizo mientras la medida sí se exigía, que es justo lo que el auditor
 * pide ver.
 */
beforeEach(function (): void {
    $this->organizacion = comoOrganizacion();
    $this->generador = app(GeneradorImplantaciones::class);

    $this->marco = Marco::factory()->create(['codigo' => 'ENS-SINTETICO']);

    $medida = function (string $codigo, array $celdas, ?Dimension $dimension = null): Requisito {
        $requisito = Requisito::factory()
            ->conCodigo($codigo)
            ->create(['marco_id' => $this->marco->id, 'titulo' => "Medida {$codigo}"]);

        foreach ($celdas as $categoria => $exigencia) {
            $fabrica = AplicabilidadEns::factory()->para(CategoriaEns::from($categoria), $exigencia);

            if ($dimension !== null) {
                $fabrica = $fabrica->moduladaPor($dimension);
            }

            $fabrica->create(['requisito_id' => $requisito->id]);
        }

        return $requisito;
    };

    $medida('org.1', ['basica' => 'aplica', 'media' => 'aplica', 'alta' => 'aplica']);
    $medida('op.acc.5', ['basica' => 'aplica', 'media' => 'R1', 'alta' => 'R2']);
    $medida('op.cont.2', ['basica' => 'no_aplica', 'media' => 'aplica', 'alta' => 'R1'], Dimension::Disponibilidad);

    $this->sistema = Sistema::factory()->de($this->organizacion)->conMarco($this->marco)->create();

    $this->valorar = function (Dimension $dimension, NivelDimension $nivel): void {
        ValoracionDimension::query()->updateOrCreate(
            ['sistema_id' => $this->sistema->id, 'dimension' => $dimension->value],
            ['organizacion_id' => $this->organizacion->id, 'nivel' => $nivel->value],
        );
    };

    $this->implantacion = fn (string $codigo): Implantacion => Implantacion::query()
        ->whereHas('requisito', fn ($q) => $q->where('codigo', $codigo))
        ->firstOrFail();

    // Punto de partida: categoría básica por confidencialidad, sin disponibilidad.
    ($this->valorar)(Dimension::Confidencialidad, NivelDimension::Bajo);
    $this->generador->generar($this->sistema);
});

it('parte de un conjunto de categoría básica', function (): void {
    $codigos = Implantacion::query()->with('requisito')->get()->pluck('requisito.codigo')->all();

    // op.cont.2 está modulada por disponibilidad, y aquí la disponibilidad es `na`.
    expect($codigos)->toEqualCanonicalizing(['org.1', 'op.acc.5'])
        ->and($codigos)->not->toContain('op.cont.2');
});

it('subir una dimensión añade medidas sin tocar el trabajo ya hecho', function (): void {
    // Se da por implantada una medida antes de revalorar.
    $orgUno = ($this->implantacion)('org.1');
    $orgUno->update(['estado' => EstadoImplantacion::Implantado->value]);

    ($this->valorar)(Dimension::Disponibilidad, NivelDimension::Medio);

    $resultado = $this->generador->generar($this->sistema);

    expect($resultado->creadas)->toBe(['op.cont.2'])
        ->and($resultado->dejanDeAplicar)->toBeEmpty();

    // La nueva nace en no_iniciado; la que estaba implantada sigue implantada.
    expect(($this->implantacion)('op.cont.2')->estado)->toBe(EstadoImplantacion::NoIniciado)
        ->and(($this->implantacion)('org.1')->estado)->toBe(EstadoImplantacion::Implantado);
});

it('bajar una dimensión marca la medida, no la borra', function (): void {
    ($this->valorar)(Dimension::Disponibilidad, NivelDimension::Medio);
    $this->generador->generar($this->sistema);

    $antes = Implantacion::query()->count();

    ($this->valorar)(Dimension::Disponibilidad, NivelDimension::Na);
    $resultado = $this->generador->generar($this->sistema);

    expect($resultado->dejanDeAplicar)->toBe(['op.cont.2']);

    // Ni una fila menos: la traza de lo que se hizo se conserva entera.
    expect(Implantacion::query()->withoutGlobalScopes()->count())->toBe($antes);

    $cont = ($this->implantacion)('op.cont.2');

    expect($cont->aplica)->toBeFalse()
        ->and($cont->estado)->toBe(EstadoImplantacion::NoAplica)
        ->and($cont->justificacion)->not->toBeNull()
        ->and($cont->transiciones->last()->estado_nuevo)->toBe(EstadoImplantacion::NoAplica);
});

it('cuando una medida vuelve a exigirse recupera el estado que tenía', function (): void {
    ($this->valorar)(Dimension::Disponibilidad, NivelDimension::Medio);
    $this->generador->generar($this->sistema);

    // Se trabaja en ella y queda en progreso.
    ($this->implantacion)('op.cont.2')->update(['estado' => EstadoImplantacion::EnProgreso->value]);

    // Deja de exigirse…
    ($this->valorar)(Dimension::Disponibilidad, NivelDimension::Na);
    $this->generador->generar($this->sistema);

    expect(($this->implantacion)('op.cont.2')->estado)->toBe(EstadoImplantacion::NoAplica);

    // …y vuelve a exigirse.
    ($this->valorar)(Dimension::Disponibilidad, NivelDimension::Medio);
    $resultado = $this->generador->generar($this->sistema);

    expect($resultado->reactivadas)->toBe(['op.cont.2'])
        ->and($resultado->creadas)->toBeEmpty();

    $cont = ($this->implantacion)('op.cont.2');

    // No vuelve a cero: recupera el trabajo que constaba hecho, leído del histórico.
    expect($cont->estado)->toBe(EstadoImplantacion::EnProgreso)
        ->and($cont->aplica)->toBeTrue()
        ->and($cont->justificacion)->toBeNull();
});

it('un cambio de refuerzo actualiza la exigencia y no el estado', function (): void {
    ($this->implantacion)('op.acc.5')->update(['estado' => EstadoImplantacion::Implantado->value]);

    expect((string) ($this->implantacion)('op.acc.5')->exigencia_calculada)->toBe('aplica');

    // Subir a categoría media endurece op.acc.5 de `aplica` a R1.
    ($this->valorar)(Dimension::Confidencialidad, NivelDimension::Medio);
    $resultado = $this->generador->generar($this->sistema);

    expect($resultado->cambianExigencia)->toContain([
        'codigo' => 'op.acc.5',
        'anterior' => 'aplica',
        'nueva' => 'R1',
    ]);

    $acc = ($this->implantacion)('op.acc.5');

    // Que ahora se exija R1 amplía el trabajo, no lo deshace.
    expect((string) $acc->exigencia_calculada)->toBe('R1')
        ->and($acc->estado)->toBe(EstadoImplantacion::Implantado);
});

it('el recálculo deja histórico sin autor, porque no lo decide una persona', function (): void {
    ($this->valorar)(Dimension::Disponibilidad, NivelDimension::Medio);
    $this->generador->generar($this->sistema);

    $transicion = ($this->implantacion)('op.cont.2')->transiciones->last();

    expect($transicion->usuario_id)->toBeNull()
        ->and($transicion->nota)->not->toBeNull();
});

it('el importador informa de cuántas implantaciones afecta retirar un requisito', function (): void {
    $importador = app(ImportadorCatalogo::class);

    $fichero = tempnam(sys_get_temp_dir(), 'catalogo').'.yaml';

    // El mismo marco, pero sin op.acc.5: el importador lo marca como retirado y
    // tiene que decir a cuántas implantaciones afecta, de todas las
    // organizaciones. Sin la puerta de mantenimiento, RLS diría cero.
    file_put_contents($fichero, <<<'YAML'
    marco:
      codigo: ENS-SINTETICO
      nombre: Marco sintético
      version: '1'
    requisitos:
      - codigo: org.1
        tipo: medida
        titulo: Medida org.1
        aplicabilidad: {basica: aplica, media: aplica, alta: aplica}
    YAML);

    $resultado = $importador->importar($fichero);

    unlink($fichero);

    expect($resultado->retirados)->toContain('op.acc.5')
        ->and($resultado->implantacionesAfectadas)->toBeGreaterThan(0);
});
