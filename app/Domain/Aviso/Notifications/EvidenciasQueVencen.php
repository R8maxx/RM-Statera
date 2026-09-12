<?php

declare(strict_types=1);

namespace App\Domain\Aviso\Notifications;

use App\Domain\Aviso\EvidenciaQueVence;
use App\Domain\Aviso\Vencimientos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El resumen diario de lo que vence.
 *
 * **Un resumen, no una alerta por evidencia.** Dice cómo está la cosa hoy, así
 * que repetirlo mañana no es spam ni hace falta una tabla de «ya avisado» para
 * no duplicar: si algo sigue caducado, sigue saliendo, que es justo lo que se
 * quiere. Y si no hay nada que decir, no se envía —un correo diario que casi
 * siempre dice «todo en orden» se filtra a una carpeta en dos semanas y deja de
 * verse el día que importa—.
 *
 * **Lleva escalares y ningún modelo.** Va en cola, y una notificación que
 * arrastre Eloquent lo reconsulta al deserializarse, antes de cualquier
 * middleware y sin contexto de organización: no reventaría con un error claro,
 * sencillamente no encontraría nada.
 */
final class EvidenciasQueVencen extends Notification implements ShouldQueue
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
        $caducadas = count($this->vencimientos->caducadas);

        $correo = (new MailMessage)
            ->subject($this->asunto())
            ->greeting('Evidencias que vencen — '.$this->organizacion);

        $correo->line($caducadas > 0
            ? 'Hay evidencias caducadas. Una evidencia caducada no prueba nada: el requisito que sostenía se queda sin prueba hasta que se renueve.'
            : 'Nada caducado todavía. Esto es lo que vence dentro de los próximos '.$this->vencimientos->dias.' días.');

        $this->bloque($correo, 'Caducadas', $this->vencimientos->caducadas);
        $this->bloque($correo, 'Por caducar', $this->vencimientos->porCaducar);

        return $correo
            ->action('Ver las evidencias', url('/evidencias'))
            ->salutation('Statera — un producto de RM Technology');
    }

    private function asunto(): string
    {
        $caducadas = count($this->vencimientos->caducadas);

        // El asunto lleva la cifra porque es lo único que se lee sin abrir.
        return $caducadas > 0
            ? sprintf('%s · %d evidencia%s caducada%s', $this->organizacion, $caducadas, $caducadas === 1 ? '' : 's', $caducadas === 1 ? '' : 's')
            : sprintf('%s · %d evidencia%s por caducar', $this->organizacion, count($this->vencimientos->porCaducar), count($this->vencimientos->porCaducar) === 1 ? '' : 's');
    }

    /**
     * @param  list<EvidenciaQueVence>  $evidencias
     */
    private function bloque(MailMessage $correo, string $titulo, array $evidencias): void
    {
        if ($evidencias === []) {
            return;
        }

        $correo->line('**'.$titulo.'**');

        foreach ($evidencias as $evidencia) {
            $correo->line(sprintf(
                '- %s — %s (%s) · %s',
                $evidencia->titulo,
                $evidencia->cuando(),
                $evidencia->fecha,
                $evidencia->responsable ?? 'sin responsable asignado',
            ));
        }
    }
}
