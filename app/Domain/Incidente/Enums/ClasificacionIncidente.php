<?php

declare(strict_types=1);

namespace App\Domain\Incidente\Enums;

/**
 * Las clases de nivel superior de la taxonomía de incidentes del CCN-STIC 817.
 *
 * **Enum y no catálogo en YAML**, por el mismo reparto que `GrupoAmenaza` frente
 * a las 56 amenazas de MAGERIT: las clases son la **estructura** de la taxonomía
 * y no su contenido. Los subtipos —que sí son contenido, y son muchos— no están
 * cargados.
 *
 * > **Sin contrastar contra la guía, y queda dicho.** El Anexo II se contrastó
 * > celda a celda contra el BOE y de ahí salieron 73 errores de 273; aquí no se
 * > ha hecho ese trabajo. Se usan como **clasificación de trabajo**, sirven para
 * > agrupar y filtrar, y **no deciden nada**: ni la peligrosidad, ni si hay que
 * > notificar, ni a quién. El día que se contrasten, entran los subtipos.
 *
 * `Otros` existe a propósito y no es un descuido: quien apunta un incidente a las
 * tres de la mañana no está clasificando taxonomías, y sin un valor para «todavía
 * no lo sé» elegiría el que menos mal le suena — que es el argumento de
 * `OrigenTarea::Propia` y el de `EstadoControl::PorConfirmar`.
 */
enum ClasificacionIncidente: string
{
    case ContenidoAbusivo = 'contenido_abusivo';
    case ContenidoDanino = 'contenido_danino';
    case ObtencionInformacion = 'obtencion_informacion';
    case IntentoIntrusion = 'intento_intrusion';
    case Intrusion = 'intrusion';
    case Disponibilidad = 'disponibilidad';
    case CompromisoInformacion = 'compromiso_informacion';
    case Fraude = 'fraude';
    case Vulnerable = 'vulnerable';
    case Otros = 'otros';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ContenidoAbusivo => 'Contenido abusivo',
            self::ContenidoDanino => 'Contenido dañino',
            self::ObtencionInformacion => 'Obtención de información',
            self::IntentoIntrusion => 'Intento de intrusión',
            self::Intrusion => 'Intrusión',
            self::Disponibilidad => 'Disponibilidad',
            self::CompromisoInformacion => 'Compromiso de la información',
            self::Fraude => 'Fraude',
            self::Vulnerable => 'Vulnerabilidad',
            self::Otros => 'Sin clasificar',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::ContenidoAbusivo => 'Correo no deseado, acoso o difusión de contenido ilegal.',
            self::ContenidoDanino => 'Código dañino: virus, troyano, ransomware o minería no autorizada.',
            self::ObtencionInformacion => 'Reconocimiento, escaneo de puertos o ingeniería social sin acceso logrado.',
            self::IntentoIntrusion => 'Explotación de una vulnerabilidad o adivinación de credenciales, sin éxito.',
            self::Intrusion => 'Acceso no autorizado conseguido, a una cuenta, a un sistema o a una aplicación.',
            self::Disponibilidad => 'Denegación de servicio, sabotaje, interrupción o fallo por causa externa.',
            self::CompromisoInformacion => 'Acceso, modificación o divulgación no autorizada de datos.',
            self::Fraude => 'Uso no autorizado de recursos, suplantación o violación de derechos.',
            self::Vulnerable => 'Configuración débil o vulnerabilidad conocida detectada y no explotada.',
            self::Otros => 'Todavía sin clasificar, o no encaja en ninguna de las anteriores.',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::ContenidoAbusivo => 'MessageSquareWarning',
            self::ContenidoDanino => 'Swords',
            self::ObtencionInformacion => 'Eye',
            self::IntentoIntrusion => 'ShieldAlert',
            self::Intrusion => 'ShieldAlert',
            self::Disponibilidad => 'CloudLightning',
            self::CompromisoInformacion => 'Database',
            self::Fraude => 'Ban',
            self::Vulnerable => 'TriangleAlert',
            self::Otros => 'CircleHelp',
        };
    }

    /**
     * **Ninguna gasta rojo, y el gris de `Otros` es deliberado.** La gravedad la
     * lleva `PeligrosidadIncidente`, no la clase: una intrusión puede ser menor y
     * un correo fraudulento puede acabar en una transferencia. Pintar la clase de
     * alarma haría que el registro se leyera por el color equivocado.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Intrusion, self::CompromisoInformacion, self::ContenidoDanino => 'en_progreso',
            self::IntentoIntrusion, self::ObtencionInformacion, self::Disponibilidad, self::Fraude => 'planificado',
            self::ContenidoAbusivo, self::Vulnerable => 'en_revision',
            self::Otros => 'no_iniciado',
        };
    }
}
