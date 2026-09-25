<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Excepciones;

use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use DomainException;

/** Lo que el registro de vulnerabilidades impide, con la frase que lo explica. */
final class OperacionDeVulnerabilidadNoPermitida extends DomainException
{
    public static function transicion(EstadoVulnerabilidad $desde, EstadoVulnerabilidad $hasta): self
    {
        return new self(sprintf('Una vulnerabilidad «%s» no puede pasar a «%s».', $desde->etiqueta(), $hasta->etiqueta()));
    }

    public static function sinMotivo(EstadoVulnerabilidad $destino): self
    {
        return new self(sprintf(
            'Pasar a «%s» exige escribir por qué: es lo que se pregunta cuando alguien la encuentra después.',
            $destino->etiqueta(),
        ));
    }

    public static function sinVerificacion(): self
    {
        return new self(
            'Para cerrarla hay que escribir cómo se comprobó que ya no está: aplicar el parche no es lo mismo '
            .'que verificar que la vulnerabilidad desapareció.'
        );
    }

    public static function sinPermisoParaAceptar(): self
    {
        return new self(
            'Aceptar una vulnerabilidad sin corregirla es asumir un riesgo, y lo firma quien tiene permiso para '
            .'aceptarlo: el responsable de seguridad.'
        );
    }
}
