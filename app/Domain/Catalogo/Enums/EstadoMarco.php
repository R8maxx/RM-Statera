<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Enums;

enum EstadoMarco: string
{
    case Vigente = 'vigente';
    case Derogado = 'derogado';
}
