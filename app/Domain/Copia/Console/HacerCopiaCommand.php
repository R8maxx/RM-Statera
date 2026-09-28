<?php

declare(strict_types=1);

namespace App\Domain\Copia\Console;

use App\Domain\Copia\HacerCopia;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * La copia de cada noche (§ 6): volcado cifrado de la base, espejo de los
 * ficheros y retirada de lo que pasa del plazo.
 *
 * No toca ninguna organización en concreto y no usa `comoMantenimiento()`: lee
 * con su propio rol, que tiene BYPASSRLS y no puede escribir.
 */
final class HacerCopiaCommand extends Command
{
    protected $signature = 'copias:hacer';

    protected $description = 'Vuelca y cifra la base, copia los ficheros y retira las copias caducadas';

    public function handle(HacerCopia $hacer): int
    {
        ['manifiesto' => $manifiesto, 'objetos' => $objetos, 'retiradas' => $retiradas] = $hacer();

        $this->components->twoColumnDetail('Copia', $manifiesto->nombre);
        $this->components->twoColumnDetail('Tamaño cifrado', Number::fileSize($manifiesto->bytes));
        $this->components->twoColumnDetail('Tablas', (string) count($manifiesto->recuentos));
        $this->components->twoColumnDetail('Filas', (string) array_sum($manifiesto->recuentos));

        foreach ($objetos as $disco => ['copiados' => $copiados, 'presentes' => $presentes]) {
            $this->components->twoColumnDetail("Ficheros de {$disco}", "{$copiados} nuevos, {$presentes} ya copiados");
        }

        $this->components->twoColumnDetail('Copias retiradas', $retiradas === [] ? 'ninguna' : implode(', ', $retiradas));

        return self::SUCCESS;
    }
}
