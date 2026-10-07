<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al responsable de seguridad de que alguien de la plataforma ha entrado
 * por la ventana de soporte (punto 44).
 *
 * **Lleva escalares y ningún modelo**, como la invitación: va en cola, y un
 * modelo se reconsultaría al deserializarse sin contexto de organización.
 */
final class EntradaDeSoporte extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $administrador,
        private readonly string $organizacion,
        private readonly string $hasta,
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
        return (new MailMessage)
            ->subject('Acceso de soporte a Statera — '.$this->organizacion)
            ->greeting('Alguien de soporte ha entrado')
            ->line("{$this->administrador}, de la plataforma, ha entrado en {$this->organizacion} por el acceso de soporte que abristeis.")
            ->line("Entra sólo en lectura: puede ver, pero no cambiar nada. El acceso se cierra solo el {$this->hasta}, y podéis cerrarlo antes desde la ficha de la organización.")
            ->line('La entrada y la salida quedan en vuestra traza.')
            ->salutation('Statera — un producto de RM Technology');
    }
}
