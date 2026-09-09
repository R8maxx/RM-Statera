<?php

declare(strict_types=1);

namespace App\Domain\Evidencia\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Qué clase de prueba es. Los seis del § 2.2 de la especificación.
 *
 * No es decorativo: un contrato y una captura de pantalla envejecen de forma
 * distinta, y el auditor no acepta lo mismo de una que de otro.
 */
#[TypeScript]
enum TipoEvidencia: string
{
    case Captura = 'captura';
    case Log = 'log';
    case Informe = 'informe';
    case Contrato = 'contrato';
    case Registro = 'registro';
    case Certificado = 'certificado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Captura => 'Captura',
            self::Log => 'Registro de actividad',
            self::Informe => 'Informe',
            self::Contrato => 'Contrato',
            self::Registro => 'Registro',
            self::Certificado => 'Certificado',
        };
    }
}
