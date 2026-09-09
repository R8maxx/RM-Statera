<?php

declare(strict_types=1);

namespace App\Domain\Implantacion;

use App\Domain\Catalogo\Enums\Exigencia;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Categorizacion\ConjuntoExigible;
use App\Domain\Categorizacion\DiferenciaConjuntos;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Categorizacion\MedidaExigible;
use App\Domain\Categorizacion\MotorCategorizacion;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Convierte el conjunto exigible de un sistema en filas de `implantaciones`, y
 * lo mantiene al día cuando cambia la valoración.
 *
 * La regla que manda (§3 de la especificación): al recalcularse, el sistema NO
 * borra implantaciones. Marca las que dejan de aplicar, crea las nuevas en
 * `no_iniciado` y avisa de la diferencia. Borrarlas destruiría la traza de lo
 * que se hizo mientras la medida sí se exigía, que es lo que el auditor pide.
 */
final class GeneradorImplantaciones
{
    public function __construct(
        private readonly MotorCategorizacion $motor,
        private readonly RegistroTransiciones $registro,
    ) {}

    public function generar(Sistema $sistema, bool $simulacion = false): ResultadoGeneracion
    {
        DB::beginTransaction();

        try {
            $resultado = $this->sincronizar($sistema, $simulacion);

            if ($simulacion) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $resultado;
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    private function sincronizar(Sistema $sistema, bool $simulacion): ResultadoGeneracion
    {
        // `load` y no `loadMissing`: esto es un recálculo, y leer una valoración
        // obsoleta de una relación ya cargada daría un conjunto exigible que no se
        // corresponde con lo que hay en la base.
        $sistema->load(['marco', 'perfil', 'valoraciones']);

        $exigible = $this->conjuntoExigible($sistema);

        /** @var array<string, Implantacion> $existentes */
        $existentes = $sistema->implantaciones()
            ->with('requisito')
            ->get()
            ->keyBy(fn (Implantacion $implantacion): string => $implantacion->requisito->codigo)
            ->all();

        $actual = $this->conjuntoActual($existentes);
        $diferencia = DiferenciaConjuntos::entre($actual, $exigible);

        $resultado = new ResultadoGeneracion(
            sistemaId: $sistema->id,
            sistema: $sistema->nombre,
            marco: $sistema->marco->codigo,
            categoria: $sistema->categoria()?->value,
            simulacion: $simulacion,
        );

        foreach ($diferencia->nuevas as $medida) {
            $existente = $existentes[$medida->codigo] ?? null;

            if ($existente === null) {
                $this->crear($sistema, $medida);
                $resultado->creadas[] = $medida->codigo;

                continue;
            }

            $this->reactivar($existente, $medida);
            $resultado->reactivadas[] = $medida->codigo;
        }

        foreach ($diferencia->cambianDeExigencia as $cambio) {
            $this->actualizarExigencia($existentes[$cambio['codigo']], $cambio['nueva']);

            $resultado->cambianExigencia[] = [
                'codigo' => $cambio['codigo'],
                'anterior' => (string) $cambio['anterior']->exigencia,
                'nueva' => (string) $cambio['nueva']->exigencia,
            ];
        }

        foreach ($diferencia->dejanDeAplicar as $medida) {
            $this->marcarComoNoAplicable($existentes[$medida->codigo]);
            $resultado->dejanDeAplicar[] = $medida->codigo;
        }

        // Las que dejan de aplicar no estaban en el conjunto exigible, así que
        // no entran en esta resta.
        $resultado->sinCambios = $exigible->count()
            - count($resultado->creadas)
            - count($resultado->reactivadas)
            - count($resultado->cambianExigencia);

        // Origen y dimensión moduladora pueden cambiar sin que cambie el nivel
        // exigido: se persisten igualmente, porque son la explicación de por qué
        // se exige la medida.
        $this->alinearOrigenes($existentes, $exigible);

        return $resultado;
    }

    /**
     * Dos estrategias, según lo que el catálogo sepa del marco.
     *
     * La decisión se toma mirando el dato, no el código del marco: si hay matriz
     * de aplicabilidad, la exigencia se deriva; si no, todos los requisitos hoja
     * aplican y excluir uno es una decisión motivada.
     */
    private function conjuntoExigible(Sistema $sistema): ConjuntoExigible
    {
        if ($this->marcoTieneMatriz($sistema)) {
            return $this->motor->calcular($sistema->marco, $sistema->valoracion(), $sistema->perfil);
        }

        return $this->todosLosRequisitosHoja($sistema);
    }

    private function marcoTieneMatriz(Sistema $sistema): bool
    {
        return DB::table('aplicabilidad_ens')
            ->join('requisitos', 'requisitos.id', '=', 'aplicabilidad_ens.requisito_id')
            ->where('requisitos.marco_id', $sistema->marco_id)
            ->exists();
    }

    /**
     * Estrategia de ISO: los 93 controles del Anexo A y las cláusulas del cuerpo
     * aplican de partida; la Declaración de Aplicabilidad es precisamente la
     * lista de exclusiones justificadas.
     *
     * Sólo hojas: los nodos de agrupación (`A.5`, `4`) no llevan implantación,
     * igual que `org` o `op.acc` no la llevan en el ENS.
     */
    private function todosLosRequisitosHoja(Sistema $sistema): ConjuntoExigible
    {
        $hojas = Requisito::query()
            ->where('marco_id', $sistema->marco_id)
            ->where('vigente', true)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('requisitos as hijos')
                    ->whereColumn('hijos.parent_id', 'requisitos.id');
            })
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();

        return new ConjuntoExigible($hojas->map(fn (Requisito $requisito): MedidaExigible => new MedidaExigible(
            requisitoId: $requisito->id,
            codigo: $requisito->codigo,
            titulo: $requisito->titulo,
            exigencia: Exigencia::aplica(),
            origen: OrigenExigencia::Catalogo,
        )));
    }

