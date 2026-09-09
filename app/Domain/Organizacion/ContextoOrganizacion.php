<?php

declare(strict_types=1);

namespace App\Domain\Organizacion;

use App\Domain\Organizacion\Models\Organizacion;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * La organización activa de la petición o del comando en curso.
 *
 * Mantiene sincronizadas las tres capas de aislamiento: al establecer el
 * contexto emite también la variable de sesión de PostgreSQL de la que depende
 * la política de Row Level Security. Si sólo se cambiara una de las dos, la
 * aplicación y la base discreparían sobre quién es el tenant, que es la clase de
 * fallo que nadie detecta hasta que ya ha filtrado datos.
 *
 * Y una cuarta cosa que tampoco puede divergir: el «team» de
 * `spatie/laravel-permission`, que está configurado como `organizacion_id`. Los
 * roles son por organización, así que si el registrar se quedara apuntando a la
 * anterior, un usuario vería los permisos de otro tenant. Va aquí y no en un
 * middleware aparte precisamente para que no exista un camino que fije una cosa
 * y olvide la otra.
 */
final class ContextoOrganizacion
{
    private ?int $organizacionId = null;

    private bool $mantenimiento = false;

    public function establecer(Organizacion|int $organizacion): void
    {
        $this->organizacionId = $organizacion instanceof Organizacion ? $organizacion->id : $organizacion;

        $this->sincronizarConLaBase();
    }

    public function olvidar(): void
    {
        $this->organizacionId = null;

        $this->sincronizarConLaBase();
    }

    public function id(): ?int
    {
        return $this->organizacionId;
    }

    public function hayContexto(): bool
    {
        return $this->organizacionId !== null;
    }

    public function idObligatorio(): int
    {
        return $this->organizacionId ?? throw new RuntimeException(
            'No hay organización activa. Toda operación sobre datos propios necesita contexto de organización.'
        );
    }

    public function enMantenimiento(): bool
    {
        return $this->mantenimiento;
    }

    /**
     * Ejecuta el callback viendo los datos de TODAS las organizaciones.
     *
     * Es la única puerta que atraviesa las tres capas, y está reservada a los
     * comandos de mantenimiento explícitos: hoy, el importador del catálogo, que
     * tiene que contar cuántas implantaciones de cuántas organizaciones quedan
     * afectadas por una revisión del marco. Nunca desde una petición web.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function comoMantenimiento(Closure $callback): mixed
    {
        $anterior = $this->mantenimiento;
        $this->mantenimiento = true;
        $this->sincronizarConLaBase();

        try {
            return $callback();
        } finally {
            $this->mantenimiento = $anterior;
            $this->sincronizarConLaBase();
        }
    }

    /**
     * Ejecuta el callback como si fuera otra organización, y restaura la
     * anterior al terminar, incluso si el callback lanza.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function paraOrganizacion(Organizacion|int $organizacion, Closure $callback): mixed
    {
        $anterior = $this->organizacionId;
        $this->establecer($organizacion);

        try {
            return $callback();
        } finally {
            $this->organizacionId = $anterior;
            $this->sincronizarConLaBase();
        }
    }

    /**
     * Vuelca el estado a las variables de sesión de PostgreSQL, que es de donde
     * lee la política de RLS.
     */
    private function sincronizarConLaBase(): void
    {
        DB::statement("SELECT set_config('app.organizacion_actual', ?, false)", [
            $this->organizacionId === null ? '' : (string) $this->organizacionId,
        ]);

        DB::statement("SELECT set_config('app.mantenimiento', ?, false)", [
            $this->mantenimiento ? 'on' : 'off',
        ]);

        $this->sincronizarPermisos();
    }

    /**
     * Apunta el registrar de permisos a la organización activa y tira su caché.
     *
     * La caché de spatie es por proceso y no distingue tenant: sin vaciarla, los
     * roles resueltos para la organización anterior seguirían contestando a la
     * siguiente. En una petición web da igual —hay una sola organización por
     * petición—, pero en un comando que recorre varias, y en los tests, no.
     */
    private function sincronizarPermisos(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($this->organizacionId);
        $registrar->forgetCachedPermissions();
    }
}
