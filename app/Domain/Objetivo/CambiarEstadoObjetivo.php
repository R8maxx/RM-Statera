<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Excepciones\TransicionDeObjetivoNoPermitida;
use App\Domain\Objetivo\Models\Objetivo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de estado de un objetivo, con su histórico, su fecha de cierre y su firma.
 *
 * **Las fechas y la firma las pone el dominio y nunca el formulario**, igual que
 * en `CambiarEstadoNoConformidad` y por el mismo motivo: la base acopla `estado`
 * con `fecha_cierre`, con `fecha_objetivo` y con `aprobado_en` mediante tres
 * `CHECK`, así que dejar que las escriba quien llame significa que el día que un
 * objetivo se apruebe desde un importador la inserción falle con un error de
 * restricción que no menciona la palabra «firma».
 *
 * **Aprobar es el gesto que compromete, y por eso exige plazo.** La cláusula 6.2
 * pide por escrito para cuándo, y un objetivo aprobado sin fecha es una consigna.
 * Se comprueba aquí y no sólo en el `FormRequest` porque la regla vale también
 * para un importador — mismo criterio que el motivo de `descartada` en tareas.
 *
 * **Tres transiciones exigen motivo escrito:**
 *
 * - `retirado` — «esto ya no lo perseguimos». Mismo criterio que `descartada` en
 *   tareas, `anulada` en una no conformidad y `rechazado` en un documento.
 * - `no_alcanzado` — **y ésta es la que paga el módulo**: «por qué no se alcanzó»
 *   es literalmente lo que la revisión por la dirección pregunta del año que
 *   termina, y sin texto el acta diría «tres de cinco» sin poder explicar ni uno.
 * - **la vuelta a `aprobado` desde algo ya cerrado** — reabrir un objetivo es
 *   darle otro plazo, y sin nota el histórico enseñaría un ir y venir de estados
 *   sin explicar ninguno.
 *
 * Y una consecuencia de volver a `propuesto`: **suelta la firma entera**. Un
 * objetivo que vuelve al borrador ya no está aprobado, y dejarle puestos el
 * firmante y la fecha sería enseñar una aprobación que ya no consta. No se pierde
 * nada: el histórico conserva cuándo se firmó y quién.
 */
final class CambiarEstadoObjetivo
{
    public function __construct(private readonly RegistroTransicionesObjetivo $registro) {}

    public function __invoke(
        Objetivo $objetivo,
        EstadoObjetivo $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): Objetivo {
        $actual = $objetivo->estado;

        if ($actual === $nuevo) {
            return $objetivo;
        }

        if (! $actual->permite($nuevo)) {
            throw new TransicionDeObjetivoNoPermitida($actual, $nuevo);
        }

        $this->exigirPlazo($objetivo, $actual, $nuevo);
        $this->exigirMotivo($actual, $nuevo, $nota);

        return DB::transaction(function () use ($objetivo, $actual, $nuevo, $usuario, $nota): Objetivo {
            $objetivo->update($this->columnas($objetivo, $nuevo, $usuario, $nota));

            $this->registro->registrar($objetivo, $actual, $nuevo, $usuario, $nota);

            return $objetivo->refresh();
        });
    }

    /**
     * Comprometerse sin decir para cuándo no es comprometerse (6.2, planificación d).
     *
     * @throws TransicionDeObjetivoNoPermitida
     */
    private function exigirPlazo(Objetivo $objetivo, EstadoObjetivo $actual, EstadoObjetivo $nuevo): void
    {
        if (! $nuevo->esComprometido() || $objetivo->fecha_objetivo !== null) {
            return;
        }

        throw new TransicionDeObjetivoNoPermitida(
            $actual,
            $nuevo,
            'Un objetivo aprobado tiene que decir para cuándo: la cláusula 6.2 lo pide por escrito.',
        );
    }

    /**
     * @throws TransicionDeObjetivoNoPermitida
     */
    private function exigirMotivo(EstadoObjetivo $actual, EstadoObjetivo $nuevo, ?string $nota): void
    {
        if ($nota !== null && trim($nota) !== '') {
            return;
        }

        if ($nuevo === EstadoObjetivo::Retirado) {
            throw new TransicionDeObjetivoNoPermitida(
                $actual,
                $nuevo,
                'Retirar un objetivo exige decir por qué: es una decisión que el auditor puede cuestionar.',
            );
        }

        if ($nuevo === EstadoObjetivo::NoAlcanzado) {
            throw new TransicionDeObjetivoNoPermitida(
                $actual,
                $nuevo,
                'Dar un objetivo por no alcanzado exige decir por qué: es lo que pregunta la revisión por la dirección.',
            );
        }

        if ($nuevo === EstadoObjetivo::Aprobado && $actual->esCerrado()) {
            throw new TransicionDeObjetivoNoPermitida(
                $actual,
                $nuevo,
                'Reabrir un objetivo exige decir qué cambia: sin eso, el histórico no explica el ir y venir.',
            );
        }
    }

    /**
     * Las columnas que dependen del estado. Ver la cabecera.
     *
     * @return array<string, mixed>
     */
    private function columnas(Objetivo $objetivo, EstadoObjetivo $nuevo, ?User $usuario, ?string $nota): array
    {
        $hoy = Carbon::today();

        return match ($nuevo) {
            // Vuelve al borrador: deja de estar firmado y deja de estar cerrado.
            EstadoObjetivo::Propuesto => [
                'estado' => $nuevo->value,
                'fecha_cierre' => null,
                'aprobado_por_id' => null,
                'aprobado_en' => null,
                'nota_aprobacion' => null,
            ],

            /*
             * La firma sólo se estampa si no había una: reabrir un objetivo que
             * ya se aprobó en marzo no reescribe quién lo firmó ni cuándo. Es el
             * mismo criterio que `RegistrarMedicion` con el objetivo sellado —
             * corregir algo de hoy no puede cambiar lo que se decidió entonces.
             */
            EstadoObjetivo::Aprobado => [
                'estado' => $nuevo->value,
                'fecha_cierre' => null,
                'aprobado_por_id' => $objetivo->aprobado_por_id ?? $usuario?->id,
                'aprobado_en' => $objetivo->aprobado_en ?? Carbon::now(),
                'nota_aprobacion' => $objetivo->nota_aprobacion ?? $nota,
            ],

            EstadoObjetivo::Alcanzado, EstadoObjetivo::NoAlcanzado, EstadoObjetivo::Retirado => [
                'estado' => $nuevo->value,
                'fecha_cierre' => $hoy,
            ],
        };
    }
}