    /**
     * El conjunto que hoy tiene el sistema, en la misma forma que devuelve el
     * motor, para poder compararlos con DiferenciaConjuntos.
     *
     * Las implantaciones con `aplica = false` quedan fuera a propósito: no
     * forman parte del conjunto exigido, y si la medida vuelve se tratarán como
     * reactivación, no como alta.
     *
     * @param  array<string, Implantacion>  $existentes
     */
    private function conjuntoActual(array $existentes): ConjuntoExigible
    {
        $medidas = [];

        foreach ($existentes as $codigo => $implantacion) {
            if (! $implantacion->aplica) {
                continue;
            }

            $medidas[] = new MedidaExigible(
                requisitoId: $implantacion->requisito_id,
                codigo: $codigo,
                titulo: $implantacion->requisito->titulo,
                exigencia: $implantacion->exigencia_calculada ?? Exigencia::aplica(),
                origen: $implantacion->origen_exigencia ?? OrigenExigencia::Catalogo,
                dimensionModuladora: $implantacion->dimension_moduladora,
            );
        }

        return new ConjuntoExigible($medidas);
    }

    private function crear(Sistema $sistema, MedidaExigible $medida): Implantacion
    {
        $implantacion = Implantacion::query()->create([
            'organizacion_id' => $sistema->organizacion_id,
            'sistema_id' => $sistema->id,
            'requisito_id' => $medida->requisitoId,
            'aplica' => true,
            'justificacion' => null,
            'exigencia_calculada' => (string) $medida->exigencia,
            'origen_exigencia' => $medida->origen->value,
            'dimension_moduladora' => $medida->dimensionModuladora?->value,
            'estado' => EstadoImplantacion::NoIniciado->value,
        ]);

        // El alta también es histórico: `estado_anterior` nulo significa que no
        // venía de ningún sitio.
        $this->registro->registrar(
            $implantacion,
            null,
            EstadoImplantacion::NoIniciado,
            null,
            'Alta por generación del conjunto exigible.',
        );

        return $implantacion;
    }

    /**
     * La medida vuelve a exigirse: se recupera el estado que tenía antes de
     * marcarse `no_aplica`. Para eso se guarda el histórico; empezar de cero
     * borraría el trabajo que ya constaba hecho.
     */
    private function reactivar(Implantacion $implantacion, MedidaExigible $medida): void
    {
        $anterior = $implantacion->estado;
        $destino = $implantacion->estadoPrevioANoAplica() ?? EstadoImplantacion::NoIniciado;

        $implantacion->update([
            'aplica' => true,
            'justificacion' => null,
            'exigencia_calculada' => (string) $medida->exigencia,
            'origen_exigencia' => $medida->origen->value,
            'dimension_moduladora' => $medida->dimensionModuladora?->value,
            'estado' => $destino->value,
        ]);

        $this->registro->registrar(
            $implantacion,
            $anterior,
            $destino,
            null,
            'Vuelve a exigirse tras recalcular el conjunto exigible.',
        );
    }

    /**
     * Cambia el nivel exigido, no el trabajo hecho: el estado NO se toca. Que
     * ahora se exija R2 donde antes bastaba `aplica` no deshace lo implantado,
     * lo amplía.
     */
    private function actualizarExigencia(Implantacion $implantacion, MedidaExigible $medida): void
    {
        $implantacion->update([
            'exigencia_calculada' => (string) $medida->exigencia,
            'origen_exigencia' => $medida->origen->value,
            'dimension_moduladora' => $medida->dimensionModuladora?->value,
        ]);
    }

    /**
     * La medida deja de exigirse. No se borra: se marca, con justificación y
     * transición, porque puede haber trabajo y evidencias colgando de ella.
     */
    private function marcarComoNoAplicable(Implantacion $implantacion): void
    {
        $anterior = $implantacion->estado;

        $implantacion->update([
            'aplica' => false,
            'estado' => EstadoImplantacion::NoAplica->value,
            'justificacion' => sprintf(
                'Deja de exigirse tras recalcular el conjunto exigible del sistema el %s.',
                Carbon::now()->format('d/m/Y'),
            ),
        ]);

        $this->registro->registrar(
            $implantacion,
            $anterior,
            EstadoImplantacion::NoAplica,
            null,
            'Deja de exigirse tras recalcular el conjunto exigible.',
        );
    }

    /**
     * @param  array<string, Implantacion>  $existentes
     */
    private function alinearOrigenes(array $existentes, ConjuntoExigible $exigible): void
    {
        foreach ($exigible as $codigo => $medida) {
            $implantacion = $existentes[$codigo] ?? null;

            if ($implantacion === null || ! $implantacion->aplica) {
                continue;
            }

            $origenDistinto = $implantacion->origen_exigencia !== $medida->origen;
            $dimensionDistinta = $implantacion->dimension_moduladora !== $medida->dimensionModuladora;

            if ($origenDistinto || $dimensionDistinta) {
                $implantacion->update([
                    'origen_exigencia' => $medida->origen->value,
                    'dimension_moduladora' => $medida->dimensionModuladora?->value,
                ]);
            }
        }
    }
}
