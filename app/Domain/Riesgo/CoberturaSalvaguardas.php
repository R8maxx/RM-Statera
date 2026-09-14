<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Riesgo\Models\Riesgo;

/**
 * Qué respaldo real tiene el riesgo residual que alguien ha declarado.
 *
 * **Nada de lo que se calcula aquí se almacena, y eso es la decisión central del
 * módulo.** El residual lo declara el analista y lo aprueba el propietario del
 * riesgo (ISO 27001, 6.1.3 f); esto se calcula al vuelo y se enseña al lado, igual
 * que la valoración efectiva de un activo se enseña junto a la propia sin
 * sobrescribirla nunca.
 *
 * El motivo de no derivarlo: **no existe ninguna función publicada** de (estado,
 * nivel de madurez) a riesgo residual. Cualquiera que inventáramos —«implantado y
 * L4 baja el impacto un escalón»— sería una opinión de la herramienta disfrazada
 * de cálculo, y además nadie podría aprobarla, porque no se aprueba lo que dedujo
 * la máquina.
 *
 * El invariante 4 —«la aplicabilidad se deriva, no se selecciona»— no aplica aquí
 * y conviene tenerlo claro: aquél es una derivación **legal**, con una respuesta
 * correcta escrita en el BOE, y dejar elegir a mano es dejar que alguien se quede
 * fuera de conformidad sin enterarse. Esto no tiene BOE.
 *
 * Lo que sí hace la herramienta es **señalar la contradicción** cuando el residual
 * declarado baja del intrínseco y las salvaguardas están sin empezar. Es el mismo
 * papel que hace `Activo::esperaBorradoSeguro()` con un equipo retirado sin
 * constancia de borrado: no corrige el dato, lo pone delante.
 */
final class CoberturaSalvaguardas
{
    /**
     * @return array{
     *     total: int,
     *     implantadas: int,
     *     enProgreso: int,
     *     sinEmpezar: int,
     *     madurezMedia: ?float,
     *     madurezEvaluadas: int,
     * }
     */
    public function de(Riesgo $riesgo): array
    {
        $salvaguardas = $riesgo->salvaguardas;

        $madureces = $salvaguardas
            ->map(static fn (Implantacion $implantacion): ?int => $implantacion->nivel_madurez?->valor())
            ->filter(static fn (?int $peso): bool => $peso !== null)
            ->values();

        return [
            'total' => $salvaguardas->count(),
            'implantadas' => $salvaguardas->where('estado', EstadoImplantacion::Implantado)->count(),
            'enProgreso' => $salvaguardas->where('estado', EstadoImplantacion::EnProgreso)->count(),
            'sinEmpezar' => $salvaguardas->where('estado', EstadoImplantacion::NoIniciado)->count(),

            /*
             * La media va SIEMPRE con su denominador. Una madurez media de 3,5
             * sobre dos salvaguardas de diez no dice lo mismo que sobre las diez,
             * y sin el denominador las dos se leen igual.
             */
            'madurezMedia' => $madureces->isEmpty() ? null : round((float) $madureces->avg(), 1),
            'madurezEvaluadas' => $madureces->count(),
        ];
    }

    /**
     * La contradicción: se declara una rebaja del riesgo y ninguna salvaguarda
     * está implantada.
     *
     * Incluye el caso más crudo, que es no haber vinculado ninguna. «El riesgo es
     * bajo porque tenemos controles» sin un solo control apuntado es exactamente
     * lo que un auditor va a pedir que se enseñe.
     */
    public function sinRespaldo(Riesgo $riesgo): bool
    {
        $vigente = $riesgo->valoracionVigente;

        if ($vigente === null || ! $vigente->rebajaElRiesgo()) {
            return false;
        }

        return $riesgo->salvaguardas->where('estado', EstadoImplantacion::Implantado)->isEmpty();
    }

    /**
     * Lo que se congela en `riesgo_valoraciones.salvaguardas`.
     *
     * **Sin esto la fila histórica miente** en cuanto una implantación cambie de
     * estado el mes que viene: la valoración de marzo diría que se apoyaba en tres
     * controles implantados cuando en marzo dos estaban a medias. Es el mismo
     * motivo por el que el `.docx` de una versión emitida se construye desde su
     * instantánea y no desde una consulta nueva.
     *
     * @return list<array{id: int, requisito: string, estado: string, madurez: ?string}>
     */
    public function instantanea(Riesgo $riesgo): array
    {
        return $riesgo->salvaguardas
            ->map(static fn (Implantacion $implantacion): array => [
                'id' => $implantacion->id,
                'requisito' => (string) $implantacion->requisito?->codigo,
                'estado' => $implantacion->estado->value,
                'madurez' => $implantacion->nivel_madurez?->value,
            ])
            ->values()
            ->all();
    }
}
