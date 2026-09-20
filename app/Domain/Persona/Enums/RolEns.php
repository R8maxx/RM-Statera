<?php

declare(strict_types=1);

namespace App\Domain\Persona\Enums;

/**
 * Los cinco roles que el ENS exige designar, y la incompatibilidad que la
 * especificación pide **impedir**. Cláusula 5.3 de ISO y CCN-STIC 801.
 *
 * **OJO: esto NO son los roles de `Domain\Autorizacion\Enums\Rol`**, que deciden
 * quién puede tocar qué dentro de Statera. Aquéllos son permisos de una
 * herramienta; éstos son cargos de la organización, se designan por escrito y el
 * auditor pide el nombramiento. Una persona puede ser responsable de seguridad del
 * sistema sin tener siquiera cuenta en Statera, y quien tiene el rol
 * `ResponsableSeguridad` de la aplicación puede no ser quien lo es de verdad. El
 * aviso ya estaba escrito en la cabecera de `Permiso` antes de que este módulo
 * existiera.
 *
 * **La incompatibilidad es una sola y viene de la guía**: el responsable de
 * seguridad y el responsable del sistema **no pueden ser la misma persona** en el
 * mismo sistema, porque quien decide qué protección hace falta no puede ser quien
 * responde de haberla puesto. Las demás combinaciones son legítimas y frecuentes en
 * una organización pequeña.
 */
enum RolEns: string
{
    case ResponsableInformacion = 'responsable_informacion';
    case ResponsableServicio = 'responsable_servicio';
    case ResponsableSeguridad = 'responsable_seguridad';
    case ResponsableSistema = 'responsable_sistema';
    case AdministradorSeguridad = 'administrador_seguridad';

    /**
     * Con qué roles no puede coincidir en la misma persona y el mismo sistema.
     *
     * Devuelve una lista y no un solo caso porque la guía puede añadir
     * incompatibilidades y un `?self` obligaría a rehacer la firma; hoy tiene como
     * mucho un elemento.
     *
     * **El administrador de la seguridad no entra**, aunque sea tentador: la guía
     * lo pone bajo la dirección del responsable de seguridad, no en conflicto con
     * él, y en una organización pequeña es habitual que coincidan. Inventarse una
     * incompatibilidad que la guía no pone bloquea un caso legítimo.
     *
     * @return list<self>
     */
    public function incompatibleCon(): array
    {
        return match ($this) {
            self::ResponsableSeguridad => [self::ResponsableSistema],
            self::ResponsableSistema => [self::ResponsableSeguridad],
            self::ResponsableInformacion, self::ResponsableServicio, self::AdministradorSeguridad => [],
        };
    }

    public function chocaCon(self $otro): bool
    {
        return in_array($otro, $this->incompatibleCon(), true);
    }

    /**
     * Si de este rol hay **uno solo** vigente por sistema.
     *
     * Lo impone además un índice único parcial. Responsable de la información y
     * responsable del servicio pueden ser varios —uno por cada información tratada
     * y por cada servicio prestado—, así que exigirles unicidad sería inventarse
     * una restricción que la guía no pone.
     */
    public function esUnicoPorSistema(): bool
    {
        return match ($this) {
            self::ResponsableSeguridad, self::ResponsableSistema, self::AdministradorSeguridad => true,
            self::ResponsableInformacion, self::ResponsableServicio => false,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::ResponsableInformacion => 'Responsable de la información',
            self::ResponsableServicio => 'Responsable del servicio',
            self::ResponsableSeguridad => 'Responsable de seguridad',
            self::ResponsableSistema => 'Responsable del sistema',
            self::AdministradorSeguridad => 'Administrador de la seguridad del sistema',
        };
    }

    /** La forma corta, para un badge en una tabla. */
    public function etiquetaCorta(): string
    {
        return match ($this) {
            self::ResponsableInformacion => 'Información',
            self::ResponsableServicio => 'Servicio',
            self::ResponsableSeguridad => 'Seguridad',
            self::ResponsableSistema => 'Sistema',
            self::AdministradorSeguridad => 'Admin. seguridad',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::ResponsableInformacion => 'Database',
            self::ResponsableServicio => 'Globe',
            self::ResponsableSeguridad => 'ShieldCheck',
            self::ResponsableSistema => 'Server',
            self::AdministradorSeguridad => 'Lock',
        };
    }

    /**
     * **Ninguno gasta rojo**, y conviene decirlo: un rol no es un estado. Lo que sí
     * puede ir mal es que un rol obligatorio esté **sin designar**, y eso lo señala
     * el registro, no el badge del rol.
     *
     * Los dos que se separan son los dos incompatibles, porque son los que hay que
     * poder distinguir de un vistazo en la ficha de una persona.
     */
    public function tono(): string
    {
        return match ($this) {
            self::ResponsableSeguridad => 'en_revision',
            self::ResponsableSistema => 'planificado',
            self::AdministradorSeguridad => 'en_progreso',
            self::ResponsableInformacion, self::ResponsableServicio => 'no_iniciado',
        };
    }
}
