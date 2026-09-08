<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Sin `WithoutModelEvents`, y es deliberado.
 *
 * `PerteneceAOrganizacion` rellena `organizacion_id` en el evento `creating`.
 * Silenciar los eventos del modelo deja la columna a nulo y la política de RLS
 * rechaza la inserción — con un error de privilegios que no dice nada de la
 * causa real.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Un usuario sin `organizacion_id` no ve ni escribe nada: con RLS activo,
        // la política deniega por defecto. Por eso el alta de usuarios pasa
        // siempre por una organización.
        $this->call(DesarrolloSeeder::class);
    }
}
