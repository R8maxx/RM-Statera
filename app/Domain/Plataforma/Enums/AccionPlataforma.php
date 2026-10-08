<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Qué hizo un administrador de la plataforma (punto 41).
 *
 * Es la traza de quien **no pertenece a ninguna organización**. La de cada
 * tenant —`eventos_auditoria`— vive bajo RLS y exige un dueño, así que la
 * entrada, la salida y el alta de un administrador no tendrían dónde
 * escribirse. Lo que un administrador hace **dentro** de un tenant (darlo de
 * alta, entrar como soporte) va además a la traza de ese tenant: el cliente
 * tiene que poder verlo en la suya sin pedírnoslo.
 *
 * El `CHECK` de la columna se construye desde estos casos en la migración, así
 * que un caso nuevo necesita su migración que lo amplíe.
 */
#[TypeScript]
enum AccionPlataforma: string
{
    case AdministradorCreado = 'administrador_creado';
    case AdministradorActivado = 'administrador_activado';
    case InicioSesion = 'inicio_sesion';
    case CierreSesion = 'cierre_sesion';
    case IntentoFallido = 'intento_fallido';
    case OrganizacionAlta = 'organizacion_alta';
    case SuscripcionCambiada = 'suscripcion_cambiada';
    case PlanGuardado = 'plan_guardado';
    case SoporteEntrada = 'soporte_entrada';
    case SoporteSalida = 'soporte_salida';
    case AdministradorPromovido = 'administrador_promovido';
    case InvitacionReenviada = 'invitacion_reenviada';
    case OrganizacionBaja = 'organizacion_baja';
    case OrganizacionReactivada = 'organizacion_reactivada';
    case AvisoVencimientoEnviado = 'aviso_vencimiento_enviado';
    case AdministradorPerfilCambiado = 'administrador_perfil_cambiado';
    case AdministradorRetirado = 'administrador_retirado';
    case PlanContratado = 'plan_contratado';
    case RescateSolicitado = 'rescate_solicitado';
    case RescateEjecutado = 'rescate_ejecutado';
    case RescateRechazado = 'rescate_rechazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AdministradorCreado => 'Administrador creado',
            self::AdministradorActivado => 'Administrador activado',
            self::InicioSesion => 'Inicio de sesión',
            self::CierreSesion => 'Cierre de sesión',
            self::IntentoFallido => 'Intento de acceso fallido',
            self::OrganizacionAlta => 'Alta de organización',
            self::SuscripcionCambiada => 'Suscripción cambiada',
            self::PlanGuardado => 'Plan guardado',
            self::SoporteEntrada => 'Entrada como soporte',
            self::SoporteSalida => 'Salida del soporte',
            self::AdministradorPromovido => 'Cuenta promovida a administradora',
            self::InvitacionReenviada => 'Invitación reenviada',
            self::OrganizacionBaja => 'Organización dada de baja',
            self::OrganizacionReactivada => 'Organización reactivada',
            self::AvisoVencimientoEnviado => 'Aviso de vencimiento enviado',
            self::AdministradorPerfilCambiado => 'Perfil de administrador cambiado',
            self::AdministradorRetirado => 'Administrador retirado',
            self::PlanContratado => 'Plan contratado por la organización',
            self::RescateSolicitado => 'Rescate de cuenta solicitado',
            self::RescateEjecutado => 'Rescate de cuenta ejecutado',
            self::RescateRechazado => 'Rescate de cuenta rechazado',
        };
    }
}
