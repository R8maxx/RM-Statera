<?php

declare(strict_types=1);

namespace App\Domain\Conformidad;

use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Enums\ViaConformidad;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo que falta para poder iniciar la declaración de conformidad de un sistema.
 *
 * **Una sola lista de bloqueos, leída desde dos sitios**: la ficha la enseña antes
 * de que nadie pulse el botón, e `IniciarDeclaracion` la vuelve a pedir y se
 * niega si no está vacía. Con dos copias de la regla, la ficha diría «listo» y la
 * acción diría «no», o al revés.
 *
 * Los bloqueos son los que harían **falsa** la declaración, no los que la harían
 * mejorable:
 *
 * - El sistema tiene que estar bajo el ENS y valorado: sin categoría no hay qué
 *   declarar.
 * - La categoría tiene que ser básica. Media y alta se certifican.
 * - Tiene que haber una **autoevaluación cerrada**: cerrarla es lo que congela su
 *   checklist, y una abierta todavía puede cambiar debajo de la declaración.
 * - Con checklist y **sin puntos pendientes**: `pendiente` no es `conforme`, y una
 *   declaración sobre medidas sin revisar afirma lo que nadie comprobó.
 * - **Una autoevaluación que no respalde ya la declaración vigente**: renovar es
 *   volver a comprobar, no volver a firmar lo mismo.
 * - **Sin no conformidades mayores abiertas.** Una mayor es, por definición, un
 *   incumplimiento de la medida; declarar conforme el sistema con una abierta es
 *   declarar lo contrario de lo que dice la propia autoevaluación. Las menores y
 *   las observaciones no bloquean: se declaran con su plan de tratamiento, y el
 *   documento las cuenta.
 */
final class RequisitosDeDeclaracion
{
    public const CODIGO_MARCO_ENS = 'ENS-RD311-2022';

    public function para(Sistema $sistema): ComprobacionPrevia
    {
        $sistema->loadMissing(['marco', 'valoraciones']);

        if ($sistema->marco->codigo !== self::CODIGO_MARCO_ENS) {
            return new ComprobacionPrevia(null, null, ['El sistema no está declarado bajo el ENS.']);
        }

        $categoria = $sistema->categoria();

        if ($categoria === null) {
            return new ComprobacionPrevia(null, null, [
                'El sistema no tiene valoradas las cinco dimensiones, así que no hay categoría que declarar.',
            ]);
        }

        if (! ViaConformidad::paraCategoria($categoria)->implementada()) {
            return new ComprobacionPrevia(null, $categoria, [
                "El sistema es de categoría {$categoria->etiqueta()}: no se declara, se certifica con una "
                .'auditoría de una entidad acreditada por ENAC. Statera modela esa vía pero todavía no la recorre.',
            ]);
        }

        $bloqueos = [];

        if (Conformidad::query()->where('sistema_id', $sistema->id)->enPreparacion()->exists()) {
            $bloqueos[] = 'Ya hay una declaración en preparación para este sistema.';
        }

        $autoevaluacion = $this->ultimaAutoevaluacionCerrada($sistema);

        if ($autoevaluacion === null) {
            $bloqueos[] = 'No hay ninguna autoevaluación cerrada de este sistema. La declaración se apoya en '
                .'ella: ciérrala antes, que es lo que congela su checklist.';

            return new ComprobacionPrevia(null, $categoria, $bloqueos);
        }

        /*
         * **La renovación necesita una autoevaluación nueva.** Sin esto, la ficha
         * ofrecía «Iniciar la renovación» recién declarada, sobre la misma
         * autoevaluación que ya respalda la vigente: una segunda declaración
         * idéntica que retiraría la primera y reiniciaría los dos años sin haber
         * vuelto a comprobar nada. Lo destapó el recorrido en el navegador.
         */
        $yaRespalda = Conformidad::query()
            ->where('sistema_id', $sistema->id)
            ->where('auditoria_id', $autoevaluacion->id)
            ->whereIn('estado', [EstadoConformidad::Declarada->value, EstadoConformidad::Publicada->value])
            ->exists();

        if ($yaRespalda) {
            $bloqueos[] = "La autoevaluación {$autoevaluacion->codigo} ya respalda la declaración vigente. "
                .'Renovarla exige una autoevaluación nueva, cerrada después.';
        }

        $puntos = $autoevaluacion->puntos()->count();
        $pendientes = $autoevaluacion->puntos()->where('resultado', ResultadoPunto::Pendiente->value)->count();

        if ($puntos === 0) {
            $bloqueos[] = "La autoevaluación {$autoevaluacion->codigo} no tiene checklist: no hay medidas "
                .'revisadas sobre las que declarar.';
        } elseif ($pendientes > 0) {
            $bloqueos[] = "La autoevaluación {$autoevaluacion->codigo} tiene {$pendientes} de {$puntos} "
                .'medidas sin revisar. Pendiente no es conforme.';
        }

        $mayores = $this->mayoresAbiertas($autoevaluacion);

        if ($mayores > 0) {
            $bloqueos[] = "La autoevaluación {$autoevaluacion->codigo} tiene {$mayores} "
                .($mayores === 1 ? 'no conformidad mayor sin cerrar' : 'no conformidades mayores sin cerrar')
                .'. Una mayor es un incumplimiento de la medida, y declarar conforme el sistema con ella '
                .'abierta contradice la propia autoevaluación.';
        }

        return new ComprobacionPrevia($autoevaluacion, $categoria, $bloqueos);
    }

    /**
     * La autoevaluación que respaldaría la declaración: la última cerrada.
     *
     * La última y no una a elegir: declarar desde la del año pasado habiendo una
     * más reciente sería escoger la que salió mejor.
     */
    public function ultimaAutoevaluacionCerrada(Sistema $sistema): ?Auditoria
    {
        return Auditoria::query()
            ->where('sistema_id', $sistema->id)
            ->where('tipo', TipoAuditoria::Autoevaluacion->value)
            ->cerradas()
            ->orderByDesc('fecha_cierre')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Hallazgos de no conformidad mayor sin tratar o con el tratamiento abierto.
     *
     * «Cerrada» en el sentido de `EstadoNoConformidad::esCerrada()`, que incluye la
     * anulada: anular es decidir que aquello no era una no conformidad, y eso es
     * tratarla con su motivo escrito.
     */
    public function mayoresAbiertas(Auditoria $auditoria): int
    {
        return Hallazgo::query()
            ->where('auditoria_id', $auditoria->id)
            ->where('tipo', TipoHallazgo::NcMayor->value)
            ->where(static function (Builder $consulta): void {
                /** @var Builder<Hallazgo> $consulta */
                $consulta->whereDoesntHave('noConformidad')
                    ->orWhereHas('noConformidad', static function (Builder $nc): void {
                        /** @var Builder<NoConformidad> $nc */
                        $nc->abiertas();
                    });
            })
            ->count();
    }
}
