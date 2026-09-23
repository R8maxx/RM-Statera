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
 *
 * **Y una cuarta, de las costuras del § 4.11 (tareas, no conformidades y
 * mejoras)**: `noDerivable()`, cuando `DerivarDePrueba` recibe una prueba que no
 * está `realizada` o cuyo resultado es `superada`. Una prueba superada no dejó
 * nada que corregir —igual que `ResultadoPrueba` no gasta el rojo de `Fallida`,
 * fallar es la prueba funcionando—, y una que sigue `planificada` o `cancelada`
 * no tiene resultado del que derivar nada todavía. Y su pareja, `yaTratada()`,
 * cuando la prueba ya tiene su no conformidad: una prueba se trata una vez.
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

    public static function noDerivable(): self
    {
        return new self(
            'Sólo se deriva una tarea, una no conformidad o una oportunidad de mejora de una prueba '
            .'ya realizada y con resultado parcial o fallido: una prueba superada no dejó nada que corregir.',
        );
    }

    public static function yaTratada(): self
    {
        return new self(
            'Esta prueba ya tiene su no conformidad: una prueba se trata una vez. Lo que quede por '
            .'corregir se añade como acción correctiva de esa no conformidad.',
        );
    }
}
