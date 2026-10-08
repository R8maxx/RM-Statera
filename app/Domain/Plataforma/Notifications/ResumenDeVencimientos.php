<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El resumen del día para quien administra la plataforma (punto 46): a qué
 * clientes se les ha avisado hoy de su vencimiento, y de qué.
 */
final class ResumenDeVencimientos extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lineas  una por organización: «Nombre: hito»
     */
    public function __construct(private readonly array $lineas)
    {
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
            ->subject('Statera: '.count($this->lineas).' suscripción(es) por vencer o vencidas')
            ->greeting('Avisos de vencimiento de hoy')
            ->line('Se ha avisado a estos clientes:');

        foreach ($this->lineas as $linea) {
            $correo->line('· '.$linea);
        }

        return $correo->salutation('Statera — un producto de RM Technology');
    }
}
