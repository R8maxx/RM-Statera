<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * `RefreshDatabase`, pero migrando con el rol dueño de las tablas.
 *
 * Desde el punto 32 la aplicación se conecta con `statera_app`, que lee y
 * escribe filas y no puede crear ni alterar nada, así que el `migrate:fresh`
 * de Laravel moriría en la primera tabla. Se migra con `pgsql_migraciones`, y
 * **los tests corren con `pgsql`**, que es la conexión de la aplicación: lo
 * que se prueba es lo que puede hacer Statera, no lo que puede hacer quien
 * construye el esquema. Un test que corriera como dueño vería un `GRANT` o un
 * `DISABLE TRIGGER` salirle bien.
 *
 * Va en un trait propio y no en `TestCase` porque un método de trait pisa al de
 * la clase padre: definido en `TestCase`, el `->use(RefreshDatabase::class)` de
 * `Pest.php` lo habría tapado sin avisar.
 */
trait RefrescaLaBase
{
    use RefreshDatabase {
        migrateFreshUsing as migrateFreshUsingDeLaravel;
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [...$this->migrateFreshUsingDeLaravel(), '--database' => 'pgsql_migraciones'];
    }
}
