<?php

declare(strict_types=1);

namespace App\Domain\Usuario\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El correo con el que alguien entra por primera vez.
 *
 * **Lleva escalares y ningún modelo**, igual que `VencimientosDelDia`: va en
 * cola, y un modelo se reconsultaría al deserializarse sin contexto de
 * organización. El enlace se construye al invitar, en la petición, y viaja ya
 * hecho.
 *
 * Nadie escribe la contraseña de otro: el enlace lleva a fijarla, y el segundo
 * factor lo pide `ExigirDosFactores` la primera vez que la cuenta vaya a
 * escribir.
 */
final class InvitacionACuenta extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $enlace,
        private readonly string $organizacion,
        private readonly string $rol,
        private readonly int $dias,
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
            ->subject('Tu cuenta en Statera — '.$this->organizacion)
            ->greeting('Tienes una cuenta en Statera')
            ->line("Te han dado de alta en {$this->organizacion} con el rol de {$this->rol}.")
            ->line('Para entrar, fija tu contraseña desde este enlace. Caduca en '.$this->dias.' días y sólo sirve una vez.')
            ->action('Fijar la contraseña', $this->enlace)
            ->line('Si no esperabas este correo, no hagas nada: sin contraseña la cuenta no entra.')
            ->salutation('Statera — un producto de RM Technology');
    }
}
