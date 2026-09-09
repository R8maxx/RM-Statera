<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Concerns;

use App\Domain\Auditoria\RegistroAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Deja traza de las altas, cambios y bajas del modelo.
 *
 * Lo usa toda entidad de cumplimiento (invariante 8). `EventoAuditoria` no lo
 * usa: un log que se audita a sí mismo es un bucle.
 *
 * Aviso sobre los borrados en cascada: las claves foráneas con
 * `cascadeOnDelete` las resuelve PostgreSQL, y Eloquent no dispara eventos por
 * las filas que se lleva por delante. Borrar un sistema deja un evento del
 * sistema, no cuarenta y cuatro de sus implantaciones. Es lo correcto para leer
 * la traza, pero conviene saberlo antes de buscar los que no están.
 */
trait RegistraTraza
{
    public static function bootRegistraTraza(): void
    {
        static::created(static fn (Model $modelo) => app(RegistroAuditoria::class)->creado($modelo));
        static::updated(static fn (Model $modelo) => app(RegistroAuditoria::class)->actualizado($modelo));
        static::deleted(static fn (Model $modelo) => app(RegistroAuditoria::class)->eliminado($modelo));
    }
}
