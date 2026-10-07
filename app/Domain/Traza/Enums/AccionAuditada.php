<?php

declare(strict_types=1);

namespace App\Domain\Traza\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Qué le pasó a la entidad. Lo que interesa de un log de cumplimiento es si algo
 * nació, cambió o desapareció, y el detalle vive en los valores anterior y
 * nuevo.
 *
 * **Y cuatro casos que no son de fila**, que llegaron con el § 4.19. El rol de
 * una cuenta vive en una pivote de spatie sin modelo propio, así que cambiarlo
 * no dispara ningún `updated`. Y una sesión no es una fila de nada: es lo que el
 * § 6 llama «registro de sesiones». Los cuatro se escriben a mano desde el
 * dominio de cuentas, nunca desde `RegistraTraza`.
 *
 * Y dos más con el punto 44: la entrada y la salida de quien administra la
 * plataforma como soporte. El cliente tiene que leer en su propia traza
 * cuándo entró alguien de fuera y cuándo se fue.
 */
#[TypeScript]
enum AccionAuditada: string
{
    case Creado = 'creado';
    case Actualizado = 'actualizado';
    case Eliminado = 'eliminado';
    case RolCambiado = 'rol_cambiado';
    case InicioSesion = 'inicio_sesion';
    case CierreSesion = 'cierre_sesion';
    case IntentoFallido = 'intento_fallido';
    case SoporteEntrada = 'soporte_entrada';
    case SoporteSalida = 'soporte_salida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::Actualizado => 'Actualizado',
            self::Eliminado => 'Eliminado',
            self::RolCambiado => 'Rol cambiado',
            self::InicioSesion => 'Inicio de sesión',
            self::CierreSesion => 'Cierre de sesión',
            self::IntentoFallido => 'Intento de acceso fallido',
            self::SoporteEntrada => 'Entrada de soporte',
            self::SoporteSalida => 'Salida de soporte',
        };
    }

    /*
     * El tono y el icono llegaron con la ficha de una cuenta, que es la primera
     * pantalla que enseña la traza: su actividad reciente se pinta con
     * `HistoricoTransiciones`. Ninguno gasta el rojo; un intento fallido suelto
     * es una contraseña mal tecleada, no un incidente.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Creado => 'planificado',
            self::Actualizado, self::RolCambiado => 'en_progreso',
            self::Eliminado, self::CierreSesion => 'no_aplica',
            self::InicioSesion => 'implantado',
            self::IntentoFallido => 'no_iniciado',
            self::SoporteEntrada => 'en_revision',
            self::SoporteSalida => 'no_aplica',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Creado => 'PenLine',
            self::Actualizado => 'CircleDotDashed',
            self::Eliminado => 'Trash2',
            self::RolCambiado => 'ArrowRightLeft',
            self::InicioSesion => 'LogIn',
            self::CierreSesion => 'LogOut',
            self::IntentoFallido => 'Lock',
            self::SoporteEntrada => 'Eye',
            self::SoporteSalida => 'X',
        };
    }
}
