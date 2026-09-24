<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La traza aprende a registrar sesiones y cambios de rol (§ 6 y § 4.19).
 *
 * Hasta aquí sólo sabía de filas creadas, actualizadas y eliminadas. Una
 * sesión no es una fila y un rol de spatie vive en una pivote sin modelo
 * propio, así que las dos cosas necesitan su verbo.
 *
 * **El `down()` restaura el `CHECK` con `NOT VALID`.** Los eventos no se pueden
 * borrar —`statera_app` no tiene `DELETE` sobre la tabla, y eso es lo que la
 * hace inmutable—, así que con un solo inicio de sesión registrado la versión
 * validante abortaría. `NOT VALID` deja las filas escritas donde están y vuelve
 * a impedir las nuevas.
 */
return new class extends Migration
{
    private const ANTERIORES = ['creado', 'actualizado', 'eliminado'];

    private const NUEVAS = ['rol_cambiado', 'inicio_sesion', 'cierre_sesion', 'intento_fallido'];

    public function up(): void
    {
        $this->restringir([...self::ANTERIORES, ...self::NUEVAS], validar: true);
    }

    public function down(): void
    {
        $this->restringir(self::ANTERIORES, validar: false);
    }

    /** @param  list<string>  $acciones */
    private function restringir(array $acciones, bool $validar): void
    {
        $lista = implode(', ', array_map(static fn (string $accion): string => "'{$accion}'", $acciones));

        DB::statement('ALTER TABLE eventos_auditoria DROP CONSTRAINT IF EXISTS eventos_auditoria_accion_check');
        DB::statement("ALTER TABLE eventos_auditoria ADD CONSTRAINT eventos_auditoria_accion_check CHECK (accion IN ({$lista}))".($validar ? '' : ' NOT VALID'));
    }
};
