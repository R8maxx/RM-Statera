<?php

declare(strict_types=1);

namespace App\Domain\Obligacion\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Con qué registro del producto se demuestra que una obligación se cumplió.
 *
 * Existe para que `app/Domain/Obligacion/` **importe un enum y no tres módulos**.
 * Un cumplimiento apunta a una auditoría, a un acta de revisión o a un documento,
 * y las tres columnas las lee una sola clase —`Referencia`— que traduce la que
 * venga a una etiqueta, un icono y una URL. Sin esto, el dominio de obligaciones
 * dependería de `Auditoria`, `RevisionDireccion` y `Documento` para pintar un
 * enlace.
 *
 * Misma forma que `Aviso\Fuente`, y por el mismo motivo: quien pinta el enlace no
 * tiene que saber de qué es.
 */
#[TypeScript]
enum ReferenciaCumplimiento: string
{
    case Auditoria = 'auditoria';
    case RevisionDireccion = 'revision_direccion';
    case Documento = 'documento';

    /** La columna de `compromiso_cumplimientos` donde vive cada una. */
    public function columna(): string
    {
        return match ($this) {
            self::Auditoria => 'auditoria_id',
            self::RevisionDireccion => 'revision_direccion_id',
            self::Documento => 'documento_id',
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Auditoria => 'Auditoría',
            self::RevisionDireccion => 'Revisión por la dirección',
            self::Documento => 'Documento',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Auditoria => 'SearchCheck',
            self::RevisionDireccion => 'UserRoundCheck',
            self::Documento => 'FileCheck',
        };
    }

    public function url(int $id): string
    {
        return match ($this) {
            self::Auditoria => "/auditorias/{$id}",
            self::RevisionDireccion => "/revision-direccion/{$id}",
            self::Documento => "/documentos/{$id}",
        };
    }
}
