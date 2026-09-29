<?php

declare(strict_types=1);

namespace App\Domain\Persona\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Una fecha que se guarda cifrada y se lee como fecha (punto 35).
 *
 * Laravel no sabe combinar `encrypted` con `date`. La fecha de nacimiento es un
 * dato personal y además se lee como fecha —`toDateString()` en la ficha—, así
 * que hace falta un cast propio: se cifra la cadena `Y-m-d` y se devuelve un
 * `Carbon`.
 *
 * **Implementa la comparación a propósito.** Cifrar la misma fecha da un texto
 * distinto cada vez, y sin `compare()` Eloquent la daría por cambiada en cada
 * guardado: un evento de traza falso por cada vez que alguien edita otra cosa
 * de la ficha.
 *
 * @implements CastsAttributes<Carbon, Carbon|string>
 */
final class FechaCifrada implements CastsAttributes, ComparesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return self::descifrar($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $fecha = $value instanceof Carbon ? $value : Carbon::parse((string) $value);

        return Crypt::encryptString($fecha->toDateString());
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return self::descifrar($firstValue)?->toDateString() === self::descifrar($secondValue)?->toDateString();
    }

    private static function descifrar(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return Carbon::parse(Crypt::decryptString((string) $valor))->startOfDay();
    }
}
