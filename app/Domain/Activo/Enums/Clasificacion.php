<?php

declare(strict_types=1);

namespace App\Domain\Activo\Enums;

/**
 * La etiqueta de clasificación de la información que contiene el activo
 * (`mp.info.2` del ENS, `A.5.12` de ISO).
 *
 * **No sustituye al nivel del Anexo I y no se deriva de él.** La clasificación
 * se decide y se estampa: alguien dice «esto es confidencial» y a partir de ahí
 * hay reglas de manejo, de etiquetado y de destrucción. El nivel de una
 * dimensión sale de valorar el perjuicio de un fallo. Un mismo activo puede ser
 * de uso interno y valer «alto» en disponibilidad. Contestan preguntas
 * distintas y conviven.
 */
enum Clasificacion: string
{
    case Publico = 'publico';
    case UsoInterno = 'uso_interno';
    case Confidencial = 'confidencial';
    case Restringido = 'restringido';
    case NoAplica = 'no_aplica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::UsoInterno => 'Uso interno',
            self::Confidencial => 'Confidencial',
            self::Restringido => 'Restringido',
            self::NoAplica => 'No aplica',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Publico => 'Puede difundirse sin restricción.',
            self::UsoInterno => 'Circula dentro de la organización. No se publica.',
            self::Confidencial => 'Sólo quien lo necesite para su trabajo. Su difusión causa perjuicio.',
            self::Restringido => 'Lista nominal de acceso. Su difusión causa perjuicio grave.',
            self::NoAplica => 'El activo no contiene información clasificable.',
        };
    }

    /**
     * El icono con el que se reconoce sin leer la etiqueta.
     *
     * Sube en cierre según sube la sensibilidad: mundo abierto, edificio,
     * candado, escudo.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Publico => 'Globe',
            self::UsoInterno => 'Building2',
            self::Confidencial => 'Lock',
            self::Restringido => 'ShieldAlert',
            self::NoAplica => 'Minus',
        };
    }

    /**
     * Sube en énfasis, como la categoría del ENS: es ordinal, no categórico.
     * `Restringido` es lo único que se marca en rojo, y no porque vaya mal sino
     * porque es lo que hay que vigilar de cerca.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Publico => 'no_iniciado',
            self::UsoInterno => 'basica',
            self::Confidencial => 'media',
            self::Restringido => 'alta',
            self::NoAplica => 'no_aplica',
        };
    }
}
