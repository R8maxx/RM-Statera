<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cómo se supo. Sin integración con escáneres —está fuera de alcance—, así que
 * «escaneo» es que alguien pasó el resultado de uno a mano.
 */
#[TypeScript]
enum OrigenVulnerabilidad: string
{
    case Escaneo = 'escaneo';
    case Aviso = 'aviso';
    case Fabricante = 'fabricante';
    case Auditoria = 'auditoria';
    case Pentest = 'pentest';
    case Interna = 'interna';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Escaneo => 'Escaneo de vulnerabilidades',
            self::Aviso => 'Aviso de un CERT (CCN-CERT, INCIBE-CERT)',
            self::Fabricante => 'Boletín del fabricante',
            self::Auditoria => 'Auditoría',
            self::Pentest => 'Prueba de intrusión',
            self::Interna => 'Detección interna',
        };
    }
}
