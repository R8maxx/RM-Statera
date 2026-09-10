<?php

declare(strict_types=1);

namespace App\Domain\Activo\Enums;

/**
 * La tipología de activos de MAGERIT, que es la que usa el ENS y la que el
 * auditor espera ver en el inventario.
 *
 * No se inventa una taxonomía propia ni se simplifica a «hardware / software /
 * datos»: el análisis de riesgos posterior se apoya en estos tipos para elegir
 * amenazas del catálogo, y un inventario clasificado de otra manera obliga a
 * traducirlo entero cuando llegue el módulo de riesgos.
 */
enum TipoActivo: string
{
    case Servicios = 'servicios';
    case Datos = 'datos';
    case Software = 'software';
    case Hardware = 'hardware';
    case Comunicaciones = 'comunicaciones';
    case Soportes = 'soportes';
    case EquipamientoAuxiliar = 'equipamiento_auxiliar';
    case Instalaciones = 'instalaciones';
    case Personal = 'personal';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Servicios => 'Servicios',
            self::Datos => 'Datos e información',
            self::Software => 'Software',
            self::Hardware => 'Hardware',
            self::Comunicaciones => 'Redes de comunicaciones',
            self::Soportes => 'Soportes de información',
            self::EquipamientoAuxiliar => 'Equipamiento auxiliar',
            self::Instalaciones => 'Instalaciones',
            self::Personal => 'Personal',
        };
    }

    /**
     * El icono de lucide del tipo, sin el sufijo `Icon`.
     *
     * **No es decoración y no es opcional.** Nueve categorías no caben en el
     * hueco de tono que dejan los estados manteniendo ΔE 6 entre ellas: la peor
     * pareja de `--tipo-*` queda en 5.2 (DESIGN.md §3). El color agrupa; la
     * identidad la carga el icono. Quitarlo deja la distinción por debajo del
     * umbral.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Servicios => 'Globe',
            self::Datos => 'Database',
            self::Software => 'AppWindow',
            self::Hardware => 'HardDrive',
            self::Comunicaciones => 'Network',
            self::Soportes => 'Archive',
            self::EquipamientoAuxiliar => 'Plug',
            self::Instalaciones => 'Building2',
            self::Personal => 'Users',
        };
    }

    /**
     * Si el activo tiene una carcasa donde pegar una etiqueta.
     *
     * Lo decide quién entra en la hoja de etiquetas QR: una instancia EC2 y una
     * suscripción de SaaS no se etiquetan porque no hay dónde. Es la misma regla
     * que ya aplicaba el generador de etiquetas del Excel.
     */
    public function esFisico(): bool
    {
        return match ($this) {
            self::Hardware, self::Comunicaciones, self::Soportes,
            self::EquipamientoAuxiliar, self::Instalaciones => true,
            self::Servicios, self::Datos, self::Software, self::Personal => false,
        };
    }

    /** Un ejemplo de cada tipo, para que el desplegable no obligue a adivinar. */
    public function ejemplo(): string
    {
        return match ($this) {
            self::Servicios => 'Sede electrónica, correo, directorio',
            self::Datos => 'Base de datos de expedientes, copias de seguridad, registros de actividad',
            self::Software => 'Gestor documental, sistema operativo, ofimática',
            self::Hardware => 'Servidor, portátil, cabina de almacenamiento',
            self::Comunicaciones => 'Fibra, red inalámbrica, VPN con la sede remota',
            self::Soportes => 'Cintas, discos externos, papel',
            self::EquipamientoAuxiliar => 'SAI, climatización, cableado',
            self::Instalaciones => 'CPD, oficina, armario de comunicaciones',
            self::Personal => 'Administrador de sistemas, usuarios, subcontrata',
        };
    }
}
