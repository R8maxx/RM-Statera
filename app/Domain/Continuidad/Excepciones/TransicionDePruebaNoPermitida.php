<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Excepciones;

use App\Domain\Continuidad\Enums\EstadoPrueba;
use DomainException;

/**
 * Un paso que la máquina de estados de una prueba de continuidad no admite, o
 * una cancelación sin motivo.
 *
 * **`realizada` y `cancelada` son terminales.** No hay una máquina de estados
 * con `transicionesPermitidas()` como la de un incidente o un BIA: sólo hay
 * una salida válida desde `planificada` hacia cada una, y cada salida la
 * sella su propia acción —`RegistrarResultadoPrueba` y `CancelarPrueba`—, así
 * que esta excepción cubre exactamente dos cosas: intentar cualquiera de las
 * dos desde un estado que no es `planificada`, y cancelar sin decir por qué.
 *
 * **Y una tercera, que no es del ciclo pero es de la misma familia**:
 * `servicioAjeno()`, cuando `RegistrarResultadoPrueba` recibe un
 * `activo_id` que la prueba no cubre. La pivote de servicios la fija
 * `PlanificarPrueba` al planificar; registrar un resultado no es el sitio
 * para ampliarla, así que un id que no esté ya adjunto es un dato que no
 * encaja, no un servicio nuevo que añadir de paso.
 */
final class TransicionDePruebaNoPermitida extends DomainException
{
    public static function entre(EstadoPrueba $desde, EstadoPrueba $hasta): self
    {
        return new self(sprintf(
            'Una prueba «%s» no puede pasar a «%s»: %s es un estado terminal.',
            $desde->etiqueta(),
            $hasta->etiqueta(),
            $desde->etiqueta(),
        ));
    }

    public static function sinMotivo(EstadoPrueba $destino): self
    {
        return new self(sprintf(
            'Pasar a «%s» exige escribir por qué: es lo que explica que una prueba planificada no '
            .'se llegara a realizar.',
            $destino->etiqueta(),
        ));
    }

    public static function servicioAjeno(int $activoId): self
    {
        return new self(sprintf(
            'El activo #%d no es uno de los servicios que esta prueba cubre: registrar un resultado '
            .'no amplía la pivote que fijó la planificación.',
            $activoId,
        ));
    }
}
