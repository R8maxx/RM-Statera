<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad;

use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Excepciones\TransicionDeNoConformidadNoPermitida;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de estado de una no conformidad, con su histórico y sus dos fechas.
 *
 * **Las fechas las pone el dominio y nunca el formulario**, igual que en
 * `CambiarEstadoTarea` y por el mismo motivo: la base acopla `estado` con
 * `fecha_cierre` y con `fecha_verificacion` mediante dos `CHECK`, así que dejar
 * que las escriba quien llame significa que el día que una no conformidad se
 * cierre desde un importador la inserción falle con un error de restricción que
 * no menciona la palabra «cierre».
 *
 * **Dos transiciones exigen motivo, y las dos por lo mismo**: son decisiones que
 * el auditor puede cuestionar y que sin texto no se pueden defender.
 *
 * - `anulada` — «esto no era una no conformidad». Mismo criterio que `descartada`
 *   en tareas y `rechazado` en documentos, y la regla vive aquí y no en el
 *   `FormRequest` porque vale también para un importador.
 * - **la vuelta a `en_tratamiento` desde algo ya cerrado** — es la verificación
 *   que sale mal, y «qué falló» es precisamente lo que hay que poder leer un año
 *   después. Sin nota, el histórico enseñaría un ir y venir de estados sin
 *   explicar ninguno.
 *
 * Y una consecuencia de esa vuelta: **suelta las tres columnas de la
 * verificación**, no sólo la fecha. Dejar puesto el resultado de una verificación
 * cuya fecha se acaba de borrar sería enseñar una comprobación que ya no consta.
 * No se pierde nada, porque al verificar el texto se copia además a la nota de la
 * transición: el histórico lo conserva aunque la columna se limpie.
 */
final class CambiarEstadoNoConformidad
{
    public function __construct(private readonly RegistroTransicionesNoConformidad $registro) {}

    public function __invoke(
        NoConformidad $noConformidad,
        EstadoNoConformidad $nuevo,
        ?User $usuario = null,
        ?string $nota = null,
    ): NoConformidad {
        $actual = $noConformidad->estado;

        if ($actual === $nuevo) {
            return $noConformidad;
        }

        if (! $actual->permite($nuevo)) {
            throw new TransicionDeNoConformidadNoPermitida($actual, $nuevo);
        }

        $this->exigirMotivo($actual, $nuevo, $nota);

        return DB::transaction(function () use ($noConformidad, $actual, $nuevo, $usuario, $nota): NoConformidad {
            $noConformidad->update($this->columnas($nuevo, $usuario, $nota));

            $this->registro->registrar($noConformidad, $actual, $nuevo, $usuario, $nota);

            return $noConformidad->refresh();
        });
    }

    /**
     * @throws TransicionDeNoConformidadNoPermitida
     */
    private function exigirMotivo(
        EstadoNoConformidad $actual,
        EstadoNoConformidad $nuevo,
        ?string $nota,
    ): void {
        $escrita = $nota !== null && trim($nota) !== '';

        if ($escrita) {
            return;
        }

        if ($nuevo === EstadoNoConformidad::Anulada) {
            throw new TransicionDeNoConformidadNoPermitida(
                $actual,
                $nuevo,
                'Anular una no conformidad exige decir por qué: es una decisión que el auditor puede cuestionar.',
            );
        }

        if ($nuevo === EstadoNoConformidad::EnTratamiento && $actual->esCerrada()) {
            throw new TransicionDeNoConformidadNoPermitida(
                $actual,
                $nuevo,
                'Reabrir el tratamiento exige decir qué falló: es el resultado de la verificación de eficacia.',
            );
        }

        if ($nuevo === EstadoNoConformidad::Verificada) {
            throw new TransicionDeNoConformidadNoPermitida(
                $actual,
                $nuevo,
                'Verificar la eficacia exige decir qué se comprobó; si no, la verificación no se puede defender.',
            );
        }
    }

    /**
     * Las columnas que dependen del estado. Ver la cabecera.
     *
     * @return array<string, mixed>
     */
    private function columnas(EstadoNoConformidad $nuevo, ?User $usuario, ?string $nota): array
    {
        $hoy = Carbon::today();

        return match ($nuevo) {
            EstadoNoConformidad::Abierta, EstadoNoConformidad::EnTratamiento => [
                'estado' => $nuevo->value,
                'fecha_cierre' => null,
                'fecha_verificacion' => null,
                'verificada_por_id' => null,
                'resultado_verificacion' => null,
            ],

            EstadoNoConformidad::Cerrada, EstadoNoConformidad::Anulada => [
                'estado' => $nuevo->value,
                'fecha_cierre' => $hoy,
                'fecha_verificacion' => null,
                'verificada_por_id' => null,
                'resultado_verificacion' => null,
            ],

            /*
             * Quién verificó va a columna y no sólo al histórico: es la firma que
             * el auditor busca al lado de la fecha, y la ficha la enseña sin
             * recorrer las transiciones. Misma decisión que `aprobada_por_id` en
             * una versión de documento.
             */
            EstadoNoConformidad::Verificada => [
                'estado' => $nuevo->value,
                'fecha_verificacion' => $hoy,
                'verificada_por_id' => $usuario?->id,
                'resultado_verificacion' => $nota,
            ],
        };
    }
}
