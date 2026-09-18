<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Enums;

/**
 * Quién es la parte interesada. Cláusula 4.2.
 *
 * Ocho casos y **el ámbito no se deduce de aquí**: un empleado es interno y un
 * regulador externo, pero un socio o un accionista son lo que cada organización
 * decida. Deducirlo acertaría en seis de ocho, que es la peor cifra posible —
 * suficiente para parecer que funciona y para colar dos errores. Va en columna
 * propia, que es lo que explica `Ambito`.
 *
 * `Sociedad` es el público que no tiene relación contractual con la organización y
 * al que le pasan cosas igual: los usuarios de un servicio público, los vecinos de
 * una instalación. En una organización sujeta al ENS es la parte interesada que
 * más justifica el propio ENS, y sin un valor para ella acabaría apuntada como
 * «cliente», que es exactamente lo que no es.
 *
 * **Sin `tono()` ni `icono()`**, como `MateriaCuestion` y por lo mismo: ocho
 * colores distinguibles no existen en la paleta, y aquí el tipo es un filtro y no
 * una señal. Se lee escrito, en la columna que lleva al lado.
 */
enum TipoParteInteresada: string
{
    case Cliente = 'cliente';
    case Empleado = 'empleado';
    case Direccion = 'direccion';
    case Proveedor = 'proveedor';
    case Regulador = 'regulador';
    case Socio = 'socio';
    case Sociedad = 'sociedad';
    case Accionista = 'accionista';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cliente => 'Cliente',
            self::Empleado => 'Personal',
            self::Direccion => 'Dirección',
            self::Proveedor => 'Proveedor',
            self::Regulador => 'Regulador o supervisor',
            self::Socio => 'Socio o aliado',
            self::Sociedad => 'Sociedad y usuarios',
            self::Accionista => 'Propiedad',
        };
    }

    /**
     * El ámbito que se propone al elegir el tipo, para no preguntarlo dos veces.
     *
     * **Es una propuesta y no una derivación**, que es toda la diferencia: el
     * formulario lo rellena y quien escribe puede cambiarlo. Con `Socio` y
     * `Accionista` se queda en nulo a propósito, porque ahí no hay respuesta por
     * defecto que no sea adivinar.
     */
    public function ambitoSugerido(): ?Ambito
    {
        return match ($this) {
            self::Empleado, self::Direccion => Ambito::Interno,
            self::Cliente, self::Proveedor, self::Regulador, self::Sociedad => Ambito::Externo,
            self::Socio, self::Accionista => null,
        };
    }
}
