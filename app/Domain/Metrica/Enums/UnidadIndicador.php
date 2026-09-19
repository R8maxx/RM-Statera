<?php

declare(strict_types=1);

namespace App\Domain\Metrica\Enums;

use App\Domain\Tarea\Coste;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * En qué se mide un indicador.
 *
 * Existe para que la cifra se escriba igual en la tabla, en la ficha, en el
 * panel y en el acta. Sin ella, un 92 sale «92» en un sitio y «92 %» en otro, y
 * el auditor pregunta cuál de los dos es el bueno.
 *
 * **El euro lo escribe `Tarea\Coste::escribir()`**, que es su tercer cliente y
 * la razón por la que esa clase existe: el formato estaba escrito dos veces
 * —la columna de la tabla y la ficha— e iba camino de la tercera.
 */
#[TypeScript]
enum UnidadIndicador: string
{
    case Porcentaje = 'porcentaje';
    case Recuento = 'recuento';
    case Dias = 'dias';
    case Euros = 'euros';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Porcentaje => 'Porcentaje',
            self::Recuento => 'Recuento',
            self::Dias => 'Días',
            self::Euros => 'Euros',
        };
    }

    /**
     * La cifra escrita.
     *
     * Un porcentaje y un recuento no llevan decimales —«el 91,67 %» finge una
     * precisión que 11 de 12 no tiene— y los días sí, porque una media de 3,5
     * días es un dato distinto de 3.
     */
    public function escribir(float $valor): string
    {
        return match ($this) {
            self::Porcentaje => number_format($valor, 0, ',', '.').' %',
            self::Recuento => number_format($valor, 0, ',', '.'),
            self::Dias => number_format($valor, 1, ',', '.').' d',
            self::Euros => Coste::escribir($valor),
        };
    }
}
