<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\AnalisisContexto;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Resuelve cuál es el análisis vigente y cuál el borrador abierto.
 *
 * **El borrador se estrena solo, al registrar la primera cuestión.** Es
 * deliberado: obligar a «abrir un análisis» antes de poder escribir una debilidad
 * pone un trámite delante del primer minuto de uso, y lo que la gente hace
 * entonces es apuntar el DAFO en otro sitio. Aprobar sí es un acto explícito,
 * porque es el que congela y el que firma la dirección.
 *
 * **Sin caché de instancia**, a diferencia de `MetodologiaVigente`. Aquélla se
 * registra como `scoped` porque se consulta muchas veces por petición y arrastrarla
 * entre organizaciones en un worker valoraría los riesgos de un cliente con la
 * escala de otro. Aquí se consulta una o dos veces por petición y el riesgo no
 * compensa: dos consultas baratas se pagan mejor que una clase con estado que
 * alguien tiene que acordarse de invalidar.
 */
final class AnalisisEnCurso
{
    /** El aprobado que aún no ha sido sustituido. Nulo mientras no haya ninguno. */
    public function vigente(): ?AnalisisContexto
    {
        return AnalisisContexto::query()->vigente()->first();
    }

    /** El que se está preparando. Nulo si no hay nada abierto. */
    public function borrador(): ?AnalisisContexto
    {
        return AnalisisContexto::query()->borrador()->first();
    }

    /**
     * El borrador abierto, estrenándolo si no hay ninguno.
     *
     * Lo llaman todas las escrituras del módulo: dar de alta una cuestión, retirar
     * una parte interesada, cambiar un requisito. Así, el primer gesto de trabajo
     * abre la revisión sin que nadie tenga que saber que existe el concepto.
     */
    public function borradorObligatorio(?User $usuario = null): AnalisisContexto
    {
        $borrador = $this->borrador();

        if ($borrador instanceof AnalisisContexto) {
            return $borrador;
        }

        $analisis = AnalisisContexto::query()->create([
            'fecha_analisis' => Carbon::today(),
            'creado_por_id' => $usuario?->id,
        ]);

        /*
         * El `refresh()` de siempre: `estado` lo pone la base con su valor por
         * defecto, así que la instancia recién creada llega **sin estado** y lo
         * primero que lo lea revienta con un «call to a member function on null»
         * que no menciona la palabra «estado». Es lo mismo que le pasó a
         * `CrearTarea`, a `RegistrarAuditoria` y a `GenerarDocumento::encolar()`.
         */
        $analisis->refresh();

        return $analisis;
    }
}
