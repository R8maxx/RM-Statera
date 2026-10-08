<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Notifications;

use App\Domain\Plataforma\Enums\HitoSuscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al responsable de seguridad de que la suscripción vence o ha vencido
 * (punto 46).
 *
 * **Lleva escalares y ningún modelo**, como el resto de correos: va en cola, y
 * un modelo se reconsultaría al deserializarse sin contexto de organización.
 */
final class VencimientoDeSuscripcion extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly HitoSuscripcion $hito,
        private readonly string $organizacion,
        private readonly string $plan,
        private readonly string $venceEl,
        private readonly ?string $escribeHasta,
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
            ->subject($this->hito->asunto().' — '.$this->organizacion)
            ->greeting($this->hito->asunto());

        $correo = match ($this->hito) {
            HitoSuscripcion::Faltan30, HitoSuscripcion::Faltan7, HitoSuscripcion::Falta1 => $correo
                ->line("El plan {$this->plan} de {$this->organizacion} vence el {$this->venceEl}.")
                ->line('Si no se renueva, Statera seguirá funcionando con normalidad unos días y después pasará a sólo lectura: se podrá ver y descargar todo, pero no cambiar nada.'),
            HitoSuscripcion::EnGracia => $correo
                ->line("El plan {$this->plan} de {$this->organizacion} venció el {$this->venceEl}.")
                ->line("Podéis seguir trabajando con normalidad hasta el {$this->escribeHasta}. Después, Statera pasará a sólo lectura hasta que se renueve."),
            HitoSuscripcion::SoloLectura => $correo
                ->line("El plan {$this->plan} de {$this->organizacion} venció y el periodo de gracia ha terminado.")
                ->line('Statera está en sólo lectura: podéis ver y descargar todo lo que hay, pero no cambiar nada hasta que se renueve. No se ha perdido ningún dato.'),
        };

        return $correo
            ->line('Para renovar, habla con quien os dio de alta en Statera.')
            ->salutation('Statera — un producto de RM Technology');
    }
}
