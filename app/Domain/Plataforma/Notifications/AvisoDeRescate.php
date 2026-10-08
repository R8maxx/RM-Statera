<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de que la plataforma ha rescatado una cuenta de la organización (punto
 * 51): a la cuenta afectada y a los responsables de seguridad. Si lo hizo una
 * sola persona, porque sólo hay una en Administración, se dice.
 *
 * **Lleva escalares y ningún modelo**, como el resto de correos en cola.
 */
final class AvisoDeRescate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $que,
        private readonly string $organizacion,
        private readonly string $cuenta,
        private readonly bool $sinSegundaPersona,
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
            ->subject('Statera: cambio en una cuenta de '.$this->organizacion)
            ->greeting('La plataforma ha cambiado una cuenta')
            ->line("{$this->que}: {$this->cuenta}, en {$this->organizacion}.")
            ->line('Se hizo a petición vuestra, después de comprobar quién lo pedía. Queda en vuestra traza.');

        if ($this->sinSegundaPersona) {
            $correo->line('Lo ha hecho una sola persona de la plataforma, porque no había otra que lo revisara. Si no lo pedisteis, avisadnos de inmediato.');
        } else {
            $correo->line('Si no lo pedisteis, avisadnos de inmediato.');
        }

        return $correo->salutation('Statera — un producto de RM Technology');
    }
}
