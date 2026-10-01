<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion\Enums;

/**
 * Por dónde se comunica: el «cómo» de la cláusula 7.4.
 *
 * Vale para lo emitido y para lo recibido: una queja también entra por algún
 * sitio, y saber por cuál es lo que dice dónde hay que mirar la próxima vez.
 */
enum CanalComunicacion: string
{
    case Correo = 'correo';
    case Reunion = 'reunion';
    case Intranet = 'intranet';
    case Formacion = 'formacion';
    case Documento = 'documento';
    case Web = 'web';
    case Telefono = 'telefono';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Correo => 'Correo electrónico',
            self::Reunion => 'Reunión',
            self::Intranet => 'Intranet o tablón',
            self::Formacion => 'Sesión de formación',
            self::Documento => 'Documento o informe',
            self::Web => 'Web pública',
            self::Telefono => 'Teléfono',
            self::Otro => 'Otro',
        };
    }
}
