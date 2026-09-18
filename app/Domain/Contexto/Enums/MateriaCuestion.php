<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Enums;

/**
 * De qué va una cuestión del contexto.
 *
 * Es el **segundo** clasificador, y no compite con `TipoCuestion`: aquél dice
 * dónde cae en la matriz y éste de qué habla. Sirven a preguntas distintas —«¿qué
 * amenazas tenemos?» y «¿qué se nos viene por el lado legal, sea amenaza u
 * oportunidad?»— y la segunda es la que el auditor hace cuando revisa si el
 * análisis cubre algo más que tecnología.
 *
 * Se llama `materia` y no `ambito` porque `Ambito` ya es el interno/externo que
 * comparten las cuestiones y las partes interesadas.
 *
 * **Ocho valores, y la lista se queda cerrada.** Son las seis clásicas de un
 * análisis del entorno más las dos que este dominio necesita de verdad: lo
 * contractual, porque en una organización que vive de prestar servicio la mitad de
 * lo que la ata viene de un contrato, y lo ambiental, que desde la enmienda 1:2024
 * dejó de ser opcional.
 *
 * **Sin `tono()` ni `icono()` a propósito.** Ocho colores distinguibles no existen
 * en la paleta —lo más parecido son los nueve `--tipo-*`, medidos y ya sin
 * holgura— y un tipo de activo y una materia no son la misma clase de cosa. Aquí
 * la materia es un filtro, no una señal: se lee escrita.
 */
enum MateriaCuestion: string
{
    case LegalRegulatorio = 'legal_regulatorio';
    case Tecnologico = 'tecnologico';
    case Economico = 'economico';
    case Organizativo = 'organizativo';
    case Social = 'social';
    case Ambiental = 'ambiental';
    case Competitivo = 'competitivo';
    case Contractual = 'contractual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LegalRegulatorio => 'Legal y regulatoria',
            self::Tecnologico => 'Tecnológica',
            self::Economico => 'Económica',
            self::Organizativo => 'Organizativa',
            self::Social => 'Social y cultural',
            self::Ambiental => 'Ambiental',
            self::Competitivo => 'Competencia y mercado',
            self::Contractual => 'Contractual',
        };
    }

    /**
     * La materia que trae de serie una cuestión del cambio climático.
     *
     * No la impone —una sequía que corta el suministro eléctrico de un centro de
     * proceso de datos es tan ambiental como operativa, y quien la escribe decide—,
     * pero la propone, que es lo que evita que la enmienda de 2024 acabe repartida
     * entre ocho materias y no se pueda contar.
     */
    public static function porDefectoDelClima(): self
    {
        return self::Ambiental;
    }
}
