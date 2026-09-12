<?php

declare(strict_types=1);

namespace App\Domain\Aviso\Notifications;

use App\Domain\Aviso\Vencimiento;
use App\Domain\Aviso\Vencimientos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El resumen diario de lo que vence: pruebas que caducan y trabajo que no se ha
 * hecho.
 *
 * **Un resumen, no una alerta por cosa.** Dice cómo está el día de hoy, así que
 * repetirlo mañana no es spam ni hace falta una tabla de «ya avisado» para no
 * duplicar: si algo sigue caducado, sigue saliendo, que es justo lo que se
 * quiere. Y si no hay nada que decir, no se envía —un correo diario que casi
 * siempre dice «todo en orden» se filtra a una carpeta en dos semanas y deja de
 * verse el día que importa—.
 *
 * **Lleva escalares y ningún modelo.** Va en cola, y una notificación que
 * arrastre Eloquent lo reconsulta al deserializarse, antes de cualquier
 * middleware y sin contexto de organización: no reventaría con un error claro,
 * sencillamente no encontraría nada.
 */
final class VencimientosDelDia extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $organizacion,
        private readonly Vencimientos $vencimientos,
    ) {
        $this->onQueue('notificaciones');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $correo = (new MailMessage)
            ->subject($this->asunto())
            ->greeting('Lo que vence — '.$this->organizacion);

        $correo->line($this->entradilla());

        $this->bloque($correo, 'Evidencias caducadas', $this->vencimientos->evidenciasCaducadas, 'caduca', 'caducó');
        $this->bloque($correo, 'Tareas vencidas', $this->vencimientos->tareasVencidas);
        $this->bloque($correo, 'Evidencias por caducar', $this->vencimientos->evidenciasPorCaducar, 'caduca', 'caducó');
        $this->bloque($correo, 'Tareas por vencer', $this->vencimientos->tareasPorVencer);

        return $correo
            ->action('Abrir Statera', url('/panel'))
            ->salutation('Statera — un producto de RM Technology');
    }

    /**
     * La primera frase dice lo que hay **en este correo**.
     *
     * Explicar que una evidencia caducada no prueba nada en un correo donde
     * todo lo que hay son tareas es ruido, y de los que enseñan a no leer el
     * primer párrafo.
     */
    private function entradilla(): string
    {
        if ($this->vencimientos->pasados() === 0) {
            return 'Nada pasado de fecha todavía. Esto es lo que vence dentro de los próximos '
                .$this->vencimientos->dias.' días.';
        }

        $caducadas = $this->vencimientos->evidenciasCaducadas !== [];
        $vencidas = $this->vencimientos->tareasVencidas !== [];

        return match (true) {
            $caducadas && $vencidas => 'Hay pruebas caducadas y trabajo sin hacer. Una evidencia caducada deja sin prueba al requisito que sostenía, y una tarea vencida es una fecha que se comprometió y pasó.',
            $caducadas => 'Hay evidencias caducadas. Una evidencia caducada no prueba nada: el requisito que sostenía se queda sin prueba hasta que se renueve.',
            default => 'Hay tareas vencidas: fechas que se comprometieron y han pasado.',
        };
    }

    /**
     * El asunto lleva la cifra porque es lo único que se lee sin abrir, y lleva
     * lo pasado de fecha antes que lo inminente porque es lo que decide si se
     * abre hoy o mañana.
     */
    private function asunto(): string
    {
        $pasados = $this->vencimientos->pasados();

        if ($pasados > 0) {
            return sprintf(
                '%s · %d cosa%s pasada%s de fecha',
                $this->organizacion,
                $pasados,
                $pasados === 1 ? '' : 's',
                $pasados === 1 ? '' : 's',
            );
        }

        $total = $this->vencimientos->total();

        return sprintf(
            '%s · %d vencimiento%s en %d días',
            $this->organizacion,
            $total,
            $total === 1 ? '' : 's',
            $this->vencimientos->dias,
        );
    }

    /**
     * @param  list<Vencimiento>  $lineas
     */
    private function bloque(
        MailMessage $correo,
        string $titulo,
        array $lineas,
        string $verbo = 'vence',
        string $pasado = 'venció',
    ): void {
        if ($lineas === []) {
            return;
        }

        $correo->line('**'.$titulo.'**');

        foreach ($lineas as $linea) {
            $correo->line(sprintf(
                '- %s — %s (%s) · %s',
                $linea->titulo,
                $linea->cuando($verbo, $pasado),
                $linea->fecha,
                $linea->responsable ?? 'sin responsable asignado',
            ));
        }
    }
}
