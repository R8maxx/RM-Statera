<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Enums;

/**
 * Los requisitos de todos los marcos viven en una sola tabla. El tipo distingue
 * las cláusulas del cuerpo de la ISO (4 a 10), los controles de su Anexo A y
 * las medidas del Anexo II del ENS.
 */
enum TipoRequisito: string
{
    case Clausula = 'clausula';
    case Control = 'control';
    case Medida = 'medida';
}
